import os
import requests

class Meta:
  def __init__(
      self,
      page_id = os.getenv("META_PAGE_ID"),
      user_access_token = os.getenv("META_USER_ACCESS_TOKEN")
  ):

    self.META_API_BASE_URL = os.getenv("META_API_BASE_URL")
    self.META_PAGE_ID = page_id
    self.META_USER_ACCESS_TOKEN = user_access_token


  # Recupero l'accesso token della pagina
  def fb_page_access_token(self):

    url = f"{self.META_API_BASE_URL}/me/accounts"
    params = {"access_token": self.META_USER_ACCESS_TOKEN}

    response = requests.get(url, params=params)

    if response.status_code == 200:

      pagine = response.json().get("data", [])
      return next((pagina["access_token"] for pagina in pagine if pagina["id"] == self.META_PAGE_ID), None)

    else:
      print("Errore nel recupero delle pagine.")
      print("Codice di stato:", response.status_code)
      print("Errore:", response.json())
      return []

    fb_page_access_token = 0

    return fb_page_access_token


  # Viene creato un post sulla pagina Facebok
  def fb_generate_post(self, msg, img_path = ""):

    if not img_path:
      url = f"{self.META_API_BASE_URL}/{self.META_PAGE_ID}/feed"
    else:
      url = f"{self.META_API_BASE_URL}/{self.META_PAGE_ID}/photos"
      files = {'source': open(img_path, 'rb')}

    payload = {
      "message": msg,
      "access_token": self.fb_page_access_token(),
    }

    if not img_path:
      response = requests.post(url, data=payload)
    else:
      response = requests.post(url, files=files, data=payload)

    if response.status_code == 200:
      print("Post pubblicato con successo!")
      print("Risposta API:", response.json())
    else:
      print("Errore nella pubblicazione del post.")
      print("Codice di stato:", response.status_code)
      print("Errore:", response.json())


  # Recupero l'ID dell'account Instagram collegato alla pagina Facebook
  def ig_get_instagram_account_id(self):

    url = f"{self.META_API_BASE_URL}/{self.META_PAGE_ID}"
    params = {
      "fields": "instagram_business_account",
      "access_token": self.META_USER_ACCESS_TOKEN,
    }

    response = requests.get(url, params=params)

    if response.status_code == 200:
      instagram_business_account = (response.json()
                                    .get("instagram_business_account", [])
                                    .get("id", []))
      return instagram_business_account

    else:
      print("Errore nel recupero delle pagine.")
      print("Codice di stato:", response.status_code)
      print("Errore:", response.json())
      return []


  # Creazione del post
  def ig_generate_post(self, msg, img_url=""):

    url = f"{self.META_API_BASE_URL}/{self.ig_get_instagram_account_id()}/media"
    payload = {
      "image_url": img_url,
      "caption": msg,
      "access_token": self.fb_page_access_token(),
    }

    response = requests.post(url, data=payload)
    if response.status_code == 200:
      print("Caricamento riuscito:", response.json())
      post_id = response.json().get("id")
      self.ig_pubblicate_post(post_id)
    else:
      print("Errore nel caricamento dell'immagine:", response.json())

  # Pubblicazione del post
  def ig_pubblicate_post(self, id):

    url = f"{self.META_API_BASE_URL}/{self.ig_get_instagram_account_id()}/media_publish"
    payload = {
      "creation_id": id,
      "access_token": self.fb_page_access_token(),
    }

    response = requests.post(url, data=payload)
    if response.status_code == 200:
      print("Pubblicazione riuscita:", response.json())
    else:
      print("Errore nella pubblicazione:", response.json())
