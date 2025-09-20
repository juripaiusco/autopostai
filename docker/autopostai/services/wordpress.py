import requests
from requests.auth import HTTPBasicAuth

class Wordpress:
    def __init__(self, wordpress_url, wordpress_username, wordpress_password):
        self.wordpress_url = wordpress_url
        self.wordpress_username = wordpress_username
        self.wordpress_password = wordpress_password

    def auth(self):
        if self.wordpress_url is not None:
            return HTTPBasicAuth(self.wordpress_username, self.wordpress_password)

    def headers(self, headers_img=None):
        headers = {}

        if headers_img is None:
            headers['Content-Type'] = "application/json"

        if headers_img is not None:
            headers.update(headers_img)

        headers['User-Agent'] = "FaPer3 (AutoPostAI)"

        return headers

    def send(self, title, content, img_path, cat_id):
        gallery_html = None
        img_wp_array = []

        # Upload immagine
        if isinstance(img_path, list):
            img_path_array = img_path
            for img_path in img_path_array:
                img_wp_array.append(self.upload_img(img_path=img_path))

            if len(img_wp_array) > 1:
                gallery_html = f"""
                                <!-- wp:gallery -->
                                <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/glightbox/dist/css/glightbox.min.css">
                                <figure class="wp-block-gallery has-nested-images columns-default is-cropped
                                wp-block-gallery-1 is-layout-flex wp-block-gallery-is-layout-flex">
                                """
                for img_wp in img_wp_array[1:]:  # dalla seconda immagine
                    gallery_html += f"""
                                    <figure class="wp-block-image size-large">

                                        <a href="{img_wp['url']}" class="glightbox" data-gallery="mygallery">

                                            <img decoding="async"
                                                alt=""
                                                class="wp-image-{img_wp['id']}"
                                                data-id="{img_wp['id']}"
                                                data-src="{img_wp['url']}"
                                                src="{img_wp['url']}">

                                        </a>

                                    </figure>
                                    """

                gallery_html += f"""
                                </figure>
                                <script src="https://cdn.jsdelivr.net/npm/glightbox/dist/js/glightbox.min.js"></script>
                                """
                gallery_html += """
                                <script>
                                document.addEventListener("DOMContentLoaded", function() {
                                    GLightbox({selector: ".glightbox"});
                                });
                                </script>
                                <!-- /wp:gallery -->
                                """

                content = gallery_html + "<br>" + content

        elif isinstance(img_path, str):
            img_wp_array.append(self.upload_img(img_path=img_path))

        data = {
            "title": title,
            "content": content,
            "status": "publish",
            "categories": cat_id if cat_id else []
        }

        if len(img_wp_array) > 0:
            data["featured_media"] = img_wp_array[0]['id']

        response = requests.post(
            f"{self.wordpress_url}/wp-json/wp/v2/posts",
            headers=self.headers(),
            auth=self.auth(),
            json=data
        )

        post_id = response.json().get("id")
        post_url = response.json().get("link")

        # # Dati per assegnare l'immagine come immagine in evidenza
        # data = {"featured_media": img_id}
        #
        # # Invia la richiesta PATCH per aggiornare il post
        # response = requests.post(
        #     f"{self.wordpress_url}/wp-json/wp/v2/posts/{post_id}",
        #     headers=self.headers(),
        #     auth=self.auth(),
        #     json=data
        # )

        return post_id, post_url, gallery_html

    def upload_img(self, img_path):
        # Aprire il file e inviare la richiesta
        with open(img_path, "rb") as img:
            files = {"file": img}
            response = requests.post(
                f"{self.wordpress_url}/wp-json/wp/v2/media",
                headers={
                    "Content-Disposition": f"attachment; filename={img_path}",
                    "User-Agent": "Mozilla/5.0 (AutoPostAI)"
                },
                auth=self.auth(),
                files=files
            )

        # Ottenere l'ID dell'immagine caricata
        image_id = response.json().get("id")
        image_url = response.json().get("source_url")

        return {
            "id": image_id,
            "url": image_url,
        }

    def update(self, post_id, title, content):
        # Qui bisogna leggere il contenuto del post e recuperare
        # tutto quello che c'è tra i tag <!-- wp:gallery --> e <!-- /wp:gallery -->

        data = {
            "title": title,
            "content": content,
            "status": "publish"
        }

        response = requests.post(
            f"{self.wordpress_url}/wp-json/wp/v2/posts/{post_id}",
            headers=self.headers(),
            auth=self.auth(),
            json=data
        )

        return response.json().get("id")

    def delete(self, post_id):
        response = requests.delete(
            f"{self.wordpress_url}/wp-json/wp/v2/posts/{post_id}",
            headers=self.headers(),
            auth=self.auth()
        )

        return response.json().get("id")
