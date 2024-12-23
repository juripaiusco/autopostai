from lib.gpt import GPT
from lib.meta import Meta


def main():

  # prompt = "Crea un testo di prova super semplice e super breve"
  # gpt = GPT()
  # contenuto = gpt.generate(prompt)
  # meta = Meta()
  # meta.fb_generate_post(contenuto)

  prompt = ("Racconta una storia basandoti su questra immagine, la storia deve includere elementi legati al"
            "cosmo e i chakra, di come l'uomo sia un tuttuno con l'universo e di come queste pietre riescano a"
            "donarti serenità e gratitudine. La storia è per un post Instagram e deve avere come call to action"
            "il commentare le proprie esperienze per generare interazioni")
  img_path = "./storage/bracciale.jpg"

  gpt = GPT()
  contenuto = gpt.generate(prompt, img_path)

  # meta = Meta()
  # meta.fb_generate_post(contenuto, img_path)

  meta = Meta()
  meta.ig_generate_post(contenuto, "https://.../bracciale.jpg")


if __name__ == "__main__":
  main()
