import os
import base64
import requests
import traceback
from dotenv import load_dotenv
from openai import OpenAI
import tiktoken

load_dotenv()

class GPT:
    def __init__(self, api_key = os.getenv("OPENAI_API_KEY"), debug = False):
        self.api_key = api_key
        self.debug = debug
        self.messages = []

    # Generazione del contenuto
    def generate(self, prompt, model = os.getenv("OPENAI_MODEL"), temperature = 1.0, img_path = None):
        try:
            headers = {
                "Authorization": f"Bearer {self.api_key}",
                "Content-Type": "application/json"
            }

            # Se è presente un'immagine, aggiungila al payload
            if img_path:
                self.set_role_img(prompt, img_path)
            else:
                self.set_role(
                    role="user",
                    content=prompt
                )

            if self.debug is True:
                print(self.messages)

            payload = {
                "model": model,
                "messages": self.messages,
                "temperature": temperature
            }

            response = requests.post(os.getenv("OPENAI_API_URL"), headers=headers, json=payload)

            if response.status_code == 200:
                response_data = response.json()

                # Calcola i token
                usage = response_data.get("usage", {})
                total_tokens = usage.get("total_tokens", 0)

                return response_data['choices'][0]['message']['content'], total_tokens
            else:
                return f"Error: {response.status_code}: {response.text}"

        except Exception as e:
            if self.debug == True:
                print(f"Errore con l'API di OpenAI: {e}")
            traceback.print_exc()
            return None

    def set_role(self, role, content):
        self.messages.append({
            "role": role,
            "content": content
        })

    def set_role_img(self, prompt, img_path):
        base64_image = self.image_to_base64(img_path)
        if not base64_image:
            return "Errore durante la conversione dell'immagine."
        self.set_role(
            role="user",
            content=[
                {
                    "type": "text",
                    "text": prompt
                },
                {
                    "type": "image_url",
                    "image_url": {"url": f"data:image/jpeg;base64,{base64_image}"}
                }
            ]
        )

    # Metodo per convertire un'immagine in Base64
    def image_to_base64(self, img_path):
        try:
            with open(img_path, "rb") as img_file:
                return base64.b64encode(img_file.read()).decode("utf-8")
        except Exception as e:
            print(f"Errore durante la conversione dell'immagine in base64: {e}")
            traceback.print_exc()
            return None

    def calculate_tokens(self, messages, model):
        try:
            # Usa la libreria tiktoken per calcolare i token
            encoding = tiktoken.encoding_for_model(model)
            num_tokens = 0
            for message in messages:
                for key, value in message.items():
                    num_tokens += len(encoding.encode(value))
            return num_tokens
        except Exception as e:
            print(f"Errore durante il calcolo dei token: {e}")
            traceback.print_exc()
            return 0
