# API — Registrazione contatto

Endpoint server-to-server per registrare un contatto (iscritto newsletter) per
un account specifico. Non è un form pubblico esposto a utenti finali: chi lo
chiama è un sistema esterno già autenticato (es. il sito del cliente), quindi
niente captcha né rate limiting oltre alla base di Laravel.

## Autenticazione

Header obbligatorio su ogni richiesta:

```
X-Api-Key: <chiave>
```

La chiave è legata a un singolo account (non globale). Si genera/rigenera da
**Account → [nome account] → Newsletter → API contatti** (visibile solo se
l'account ha il provider newsletter impostato su SMTP custom). La chiave si
salva come hash: **il valore in chiaro si vede una sola volta**, al momento
della generazione — se persa, va rigenerata (la precedente smette di
funzionare).

Risposta se la chiave manca o non è valida:

```
401 Unauthorized
{ "message": "API key mancante." }
{ "message": "API key non valida." }
```

## Endpoint

```
POST /api/contacts
Content-Type: application/json
X-Api-Key: <chiave>
```

### Payload

| Campo             | Tipo   | Obbligatorio | Note                                                   |
|--------------------|--------|:---:|---------------------------------------------------------------|
| `email`            | string | sì  | Validata per sintassi RFC **e record MX** del dominio.         |
| `consent_source`   | string | no  | Provenienza del consenso (es. `"sito-vetrina"`). Default `"api"`. |

```json
{
  "email": "mario.rossi@example.com",
  "consent_source": "form-homepage"
}
```

### Comportamento

1. Se l'email è validata negativamente (sintassi o MX assente) → `422`.
2. Se l'email è nella **suppression list** dell'account:
   - motivo `hard_bounce` o `complaint` → **rifiutata**, nessun contatto creato/modificato (`422`).
   - motivo `unsubscribe` → il contatto viene comunque registrato ma con stato
     `unsubscribed` (tracciato, non silenziosamente ignorato).
3. Se il contatto esiste già (anche soft-eliminato) per quell'account → non
   duplica: se era stato eliminato lo ripristina, altrimenti lo considera
   idempotente.
4. Altrimenti crea il contatto con stato `active`.

### Risposte

**201 — nuovo contatto creato**
```json
{ "status": "created", "contact": { "id": 42, "email": "mario.rossi@example.com", "status": "active" } }
```

**200 — contatto già esistente (idempotente)**
```json
{ "status": "exists", "contact": { "id": 42, "email": "mario.rossi@example.com", "status": "active" } }
```

**200 — contatto ripristinato (era stato eliminato)**
```json
{ "status": "restored", "contact": { "id": 42, "email": "mario.rossi@example.com", "status": "active" } }
```

**201 — email in suppression list per `unsubscribe` (registrata ma silenziata)**
```json
{ "status": "suppressed_unsubscribed", "contact": { "id": 42, "email": "mario.rossi@example.com", "status": "unsubscribed" } }
```

**422 — email in suppression list per `hard_bounce`/`complaint` (rifiutata)**
```json
{ "message": "Email non registrabile: presente in suppression list.", "reason": "hard_bounce" }
```

**422 — validazione fallita (sintassi email o MX)**
```json
{ "message": "The email field must be a valid email address.", "errors": { "email": ["..."] } }
```

**401 — chiave assente o non valida** (vedi sopra).

## Fuori scope (non gestito da questo endpoint)

- Assegnazione tag in fase di registrazione (va fatta successivamente da UI).
- Rate limiting/captcha oltre al throttle di base del gruppo route `api` di Laravel.
