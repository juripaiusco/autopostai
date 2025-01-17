from task.posts_send import posts_send
from task.comments_get import comments_get
from task.reply_send import reply_send
from task.task_complete import task_complete

#
# Questa funzione dev'essere richiamata da un contrab ogni minuto.
#
# Al suo interno main richiama 3 funzioni principali:
#
# 1. posts_send()
#    Vengono inviati tutti i post da inviare, cioè nel database ci sono dei
#    pronti per essere inviati, viene eseguita una query che li richiama e
#    vengono caricati nei vari canali.
#    RAGIONAMENTO: ----------------------------------------------------------
#    Statisticamente non verranno pubblicati tutti i post allo stesso orario
#    quindi la query che recupera i post da inviare li prende tutti.
#
# 2. comments_get()
#    Per ogni post inviato viene verificata la presenza di nuovi commenti,
#    questi ultimi vengono scaricati e salvati nel database, questo per poter
#    creare una risposta pertinente all'utente che ha commentato, perché
#    vengono presi tutti i vari dati per creare una risposta.
#    RAGIONAMENTO: ----------------------------------------------------------
#    Viene selezionato un post al minuto e che al suo interno ha gli ID di tutti
#    i canali utilizzati. In base alle impostazioni del canale vengono fatte
#    delle azioni, tra cui rispondere ai commenti.
#    Quando il numero dei commenti scaricati, corrisponde al numero dei commenti
#    massimo al quale il post può rispondere, il post viene marchiato come "task_complete",
#    in questo modo non verrà più selezionato dalla query iniziale per scaricare i
#    commenti.
#
# 3. reply_send()
#    Invio della risposta al commento, in base al tipo di post e commento fatto,
#    viene creata una risposta pertinente, in base alle impostazioni create.
#    RAGIONAMENTO: ----------------------------------------------------------
#    Viene fatta una query, selezionando un commento alla volta, quindi un commento
#    al minuto, al quale viene data una risposta, una volta risposto il commento
#    risulterà risposto, perché avrà un reply e un reply_id.
#
#    IMPORTANTE:
#    Se viene impostato un limite di risposte, per adesso questo probabilmente non
#    verrà rispettato nei grandi numeri, perché vengono scaricati molti commenti,
#    ma non è ancora indicato un massimo di download. Bisogna prevedere un controllo
#    nei commenti del db, così da limitare le risposte.
#
def main():
    debug = True

    posts_send(debug=debug)
    if debug is True: print()

    comments_get(debug=debug)
    if debug is True: print()

    reply_send(debug=debug)
    if debug is True: print()

    task_complete(debug=debug)

if __name__ == "__main__":
    main()
