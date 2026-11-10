<?php
/**
 * Endpoint ajax del form: valida, verifica il captcha e inoltra a FaPer3 lato server.
 * URL: index.php?fc=module&module=faper3newsletter&controller=subscribe (o rewrite equivalente).
 * Risposta JSON: { success: bool, message: string }.
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class Faper3newsletterSubscribeModuleFrontController extends ModuleFrontController
{
    /** @var Faper3newsletter */
    public $module;

    public $ajax = true;

    public function postProcess()
    {
        list($status, $payload) = $this->handle();

        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        $this->ajaxRender(json_encode($payload));
        exit;
    }

    /**
     * @return array [int $httpStatus, array $payload]
     */
    private function handle()
    {
        if (Tools::strtoupper((string) $_SERVER['REQUEST_METHOD']) !== 'POST') {
            return [405, $this->fail($this->module->l('Richiesta non valida.', 'subscribe'))];
        }
        if (!$this->module->isConfigured()) {
            return [503, $this->fail($this->module->l('Servizio momentaneamente non disponibile, riprova più tardi.', 'subscribe'))];
        }
        if ((string) Tools::getValue('token') !== Tools::getToken(false)) {
            return [403, $this->fail($this->module->l('Sessione scaduta: ricarica la pagina e riprova.', 'subscribe'))];
        }

        $firstName = trim((string) Tools::getValue('firstname'));
        $lastName = trim((string) Tools::getValue('lastname'));
        $email = Tools::strtolower(trim((string) Tools::getValue('email')));

        if ($firstName === '' || $lastName === '' || $email === '') {
            return [422, $this->fail($this->module->l('Compila nome, cognome ed email.', 'subscribe'))];
        }
        if (!Validate::isName($firstName) || !Validate::isName($lastName)
            || Tools::strlen($firstName) > 255 || Tools::strlen($lastName) > 255) {
            return [422, $this->fail($this->module->l('Nome o cognome non validi.', 'subscribe'))];
        }
        if (!Validate::isEmail($email) || Tools::strlen($email) > 255) {
            return [422, $this->fail($this->module->l('Indirizzo email non valido.', 'subscribe'))];
        }
        if (!Tools::getValue('privacy')) {
            return [422, $this->fail($this->module->l('Per iscriverti devi accettare l\'informativa privacy.', 'subscribe'))];
        }

        $ip = (string) Tools::getRemoteAddr();
        if (!$this->module->createCaptcha()->verify((string) Tools::getValue('captcha_token'), $ip)) {
            return [422, $this->fail($this->module->l('Verifica anti-spam non superata: riprova.', 'subscribe'))];
        }

        $result = $this->module->sendContact([
            'email' => $email,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'consent_ip' => filter_var($ip, FILTER_VALIDATE_IP) ? $ip : null,
        ], Faper3newsletter::ORIGIN_FORM);

        // Messaggio identico per nuovo/già iscritto/ripristinato: non rivela chi è in lista.
        if (Faper3NewsletterClient::isSuccess($result['code'])) {
            return [200, ['success' => true, 'message' => $this->module->l('Iscrizione completata, grazie!', 'subscribe')]];
        }
        if ($result['code'] === 422) {
            return [422, $this->fail($this->module->l('Indirizzo email non valido o non accettato.', 'subscribe'))];
        }

        return [503, $this->fail($this->module->l('Servizio momentaneamente non disponibile, riprova più tardi.', 'subscribe'))];
    }

    /**
     * @param string $message
     *
     * @return array
     */
    private function fail($message)
    {
        return ['success' => false, 'message' => $message];
    }
}
