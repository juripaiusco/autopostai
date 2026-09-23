"""Link di disiscrizione: signed URL Laravel generata lato Python.

Replica di Illuminate\\Routing\\UrlGenerator::signedRoute(..., absolute: false)
per la route pubblica `newsletter.unsubscribe` (routes/web.php, middleware
'signed:relative') — nessuna scadenza, quindi
hash_hmac('sha256', '/disiscrivi/{id}', APP_KEY_grezza). Firma sul solo path:
resta valida qualunque schema/host veda Laravel dietro un proxy. L'URL
restituito e' comunque assoluto (APP_URL), serve cliccabile nell'email.
"""

from __future__ import annotations

import hashlib
import hmac

from publisher import config


def unsubscribe_url(contact_id: int) -> str:
    path = f"/disiscrivi/{contact_id}"
    signature = hmac.new((config.APP_KEY or "").encode(), path.encode(), hashlib.sha256).hexdigest()
    return f"{(config.APP_URL or '').rstrip('/')}{path}?signature={signature}"
