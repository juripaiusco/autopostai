"""Link di disiscrizione: signed URL Laravel generata lato Python.

Replica esatta di Illuminate\\Routing\\UrlGenerator::signedRoute() per la
route pubblica `newsletter.unsubscribe` (routes/web.php, middleware
'signed') — nessuna scadenza (nessun parametro 'expires'), quindi
hash_hmac('sha256', url_assoluto_senza_query, APP_KEY_grezza). Verificato
byte-per-byte contro un URL::signedRoute() reale in fase di sviluppo.
"""

from __future__ import annotations

import hashlib
import hmac

from publisher import config


def unsubscribe_url(contact_id: int) -> str:
    base = f"{config.APP_URL}/disiscrivi/{contact_id}"
    signature = hmac.new((config.APP_KEY or "").encode(), base.encode(), hashlib.sha256).hexdigest()
    return f"{base}?signature={signature}"
