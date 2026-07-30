"""Client SMTP custom (per-account, credenziali da settings.nl_smtp_*).

A differenza di Mailchimp/Brevo (una singola API di campagna) qui non c'e' un
"servizio terzo" da chiamare: e' un invio SMTP diretto, un messaggio alla
volta — il chiamante (publisher/tasks/newsletter_send.py) itera i contatti e
chiama send() per ciascuno, catturando le eccezioni come bounce sincrono.
"""

from __future__ import annotations

import re
import smtplib
from email.mime.multipart import MIMEMultipart
from email.mime.text import MIMEText
from email.utils import formataddr


def parse_sender(sender: str | None, fallback_address: str | None) -> tuple[str | None, str]:
    """'Nome Mittente <email@dominio.it>' -> (nome, email). Fallback allo
    username SMTP se il campo mittente non e' nel formato atteso."""
    if sender:
        match = re.match(r"^(.*)<(.+)>$", sender.strip())
        if match:
            return match.group(1).strip() or None, match.group(2).strip()
    return None, (sender or fallback_address or "")


class SmtpClient:
    def __init__(self, host: str, port, username: str, password: str, encryption: str | None):
        self.host = host
        self.port = int(port or 587)
        self.username = username
        self.password = password
        self.encryption = (encryption or "").lower()

    def send(self, to_email: str, subject: str, html: str, sender: str | None, fallback_address: str | None) -> None:
        from_name, from_address = parse_sender(sender, fallback_address)

        message = MIMEMultipart("alternative")
        message["Subject"] = subject
        message["From"] = formataddr((from_name, from_address)) if from_name else from_address
        message["To"] = to_email
        message.attach(MIMEText(html, "html"))

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
        server.sendmail(from_address, [to_email], message.as_string())
