<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Con APP_LOCALE=it e senza lang/it l'utente vedeva le chiavi grezze dei
 * messaggi (es. "validation.image") invece del testo.
 */
class ItalianValidationMessagesTest extends TestCase
{
    public function test_validation_messages_are_translated_in_italian(): void
    {
        app()->setLocale('it');

        $errors = validator(
            ['email' => 'non-una-email', 'images' => ['x']],
            ['email' => 'email', 'images.*' => 'image', 'title' => 'required'],
        )->errors();

        $this->assertSame('Il campo email deve essere un indirizzo email valido.', $errors->first('email'));
        $this->assertSame('Il file immagine deve essere un\'immagine.', $errors->first('images.0'));
        $this->assertSame('Il campo titolo è obbligatorio.', $errors->first('title'));
    }

    public function test_auth_messages_are_translated_in_italian(): void
    {
        app()->setLocale('it');

        $this->assertSame('Credenziali non valide.', __('auth.failed'));
        $this->assertSame('La password attuale non è corretta.', __('The provided password does not match your current password.'));
    }
}
