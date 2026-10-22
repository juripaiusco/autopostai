"""Password DB con caratteri speciali: l'URL di connessione non deve spezzarsi."""

from sqlalchemy.engine import make_url

from publisher import config
from publisher.db.engine import database_url


def test_special_characters_in_password_survive_the_url(monkeypatch):
    password = 'p@ss:w/rd#%?&"x'
    monkeypatch.setattr(config, "DB_USERNAME", "autopostaibeta")
    monkeypatch.setattr(config, "DB_PASSWORD", password)
    monkeypatch.setattr(config, "DB_HOST", "192.168.2.1")
    monkeypatch.setattr(config, "DB_PORT", 3306)
    monkeypatch.setattr(config, "DB_DATABASE", "faper3_beta")

    url = database_url()
    # Andata e ritorno dalla forma testuale: e' quella che si rompeva.
    parsed = make_url(url.render_as_string(hide_password=False))

    assert parsed.password == password
    assert parsed.username == "autopostaibeta"
    assert parsed.host == "192.168.2.1"
    assert parsed.port == 3306
    assert parsed.database == "faper3_beta"
    assert parsed.query["charset"] == "utf8mb4"
