"""Client SMTP custom (per-account, credenziali da settings.nl_smtp_*).

A differenza di Mailchimp/Brevo (una singola API di campagna) qui non c'e' un
"servizio terzo" da chiamare: e' un invio SMTP diretto, un messaggio alla
volta — il chiamante (publisher/tasks/newsletter_send.py) itera i contatti e
chiama send() per ciascuno.

Solo RecipientRefused e' un bounce (il server ha rifiutato QUEL destinatario);
qualsiasi altra eccezione (connessione, login, mittente, timeout) e' un problema
dell'account/server, non del contatto.
"""

from __future__ import annotations

import html as html_lib
import re
import smtplib
from email.mime.multipart import MIMEMultipart
from email.mime.text import MIMEText
from email.utils import formataddr, formatdate, make_msgid


class RecipientRefused(Exception):
    """Il server SMTP ha rifiutato il destinatario (RCPT TO). `permanent` = 5xx
    (hard bounce: casella inesistente); 4xx = temporaneo (casella piena,
    greylisting)."""

    def __init__(self, code: int, message: str):
        super().__init__(f"{code} {message}")
        self.code = code
        self.permanent = 500 <= code < 600


def parse_sender(sender: str | None, fallback_address: str | None) -> tuple[str | None, str]:
    """'Nome Mittente <email@dominio.it>' -> (nome, email). Fallback allo
    username SMTP se il campo mittente non e' nel formato atteso."""
    if sender:
        match = re.match(r"^(.*)<(.+)>$", sender.strip())
        if match:
            return match.group(1).strip() or None, match.group(2).strip()
    return None, (sender or fallback_address or "")


def build_message(
    to_email: str,
    subject: str,
    html: str,
    sender: str | None,
    fallback_address: str | None,
    unsubscribe_url: str | None = None,
) -> MIMEMultipart:
    """Messaggio pronto per l'invio.

    Header richiesti da Gmail/Yahoo per gli invii massivi (senza, le email
    finiscono in spam o vengono rifiutate): Date, Message-ID, List-Unsubscribe
    con List-Unsubscribe-Post (disiscrizione one-click RFC 8058: il client fa
    POST sull'URL firmato, gestito da Laravel). Parte text/plain accanto
    all'html: una email solo-html pesa sul punteggio antispam.
    """
    from_name, from_address = parse_sender(sender, fallback_address)

    message = MIMEMultipart("alternative")
    message["Subject"] = subject
    message["From"] = formataddr((from_name, from_address)) if from_name else from_address
    message["To"] = to_email
    message["Date"] = formatdate(localtime=True)
    message["Message-ID"] = make_msgid(domain=from_address.rpartition("@")[2] or None)
    if unsubscribe_url:
        message["List-Unsubscribe"] = f"<{unsubscribe_url}>"
        message["List-Unsubscribe-Post"] = "List-Unsubscribe=One-Click"

    # Ordine RFC 2046: la parte preferita (html) per ultima.
    message.attach(MIMEText(html_to_text(html), "plain", "utf-8"))
    message.attach(MIMEText(html, "html", "utf-8"))
    return message


def html_to_text(html: str) -> str:
    """Versione testo leggibile dell'html newsletter: link come "testo (url)",
    blocchi su righe separate, niente tag/stili/pixel."""
    text = re.sub(r"(?is)<(style|script|head)\b.*?</\1>", "", html)
    text = re.sub(
        r'(?is)<a\b[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)</a>',
        lambda m: f"{re.sub(r'<[^>]+>', '', m.group(2)).strip()} ({m.group(1)})",
        text,
    )
    text = re.sub(r"(?i)<br\s*/?>", "\n", text)
    text = re.sub(r"(?i)</(p|div|h[1-6]|li|tr|table)>", "\n\n", text)
    text = re.sub(r"<[^>]+>", "", text)
    text = html_lib.unescape(text)
    text = re.sub(r"[ \t]+", " ", text)
    text = re.sub(r"\n\s*\n\s*(\n\s*)+", "\n\n", text)
    return "\n".join(line.strip() for line in text.strip().splitlines())


class SmtpClient:
    def __init__(self, host: str, port, username: str, password: str, encryption: str | None):
        self.host = host
        self.port = int(port or 587)
        self.username = username
        self.password = password
        self.encryption = (encryption or "").lower()

    def send(
        self,
        to_email: str,
        subject: str,
        html: str,
        sender: str | None,
        fallback_address: str | None,
        unsubscribe_url: str | None = None,
    ) -> None:
        message = build_message(to_email, subject, html, sender, fallback_address, unsubscribe_url)
        from_address = parse_sender(sender, fallback_address)[1]

        if self.encryption == "ssl":
            with smtplib.SMTP_SSL(self.host, self.port, timeout=30) as server:
                self._login_and_send(server, from_address, to_email, message)
        else:
            with smtplib.SMTP(self.host, self.port, timeout=30) as server:
                if self.encryption == "tls":
                    server.starttls()
                self._login_and_send(server, from_address, to_email, message)

    def _login_and_send(self, server: smtplib.SMTP, from_address: str, to_email: str, message: MIMEMultipart) -> None:
        if self.username:
            server.login(self.username, self.password or "")
        try:
            server.sendmail(from_address, [to_email], message.as_string())
        except smtplib.SMTPRecipientsRefused as e:
            code, reply = next(iter(e.recipients.values()), (0, b""))
            if isinstance(reply, bytes):
                reply = reply.decode(errors="replace")
            raise RecipientRefused(code, reply) from e
