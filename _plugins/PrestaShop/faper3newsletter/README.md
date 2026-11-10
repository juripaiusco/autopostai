# FaPer3 Newsletter — modulo PrestaShop 1.7

Form di iscrizione alla newsletter FaPer3 (nome, cognome, email, consenso privacy)
con invio ajax. Il negozio chiama l'API FaPer3 lato server: la chiave non arriva mai
al browser. Facoltativo: invio a FaPer3 anche dei clienti che attivano la newsletter
dal proprio account.

- PrestaShop 1.7.6 – 1.7.8 (testato su 1.7.8.9), PHP 7.1+, estensione cURL.
- Captcha facoltativo: Google reCAPTCHA v3 oppure Cloudflare Turnstile.

## Installazione

1. Crea lo zip (la cartella `faper3newsletter/` deve stare alla radice dello zip):
   ```bash
   cd _plugins/PrestaShop && zip -r faper3newsletter.zip faper3newsletter -x '*.DS_Store'
   ```
2. Back office → **Moduli → Module Manager → Carica un modulo** → scegli lo zip.
3. **Configura** e compila:
   - **URL FaPer3**: `https://faper3.it`.
   - **API key**: in FaPer3 apri **Account → [account] → Newsletter → API contatti** e
     genera la chiave. Viene mostrata una sola volta; serve un account con provider
     newsletter **SMTP custom**.
4. Il form compare nel footer (hook `displayFooterBefore`).
5. Se è installato `ps_emailsubscription`, toglilo dal footer per non avere due form:
   **Design → Posizioni** → `displayFooterBefore` → sgancia "Iscrizione alla newsletter".
   **Non disinstallarlo**: è il modulo che mostra la casella "newsletter" nel modulo di
   registrazione dei clienti.

Fino a quando URL e API key non sono impostati, il form non viene mostrato.

## Configurazione

| Sezione | Campo | Note |
|---|---|---|
| Connessione | Tag iscritti dal form | Più tag separati da virgola. Default `prestashop`. |
| Connessione | Origine consenso (form) | Salvata in FaPer3 come provenienza del consenso. Default `prestashop-newsletter`. |
| Testi, aspetto e privacy | Colore pulsante | Vuoto = colore del tema. Il testo diventa bianco, oppure scuro sui colori molto chiari. |
| Testi, aspetto e privacy | Titolo, sottotitolo, testo checkbox | Un valore per ogni lingua. |
| Testi, aspetto e privacy | Pagina informativa privacy | Pagina CMS collegata accanto alla checkbox. |
| Testi, aspetto e privacy | URL informativa privacy esterna | Per un'informativa fuori da PrestaShop (es. iubenda), una per lingua. Se compilato ha la precedenza sulla pagina CMS; lasciato vuoto, si usa la pagina CMS. |
| Anti-spam | Captcha | Nessuno, reCAPTCHA v3 o Turnstile. Servono site key e secret key. |
| Anti-spam | Soglia reCAPTCHA v3 | Da 0 a 1, default 0.5. Sotto questo punteggio l'iscrizione è rifiutata. |
| Clienti | Sincronizza clienti | Se attivo, invia a FaPer3 chi attiva la newsletter in registrazione o dal proprio account. Default tag `prestashop-clienti`. |

Le chiavi dei captcha si creano su:
- reCAPTCHA v3: https://www.google.com/recaptcha/admin (tipo "v3", con il dominio del negozio).
- Turnstile: Cloudflare dashboard → Turnstile → Add widget (con il dominio del negozio).

## Personalizzare l'aspetto

Il bottone usa le classi `btn btn-primary` del tema. Il suo colore si può cambiare dalla
configurazione del modulo (**Colore pulsante**). Gli altri colori e i font arrivano dal tema.
Input e bottone hanno gli angoli arrotondati (8px).

- **Template**: copialo in `themes/<tema>/modules/faper3newsletter/views/templates/hook/faper3newsletter.tpl`.
- **CSS**: aggiungi le regole nel CSS del tema, per esempio:
  ```css
  .f3n-block { color: #fff; }          /* testi chiari su sfondo scuro */
  .f3n-block { --f3n-radius: 4px; }    /* angoli di input e bottone (0 = squadrati) */
  ```
- **Posizione**: puoi spostare il form da Design → Posizioni, oppure inserirlo in un
  template del tema con `{widget name='faper3newsletter'}`.

## Comportamento

- Il visitatore vede un messaggio di conferma generico anche se era già iscritto,
  così il form non rivela chi è già in lista.
- In caso di problemi verso FaPer3 (chiave errata, servizio non raggiungibile, troppe
  richieste) il visitatore vede "Servizio momentaneamente non disponibile". Il dettaglio
  finisce in **Parametri avanzati → Log**, con messaggi che iniziano per "FaPer3 Newsletter".
- La sincronizzazione dei clienti non blocca mai la registrazione: se FaPer3 non risponde,
  l'errore viene solo scritto nel log.

## Limiti

- Se un cliente disattiva la newsletter in PrestaShop, la disiscrizione **non** viene
  inviata a FaPer3: si disiscrive dal link presente nelle email, oppure dalla pagina
  Contatti di FaPer3.
- Gli iscritti già presenti prima dell'installazione non vengono inviati. Esportali in
  CSV da PrestaShop e importali dalla pagina Contatti di FaPer3.
- Non c'è conferma via email (double opt-in). Come prova del consenso FaPer3 salva IP,
  data e origine.
