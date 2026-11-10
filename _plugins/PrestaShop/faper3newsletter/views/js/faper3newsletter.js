/**
 * FaPer3 Newsletter — invio ajax del form di iscrizione.
 * Config da Media::addJsDef: window.faper3newsletter = { captcha, siteKey, action, messages }.
 * ES5 senza build: gira così com'è su qualsiasi tema PrestaShop 1.7.
 */
(function () {
  'use strict';

  var cfg = window.faper3newsletter || {};
  var messages = cfg.messages || {};

  function showMessage(form, text, ok) {
    var box = form.querySelector('.f3n-message');
    if (!box) {
      return;
    }
    box.textContent = text || '';
    box.hidden = !text;
    box.classList.toggle('f3n-message--success', !!ok);
    box.classList.toggle('f3n-message--error', !ok);
  }

  /* ---- Cloudflare Turnstile (render esplicito, lo script è caricato async) ---- */

  function renderTurnstile(form) {
    var el = form.querySelector('.f3n-turnstile');
    if (!el || el.getAttribute('data-widget-id')) {
      return;
    }
    var id = window.turnstile.render(el, {
      sitekey: cfg.siteKey,
      action: cfg.action,
      theme: 'auto'
    });
    el.setAttribute('data-widget-id', id);
  }

  function initTurnstile(forms, attempt) {
    if (window.turnstile && typeof window.turnstile.render === 'function') {
      forms.forEach(renderTurnstile);
      return;
    }
    if (attempt < 100) { // ~20 secondi
      setTimeout(function () { initTurnstile(forms, attempt + 1); }, 200);
    }
  }

  function turnstileId(form) {
    var el = form.querySelector('.f3n-turnstile');
    return el ? el.getAttribute('data-widget-id') : null;
  }

  function resetCaptcha(form) {
    var id = turnstileId(form);
    if (cfg.captcha === 'turnstile' && id && window.turnstile) {
      window.turnstile.reset(id);
    }
  }

  /* ---- Token captcha al momento dell'invio ---- */

  function captchaToken(form) {
    if (cfg.captcha === 'recaptcha_v3') {
      return new Promise(function (resolve, reject) {
        if (!window.grecaptcha || typeof window.grecaptcha.ready !== 'function') {
          reject(new Error('captcha'));
          return;
        }
        window.grecaptcha.ready(function () {
          window.grecaptcha.execute(cfg.siteKey, { action: cfg.action }).then(resolve, reject);
        });
      });
    }
    if (cfg.captcha === 'turnstile') {
      var id = turnstileId(form);
      var token = id && window.turnstile ? window.turnstile.getResponse(id) : '';
      return token ? Promise.resolve(token) : Promise.reject(new Error('captcha'));
    }
    return Promise.resolve('');
  }

  /* ---- Invio ---- */

  function onSubmit(event) {
    var form = event.currentTarget;
    event.preventDefault();
    if (form.getAttribute('data-busy') === '1') {
      return;
    }

    var value = function (name) { return (form.elements[name].value || '').trim(); };
    if (!value('firstname') || !value('lastname') || !value('email')) {
      showMessage(form, messages.required, false);
      return;
    }
    if (!form.elements.privacy.checked) {
      showMessage(form, messages.privacy, false);
      return;
    }

    var button = form.querySelector('.f3n-submit');
    form.setAttribute('data-busy', '1');
    if (button) {
      button.disabled = true;
    }
    showMessage(form, '', true);

    captchaToken(form)
      .then(null, function () {
        throw new Error(messages.captcha);
      })
      .then(function (token) {
        var data = new FormData(form);
        data.append('captcha_token', token);
        data.append('ajax', '1');
        return fetch(form.action, {
          method: 'POST',
          body: data,
          credentials: 'same-origin',
          headers: { Accept: 'application/json' }
        });
      })
      .then(function (response) {
        return response.json().catch(function () {
          return { success: false, message: messages.network };
        });
      })
      .then(function (result) {
        showMessage(form, result.message || messages.network, !!result.success);
        if (result.success) {
          form.reset();
        }
        resetCaptcha(form); // i token captcha sono monouso
      })
      .catch(function (error) {
        var text = error && error.message === messages.captcha ? messages.captcha : messages.network;
        showMessage(form, text, false);
        resetCaptcha(form);
      })
      .then(function () {
        form.removeAttribute('data-busy');
        if (button) {
          button.disabled = false;
        }
      });
  }

  function init() {
    var forms = Array.prototype.slice.call(document.querySelectorAll('.f3n-form'));
    if (!forms.length) {
      return;
    }
    forms.forEach(function (form) {
      form.addEventListener('submit', onSubmit);
    });
    if (cfg.captcha === 'turnstile') {
      initTurnstile(forms, 0);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
