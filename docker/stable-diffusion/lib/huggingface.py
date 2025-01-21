import os
import requests
import random
from datetime import datetime
import pytz
import traceback
from dotenv import load_dotenv

load_dotenv()

# Fuso orario locale (ad esempio, Europa/Roma)
LOCAL_TIMEZONE = pytz.timezone('Europe/Rome')

# Ottieni la data attuale
CURRENT_TIME = datetime.now(LOCAL_TIMEZONE).strftime('%Y%m%d%H%M%S')

class HuggingFace:
  def __init__(self, api_key = os.getenv("HUGGINGFACE_API_KEY"), url_model = os.getenv("HUGGINGFACE_URL_MODEL")):
    self.api_key = api_key
    self.url_model = url_model

  def stableDiffusion_generate_img(
      self,
      prompt = None,
      img_name = CURRENT_TIME,
      num_inference_steps = 40,
      guidance_scale = 5.0,
      w = 1024,
      h = 1024
  ):
    # Inserisci il tuo API Token
    headers = {
      "Authorization": f"Bearer {self.api_key}"
    }

    # Definisci il prompt
    data = {
      "inputs": prompt,
      "parameters": {
        "num_inference_steps": num_inference_steps,
        "guidance_scale": guidance_scale,
        "seed": random.randint(0, 2147483647),
        "width": w,
        "height": h
      }
    }

    try:
      # Fai la richiesta POST all'API
      response = requests.post(self.url_model, headers=headers, json=data)

      if response.status_code == 200:
        with open(f"./img/{img_name}.jpg", "wb") as f:
          f.write(response.content)

        return f"{img_name}.jpg"

      else:
        return f"Errore nella richiesta: {response.status_code}"

    except Exception as e:
      print(f"Errore con l'API di OpenAI: {e}")
      traceback.print_exc()
      return None

  def load_model(self):
    return None