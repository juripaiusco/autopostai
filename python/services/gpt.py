import os
import base64
import requests
import traceback
from dotenv import load_dotenv
from openai import OpenAI
import tiktoken

load_dotenv()

class GPT:
  def __init__(self, api_key = os.getenv("OPENAI_API_KEY")):
    self.api_key = api_key


  # Generazione del contenuto
  def generate(self, prompt, img_path = "", model = os.getenv("OPENAI_MODEL"), temperature = 1.0):

    if not prompt:
      print("Please enter a prompt.")
      return None

    # Inizializza l'encoder per il modello
    encoding = tiktoken.encoding_for_model(model)

    # Conta i token in entrata
    token_in_entrata = len(encoding.encode(prompt))

    # Invia il prompt ad OpenAI
    if not img_path:
      output = self.generate_txt(prompt, model, temperature)
    else:
      output = self.generate_textByImg(prompt, img_path, model, temperature)

    # Conta i token in uscita
    token_in_uscita = len(encoding.encode(output))

    # Totale dei token
    token_totali = token_in_entrata + token_in_uscita

    return output, token_totali


  # Viene generato del contenuto di testo in base al prompt
  def generate_txt(self, prompt, model, temperature):
    try:
      # Inizializza il client OpenAI
      client = OpenAI(
        api_key=os.environ.get("OPENAI_API_KEY", self.api_key),
      )

      # Chiamata API per generare il completamento
      chat_completion = client.chat.completions.create(
        messages=[
          {"role": "user", "content": prompt}
        ],
        model=model,
        temperature=temperature,
      )

      # Restituisci il contenuto del messaggio
      return chat_completion.choices[0].message.content

    except Exception as e:
      print(f"Errore con l'API di OpenAI: {e}")
      traceback.print_exc()
      return None

  # Caricamento dell'immagine con un prompt
  def generate_textByImg(self, prompt, img_path, model, temperature):
    headers = {
      "Authorization": f"Bearer {self.api_key}",
      "Content-Type": "application/json"
    }

    payload = {
      "model": f"{model}",
      "messages": [
        {
          "role": "user",
          "content": [
            {"type": "text",
             "text": f"{prompt}",},
            {"type": "image_url", "image_url": {"url": f"data:image/jpeg;base64,{self.image_to_base64(img_path)}"}}
          ]
        }
      ],
      "max_tokens": 500
    }

    response = requests.post("https://api.openai.com/v1/chat/completions", headers=headers, json=payload)

    if response.status_code == 200:
      response_data = response.json()
      generated_text = response_data['choices'][0]['message']['content']
      return generated_text
    else:
      return f"Error: {response.status_code}: {response.text}"


  # Metodo per convertire un'immagine in Base64
  def image_to_base64(self, image_path):
    with open(image_path, "rb") as img_file:
      return base64.b64encode(img_file.read()).decode("utf-8")
