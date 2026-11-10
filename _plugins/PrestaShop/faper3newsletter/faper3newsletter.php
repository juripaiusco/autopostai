<?php
/**
 * FaPer3 Newsletter — iscrizione alla newsletter FaPer3 da PrestaShop 1.7.
 *
 * - Form ajax (nome, cognome, email, privacy, captcha opzionale) nel footer o come widget.
 * - Sync opzionale dei clienti che attivano la newsletter in registrazione/account.
 * La chiamata all'API FaPer3 avviene sempre lato server (la chiave non arriva al browser).
 *
 * Compatibile PHP 7.1+ (PrestaShop 1.7.6 – 1.7.8).
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/classes/Faper3NewsletterClient.php';
require_once __DIR__ . '/classes/Faper3NewsletterCaptcha.php';

use PrestaShop\PrestaShop\Core\Module\WidgetInterface;

class Faper3newsletter extends Module implements WidgetInterface
{
    const ORIGIN_FORM = 'form';
    const ORIGIN_CUSTOMERS = 'customers';

    const SUBMIT_ACTION = 'submitFaper3newsletter';

    /** Chiavi Configuration semplici (non multilingua) con valore di default. */
    const CONFIG_DEFAULTS = [
        'F3N_API_URL' => 'https://faper3.it',
        'F3N_API_KEY' => '',
        'F3N_TAG_FORM' => 'prestashop',
        'F3N_SOURCE_FORM' => 'prestashop-newsletter',
        'F3N_PRIVACY_CMS' => 0,
        'F3N_CAPTCHA' => 'none',
        'F3N_CAPTCHA_SITE' => '',
        'F3N_CAPTCHA_SECRET' => '',
        'F3N_RECAPTCHA_SCORE' => '0.5',
        'F3N_SYNC_CUSTOMERS' => 0,
        'F3N_TAG_CUSTOMERS' => 'prestashop-clienti',
        'F3N_SOURCE_CUSTOMERS' => 'prestashop-clienti',
    ];

    /** Chiavi multilingua: [default it, default altre lingue]. */
    const CONFIG_LANG_DEFAULTS = [
        'F3N_TITLE' => [
            'Vuoi essere sempre informato sulle nostre iniziative?',
            'Want to stay up to date on our initiatives?',
        ],
        'F3N_SUBTITLE' => [
            'Iscriviti alla nostra newsletter.',
            'Subscribe to our newsletter.',
        ],
        'F3N_PRIVACY_TEXT' => [
            'Acconsento al trattamento dei miei dati per ricevere la newsletter.',
            'I agree to the processing of my data to receive the newsletter.',
        ],
    ];

    /** Campi segreti: se lasciati vuoti nel form di configurazione si mantiene il valore salvato. */
    const SECRET_KEYS = ['F3N_API_KEY', 'F3N_CAPTCHA_SECRET'];

    /** @var array<int,int> flag newsletter dei clienti prima dell'update, per id cliente */
    private static $newsletterBeforeUpdate = [];

    public function __construct()
    {
        $this->name = 'faper3newsletter';
        $this->tab = 'front_office_features';
        $this->version = '1.0.0';
        $this->author = 'FaPer3';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = ['min' => '1.7.6.0', 'max' => '1.7.99.99'];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('FaPer3 Newsletter');
        $this->description = $this->l('Iscrizione alla newsletter FaPer3 (nome, cognome, email) con form ajax e sincronizzazione clienti.');
        $this->confirmUninstall = $this->l('Disinstallare il modulo? La configurazione (API key compresa) verrà cancellata.');
    }

    /* ------------------------------------------------------------------ */
    /* Install / uninstall                                                 */
    /* ------------------------------------------------------------------ */

    public function install()
    {
        if (!parent::install()) {
            return false;
        }

        foreach (self::CONFIG_DEFAULTS as $key => $value) {
            Configuration::updateValue($key, $value);
        }
        foreach (self::CONFIG_LANG_DEFAULTS as $key => $defaults) {
            $values = [];
            foreach (Language::getLanguages(false) as $lang) {
                $values[(int) $lang['id_lang']] = $lang['iso_code'] === 'it' ? $defaults[0] : $defaults[1];
            }
            Configuration::updateValue($key, $values);
        }

        return $this->registerHook([
            'displayFooterBefore',
            'actionFrontControllerSetMedia',
            'actionCustomerAccountAdd',
            'actionObjectCustomerUpdateBefore',
            'actionObjectCustomerUpdateAfter',
        ]);
    }

    public function uninstall()
    {
        foreach (array_merge(array_keys(self::CONFIG_DEFAULTS), array_keys(self::CONFIG_LANG_DEFAULTS)) as $key) {
            Configuration::deleteByName($key);
        }

        return parent::uninstall();
    }

    /* ------------------------------------------------------------------ */
    /* API condivise (usate anche dal front controller)                    */
    /* ------------------------------------------------------------------ */

    /**
     * @return bool URL e API key impostati
     */
    public function isConfigured()
    {
        return trim((string) Configuration::get('F3N_API_URL')) !== ''
            && trim((string) Configuration::get('F3N_API_KEY')) !== '';
    }

    /**
     * @return Faper3NewsletterCaptcha
     */
    public function createCaptcha()
    {
        return new Faper3NewsletterCaptcha(
            (string) Configuration::get('F3N_CAPTCHA'),
            (string) Configuration::get('F3N_CAPTCHA_SECRET'),
            (float) Configuration::get('F3N_RECAPTCHA_SCORE')
        );
    }

    /**
     * Invia un contatto a FaPer3 con tag e consent_source dell'origine indicata.
     *
     * @param array $person email, first_name, last_name, consent_ip
     * @param string $origin self::ORIGIN_FORM | self::ORIGIN_CUSTOMERS
     *
     * @return array risultato di Faper3NewsletterClient::registerContact()
     */
    public function sendContact(array $person, $origin)
    {
        $isForm = $origin === self::ORIGIN_FORM;
        $payload = $person + [
            'tags' => Faper3NewsletterClient::parseTags(Configuration::get($isForm ? 'F3N_TAG_FORM' : 'F3N_TAG_CUSTOMERS')),
            'consent_source' => (string) Configuration::get($isForm ? 'F3N_SOURCE_FORM' : 'F3N_SOURCE_CUSTOMERS'),
        ];

        $client = new Faper3NewsletterClient(
            Configuration::get('F3N_API_URL'),
            Configuration::get('F3N_API_KEY'),
            // dal form il visitatore aspetta la risposta; per i clienti non deve rallentare la registrazione
            $isForm ? 10 : 5,
            'faper3newsletter-prestashop/' . $this->version
        );
        $result = $client->registerContact($payload);

        // 422 dal form = email rifiutata (sintassi/MX/suppression): errore dell'utente, non del sistema.
        if (!Faper3NewsletterClient::isSuccess($result['code']) && !($isForm && $result['code'] === 422)) {
            $this->logApiFailure($result, $origin);
        }

        return $result;
    }

    /**
     * @param array $result
     * @param string $origin
     */
    private function logApiFailure(array $result, $origin)
    {
        $detail = $result['error'];
        if ($detail === null && isset($result['body']['message'])) {
            $detail = (string) $result['body']['message'];
        }
        $message = sprintf(
            'FaPer3 Newsletter (%s): HTTP %d%s%s',
            $origin,
            $result['code'],
            $result['code'] === 401 ? ' - API key mancante o non valida' : '',
            $detail ? ' - ' . Tools::substr($detail, 0, 200) : ''
        );

        PrestaShopLogger::addLog($message, 3, null, 'Module', (int) $this->id, true);
    }

    /* ------------------------------------------------------------------ */
    /* Front office                                                        */
    /* ------------------------------------------------------------------ */

    public function hookActionFrontControllerSetMedia()
    {
        if (!$this->isConfigured()) {
            return;
        }

        $controller = $this->context->controller;
        $controller->registerStylesheet('f3n-style', 'modules/' . $this->name . '/views/css/faper3newsletter.css');
        $controller->registerJavascript('f3n-script', 'modules/' . $this->name . '/views/js/faper3newsletter.js', ['position' => 'bottom', 'priority' => 200]);

        $provider = (string) Configuration::get('F3N_CAPTCHA');
        $siteKey = (string) Configuration::get('F3N_CAPTCHA_SITE');
        if ($provider === Faper3NewsletterCaptcha::RECAPTCHA_V3) {
            $controller->registerJavascript(
                'f3n-recaptcha',
                'https://www.google.com/recaptcha/api.js?render=' . urlencode($siteKey),
                ['server' => 'remote', 'position' => 'bottom', 'priority' => 190, 'attributes' => 'async']
            );
        } elseif ($provider === Faper3NewsletterCaptcha::TURNSTILE) {
            $controller->registerJavascript(
                'f3n-turnstile',
                'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit',
                ['server' => 'remote', 'position' => 'bottom', 'priority' => 190, 'attributes' => 'async']
            );
        }

        Media::addJsDef(['faper3newsletter' => [
            'captcha' => $provider,
            'siteKey' => $siteKey,
            'action' => Faper3NewsletterCaptcha::ACTION,
            'messages' => [
                'required' => $this->l('Compila nome, cognome ed email.'),
                'privacy' => $this->l('Per iscriverti devi accettare l\'informativa privacy.'),
                'captcha' => $this->l('Verifica anti-spam non ancora pronta: attendi qualche secondo e riprova.'),
                'network' => $this->l('Servizio momentaneamente non disponibile, riprova più tardi.'),
            ],
        ]]);
    }

    public function renderWidget($hookName, array $configuration)
    {
        if (!$this->isConfigured()) {
            return '';
        }
        $this->smarty->assign($this->getWidgetVariables($hookName, $configuration));

        return $this->fetch('module:' . $this->name . '/views/templates/hook/faper3newsletter.tpl');
    }

    public function getWidgetVariables($hookName, array $configuration)
    {
        $idLang = (int) $this->context->language->id;
        $cmsId = (int) Configuration::get('F3N_PRIVACY_CMS');

        return ['f3n' => [
            'action' => $this->context->link->getModuleLink($this->name, 'subscribe', [], true),
            'token' => Tools::getToken(false),
            'title' => (string) Configuration::get('F3N_TITLE', $idLang),
            'subtitle' => (string) Configuration::get('F3N_SUBTITLE', $idLang),
            'privacy_text' => (string) Configuration::get('F3N_PRIVACY_TEXT', $idLang),
            'privacy_url' => $cmsId > 0 ? $this->context->link->getCMSLink($cmsId) : '',
            'captcha' => (string) Configuration::get('F3N_CAPTCHA'),
            'hook' => (string) $hookName,
        ]];
    }

    /* ------------------------------------------------------------------ */
    /* Sync clienti                                                        */
    /* ------------------------------------------------------------------ */

    public function hookActionCustomerAccountAdd($params)
    {
        if (!empty($params['newCustomer']) && $params['newCustomer'] instanceof Customer
            && (int) $params['newCustomer']->newsletter === 1) {
            $this->syncCustomer($params['newCustomer']);
        }
    }

    public function hookActionObjectCustomerUpdateBefore($params)
    {
        if (!$this->customerSyncEnabled() || empty($params['object']) || !$params['object'] instanceof Customer) {
            return;
        }
        $id = (int) $params['object']->id;
        if ($id > 0) {
            self::$newsletterBeforeUpdate[$id] = (int) Db::getInstance()->getValue(
                'SELECT `newsletter` FROM `' . _DB_PREFIX_ . 'customer` WHERE `id_customer` = ' . $id
            );
        }
    }

    public function hookActionObjectCustomerUpdateAfter($params)
    {
        if (empty($params['object']) || !$params['object'] instanceof Customer) {
            return;
        }
        $customer = $params['object'];
        $id = (int) $customer->id;
        if (!isset(self::$newsletterBeforeUpdate[$id])) {
            return;
        }
        $before = self::$newsletterBeforeUpdate[$id];
        unset(self::$newsletterBeforeUpdate[$id]);

        // Solo attivazione 0 → 1: la disiscrizione non è prevista dall'API FaPer3.
        if ($before === 0 && (int) $customer->newsletter === 1) {
            $this->syncCustomer($customer);
        }
    }

    /**
     * @return bool
     */
    private function customerSyncEnabled()
    {
        return (bool) Configuration::get('F3N_SYNC_CUSTOMERS') && $this->isConfigured();
    }

    /**
     * Mai bloccante: un errore verso FaPer3 non deve impedire registrazione/salvataggio del cliente.
     */
    private function syncCustomer(Customer $customer)
    {
        if (!$this->customerSyncEnabled()) {
            return;
        }

        try {
            $ip = (string) $customer->ip_registration_newsletter;
            if (!filter_var($ip, FILTER_VALIDATE_IP)) {
                // Dal back office l'IP corrente è quello dell'impiegato, non prova il consenso del cliente.
                $ip = defined('_PS_ADMIN_DIR_') ? '' : (string) Tools::getRemoteAddr();
            }

            $this->sendContact([
                'email' => (string) $customer->email,
                'first_name' => (string) $customer->firstname,
                'last_name' => (string) $customer->lastname,
                'consent_ip' => filter_var($ip, FILTER_VALIDATE_IP) ? $ip : null,
            ], self::ORIGIN_CUSTOMERS);
        } catch (\Throwable $e) {
            PrestaShopLogger::addLog('FaPer3 Newsletter (customers): ' . $e->getMessage(), 3, null, 'Module', (int) $this->id, true);
        }
    }

    /* ------------------------------------------------------------------ */
    /* Back office: configurazione                                         */
    /* ------------------------------------------------------------------ */

    public function getContent()
    {
        $output = '';
        if (Tools::isSubmit(self::SUBMIT_ACTION)) {
            $errors = $this->validateConfig();
            if ($errors) {
                $output .= $this->displayError(implode('<br>', $errors));
            } else {
                $this->saveConfig();
                $output .= $this->displayConfirmation($this->l('Impostazioni salvate.'));
            }
        }

        return $output . $this->renderConfigForm();
    }

    /**
     * @return string[]
     */
    private function validateConfig()
    {
        $errors = [];

        $url = trim((string) Tools::getValue('F3N_API_URL'));
        if (!preg_match('#^https?://#i', $url) || !Validate::isAbsoluteUrl($url)) {
            $errors[] = $this->l('URL API FaPer3 non valido (es. https://faper3.it).');
        }
        if (trim((string) Tools::getValue('F3N_API_KEY')) === '' && (string) Configuration::get('F3N_API_KEY') === '') {
            $errors[] = $this->l('Inserisci la API key FaPer3.');
        }

        $provider = (string) Tools::getValue('F3N_CAPTCHA');
        if (!in_array($provider, Faper3NewsletterCaptcha::providers(), true)) {
            $errors[] = $this->l('Provider captcha non valido.');
        } elseif ($provider !== Faper3NewsletterCaptcha::NONE) {
            if (trim((string) Tools::getValue('F3N_CAPTCHA_SITE')) === '') {
                $errors[] = $this->l('Inserisci la site key del captcha.');
            }
            if (trim((string) Tools::getValue('F3N_CAPTCHA_SECRET')) === '' && (string) Configuration::get('F3N_CAPTCHA_SECRET') === '') {
                $errors[] = $this->l('Inserisci la secret key del captcha.');
            }
        }

        $score = str_replace(',', '.', (string) Tools::getValue('F3N_RECAPTCHA_SCORE'));
        if (!is_numeric($score) || (float) $score < 0 || (float) $score > 1) {
            $errors[] = $this->l('La soglia reCAPTCHA deve essere un numero tra 0 e 1.');
        }

        return $errors;
    }

    private function saveConfig()
    {
        foreach (array_keys(self::CONFIG_DEFAULTS) as $key) {
            $value = trim((string) Tools::getValue($key));
            if (in_array($key, self::SECRET_KEYS, true) && $value === '') {
                continue; // campo segreto lasciato vuoto = mantieni quello salvato
            }
            if ($key === 'F3N_API_URL') {
                $value = rtrim($value, '/');
            } elseif ($key === 'F3N_RECAPTCHA_SCORE') {
                $value = (string) (float) str_replace(',', '.', $value);
            } elseif ($key === 'F3N_PRIVACY_CMS' || $key === 'F3N_SYNC_CUSTOMERS') {
                $value = (int) $value;
            }
            Configuration::updateValue($key, $value);
        }

        foreach (array_keys(self::CONFIG_LANG_DEFAULTS) as $key) {
            $values = [];
            foreach (Language::getLanguages(false) as $lang) {
                $values[(int) $lang['id_lang']] = trim((string) Tools::getValue($key . '_' . (int) $lang['id_lang']));
            }
            Configuration::updateValue($key, $values);
        }
    }

    /**
     * @return string
     */
    private function renderConfigForm()
    {
        $idLang = (int) $this->context->language->id;

        $cmsOptions = [['id' => 0, 'name' => $this->l('— Nessun link —')]];
        foreach (CMS::listCms($idLang) as $cms) {
            $cmsOptions[] = ['id' => (int) $cms['id_cms'], 'name' => $cms['meta_title']];
        }

        $savedKey = (string) Configuration::get('F3N_API_KEY');
        $keyDesc = $savedKey !== ''
            ? sprintf($this->l('Chiave salvata (termina con …%s). Lascia vuoto per mantenerla.'), Tools::substr($savedKey, -4))
            : $this->l('Da FaPer3: Account → Newsletter → API contatti (account con provider SMTP custom).');

        $hasSecret = (string) Configuration::get('F3N_CAPTCHA_SECRET') !== '';
        $switch = [
            ['id' => 'on', 'value' => 1, 'label' => $this->l('Sì')],
            ['id' => 'off', 'value' => 0, 'label' => $this->l('No')],
        ];
        $submit = ['title' => $this->l('Salva')];

        $forms = [
            ['form' => [
                'legend' => ['title' => $this->l('Connessione FaPer3'), 'icon' => 'icon-cogs'],
                'input' => [
                    ['type' => 'text', 'name' => 'F3N_API_URL', 'label' => $this->l('URL FaPer3'), 'required' => true,
                        'desc' => $this->l('Indirizzo base, senza /api (es. https://faper3.it).')],
                    ['type' => 'password', 'name' => 'F3N_API_KEY', 'label' => $this->l('API key'), 'required' => true, 'desc' => $keyDesc],
                    ['type' => 'text', 'name' => 'F3N_TAG_FORM', 'label' => $this->l('Tag iscritti dal form'),
                        'desc' => $this->l('Uno o più tag separati da virgola, assegnati al contatto in FaPer3.')],
                    ['type' => 'text', 'name' => 'F3N_SOURCE_FORM', 'label' => $this->l('Origine consenso (form)'),
                        'desc' => $this->l('Salvata in FaPer3 come prova di provenienza del consenso.')],
                ],
                'submit' => $submit,
            ]],
            ['form' => [
                'legend' => ['title' => $this->l('Testi e privacy'), 'icon' => 'icon-pencil'],
                'input' => [
                    ['type' => 'text', 'name' => 'F3N_TITLE', 'label' => $this->l('Titolo'), 'lang' => true],
                    ['type' => 'text', 'name' => 'F3N_SUBTITLE', 'label' => $this->l('Sottotitolo'), 'lang' => true],
                    ['type' => 'textarea', 'name' => 'F3N_PRIVACY_TEXT', 'label' => $this->l('Testo checkbox privacy'), 'lang' => true],
                    ['type' => 'select', 'name' => 'F3N_PRIVACY_CMS', 'label' => $this->l('Pagina informativa privacy'),
                        'options' => ['query' => $cmsOptions, 'id' => 'id', 'name' => 'name']],
                ],
                'submit' => $submit,
            ]],
            ['form' => [
                'legend' => ['title' => $this->l('Anti-spam'), 'icon' => 'icon-shield'],
                'input' => [
                    ['type' => 'select', 'name' => 'F3N_CAPTCHA', 'label' => $this->l('Captcha'),
                        'options' => ['query' => [
                            ['id' => Faper3NewsletterCaptcha::NONE, 'name' => $this->l('Nessuno')],
                            ['id' => Faper3NewsletterCaptcha::RECAPTCHA_V3, 'name' => 'Google reCAPTCHA v3'],
                            ['id' => Faper3NewsletterCaptcha::TURNSTILE, 'name' => 'Cloudflare Turnstile'],
                        ], 'id' => 'id', 'name' => 'name']],
                    ['type' => 'text', 'name' => 'F3N_CAPTCHA_SITE', 'label' => $this->l('Site key')],
                    ['type' => 'password', 'name' => 'F3N_CAPTCHA_SECRET', 'label' => $this->l('Secret key'),
                        'desc' => $hasSecret ? $this->l('Secret salvata. Lascia vuoto per mantenerla.') : ''],
                    ['type' => 'text', 'name' => 'F3N_RECAPTCHA_SCORE', 'label' => $this->l('Soglia reCAPTCHA v3'), 'class' => 'fixed-width-sm',
                        'desc' => $this->l('Da 0 a 1: sotto questo punteggio l\'iscrizione è rifiutata (consigliato 0.5).')],
                ],
                'submit' => $submit,
            ]],
            ['form' => [
                'legend' => ['title' => $this->l('Clienti del negozio'), 'icon' => 'icon-user'],
                'input' => [
                    ['type' => 'switch', 'name' => 'F3N_SYNC_CUSTOMERS', 'label' => $this->l('Sincronizza clienti'), 'is_bool' => true, 'values' => $switch,
                        'desc' => $this->l('Invia a FaPer3 i clienti che attivano la newsletter in registrazione o dal proprio account.')],
                    ['type' => 'text', 'name' => 'F3N_TAG_CUSTOMERS', 'label' => $this->l('Tag clienti')],
                    ['type' => 'text', 'name' => 'F3N_SOURCE_CUSTOMERS', 'label' => $this->l('Origine consenso (clienti)')],
                ],
                'submit' => $submit,
            ]],
        ];

        $values = [];
        foreach (array_keys(self::CONFIG_DEFAULTS) as $key) {
            $values[$key] = in_array($key, self::SECRET_KEYS, true) ? '' : Tools::getValue($key, Configuration::get($key));
        }
        foreach (array_keys(self::CONFIG_LANG_DEFAULTS) as $key) {
            $values[$key] = Configuration::getConfigInMultipleLangs($key);
        }

        $helper = new HelperForm();
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->default_form_language = (int) Configuration::get('PS_LANG_DEFAULT');
        $helper->allow_employee_form_lang = (int) Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG');
        $helper->submit_action = self::SUBMIT_ACTION;
        $helper->tpl_vars = [
            'fields_value' => $values,
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $idLang,
        ];

        return $helper->generateForm($forms);
    }
}
