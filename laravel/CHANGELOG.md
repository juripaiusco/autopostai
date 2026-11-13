# Novità di FaPer3

Qui trovi cosa cambia a ogni versione di FaPer3.
Numerazione: `MAJOR.MINOR.PATCH` — il secondo numero sale con le novità, il terzo con le sole correzioni.

## [Non rilasciato]

### Novità
- Puoi installare FaPer3 sul telefono come un'app, con la sua icona e la schermata di avvio.
- Senza connessione l'app mostra una pagina dedicata invece dell'errore del browser, e si ricarica da sola quando torna la rete.
- Newsletter e articoli WordPress: se nel prompt inserisci dei link, FaPer3 legge quelle pagine e scrive il testo con i loro contenuti reali, invece di immaginarli.
- Le immagini dei prodotti trovate in quei link compaiono nel testo di newsletter e articoli WordPress, sotto il prodotto a cui si riferiscono.
- Nella lista post la colonna «Attività» mostra, oltre ai commenti, a quante persone è arrivata la newsletter (es. 50/100) e quante l'hanno aperta, con la percentuale.
- I post la cui newsletter è ancora in consegna hanno lo stato «In invio» finché tutti i contatti non l'hanno ricevuta.
- Nel dettaglio del post la scheda Newsletter mostra destinatari, email inviate e da inviare, aperture, tasso di apertura e rimbalzi, con l'avanzamento dell'invio.
- Nel dettaglio del post, i «Risultati complessivi» contano le email aperte come views e quelle consegnate come persone raggiunte.
- Nel template della newsletter puoi posizionare le immagini del post dove vuoi con `[image 1]`, `[image 2]`…

### Miglioramenti
- Anche i post recenti della Dashboard mostrano la colonna «Attività» (commenti, invii e aperture newsletter) e lo stato «In invio».
- Se un link nel prompt non si può leggere (per esempio perché il sito blocca gli accessi automatici), l'anteprima te lo segnala.

### Correzioni
- Nel testo di newsletter e articoli non compaiono più immagini inventate dall'AI: restano solo quelle trovate davvero nei link.

## [2.1.1] — 2026-11-10

### Miglioramenti
- La versione dell'app è visibile in basso a destra in ogni pagina.
- Le notifiche si aprono in un pannello più leggibile, con la data di ogni avviso; la campanella si muove quando ce ne sono di nuove.

### Correzioni
- Su smartphone l'intestazione resta fissa in alto, anche con il menu aperto.
- Su smartphone il filtro per utente nel menu è allineato al pulsante "Nuovo post".
- Su smartphone i filtri per stato di post, contatti e account non escono più dallo schermo: si scorrono col dito.
- Su smartphone, nel primo passo del post, i campi Account e Titolo sono uno sotto l'altro invece che schiacciati affiancati.
- Su smartphone, nell'ultimo passo del post, i pulsanti Salva, Pubblica subito e Salva e aggiungi sono allineati a tutta larghezza.
- Su smartphone la paginazione delle liste non esce più dallo schermo ed è più facile da toccare.

## [2.1.0] — 2026-11-10

### Novità
- **Editor di testo ricco** per WordPress e Newsletter: grassetto, titoli, elenchi e link direttamente nell'anteprima, senza scrivere codice.
- **Editor per i social** con emoji e la scheda "Social": puoi personalizzare il testo dei social rispetto all'articolo, oppure farlo generare all'AI con "Genera versione social".
- **Link a un tuo post e pulsante CTA**: scegli il post da un elenco con anteprima e lo inserisci nel testo, senza ricordare codici.
- **Modifica dei post già pubblicati** con gli stessi editor della creazione.
- **Duplica post**: dall'elenco dei post crei una copia (testo, immagini e canali) da programmare in un'altra data.
- **"Salva e aggiungi"**: salva il post e crea subito una copia programmata un minuto dopo, pronta da modificare. Un avviso ti segnala se le due uscite sono troppo vicine.
- **Modulo PrestaShop** per raccogliere iscrizioni alla newsletter dal tuo negozio (con privacy e captcha).

### Miglioramenti
- La barra **"Crediti utilizzati"** mostra il consumo reale del mese e diventa rossa oltre il 90%.
- Anche la generazione dell'anteprima ora conta nei crediti del post.

### Correzioni
- Un post con data e non ancora uscito resta "Schedulato" anche dopo l'orario previsto (prima tornava "Bozza").
- Con solo WordPress e nessuna categoria il form non inviava il canale.
- Sui social il testo esce senza formattazione da articolo quando il post ha anche WordPress o Newsletter.

## [2.0.0] — 2026-11-06

La nuova versione di FaPer3, riscritta da zero.

### Novità
- Interfaccia completamente nuova, con dashboard basata sui dati reali: post pubblicati, commenti, risposte AI e aperture newsletter.
- **Contatti e newsletter** con il tuo server di posta: anagrafica con nome, cognome e tag, import da CSV, invio filtrato per tag, gestione di disiscrizioni e indirizzi non validi.
- **Immagini generate con l'AI**, con scelta del modello.
- **Monitoraggio AI** dei post e stato "Completato"; le risposte AI ai commenti tengono conto del post e delle sue foto.
- **Modifica del testo dopo la pubblicazione** su Facebook e WordPress.
- Opzione esplicita "l'AI guarda l'immagine" per scrivere il testo partendo dalla foto.
- Miniatura della prima immagine nell'elenco dei post e in dashboard.
- **Server MCP**: collega FaPer3 al tuo assistente AI per leggere, scrivere e pianificare post e contatti.

### Miglioramenti
- Il testo per LinkedIn rispetta il limite di caratteri della piattaforma.
- Liste newsletter e categorie WordPress selezionabili come etichette, con il numero di iscritti e articoli.
- Messaggi dell'applicazione in italiano.
