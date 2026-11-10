{*
 * FaPer3 Newsletter — form di iscrizione.
 * Per personalizzarlo dal tema: themes/<tema>/modules/faper3newsletter/views/templates/hook/faper3newsletter.tpl
 *}
<div class="f3n-block f3n-hook-{$f3n.hook|escape:'html':'UTF-8'}{if $f3n.btn_color} f3n-custom-btn{/if}"
  {if $f3n.btn_color}style="--f3n-btn-bg: {$f3n.btn_color|escape:'html':'UTF-8'}; --f3n-btn-text: {$f3n.btn_text_color|escape:'html':'UTF-8'};"{/if}>
  <div class="f3n-inner">
    {if $f3n.title || $f3n.subtitle}
      <div class="f3n-intro">
        {if $f3n.title}<p class="f3n-title">{$f3n.title|escape:'html':'UTF-8'}</p>{/if}
        {if $f3n.subtitle}<p class="f3n-subtitle">{$f3n.subtitle|escape:'html':'UTF-8'}</p>{/if}
      </div>
    {/if}

    <form class="f3n-form" action="{$f3n.action|escape:'html':'UTF-8'}" method="post" novalidate>
      <input type="hidden" name="token" value="{$f3n.token|escape:'html':'UTF-8'}">

      <div class="f3n-row">
        <label class="f3n-field">
          <span class="f3n-sr">{l s='Nome' mod='faper3newsletter'}</span>
          <input class="form-control f3n-input" type="text" name="firstname" maxlength="255" autocomplete="given-name" required
                 placeholder="{l s='Nome' mod='faper3newsletter'}">
        </label>
        <label class="f3n-field">
          <span class="f3n-sr">{l s='Cognome' mod='faper3newsletter'}</span>
          <input class="form-control f3n-input" type="text" name="lastname" maxlength="255" autocomplete="family-name" required
                 placeholder="{l s='Cognome' mod='faper3newsletter'}">
        </label>
        <label class="f3n-field f3n-field-email">
          <span class="f3n-sr">{l s='Email' mod='faper3newsletter'}</span>
          <input class="form-control f3n-input" type="email" name="email" maxlength="255" autocomplete="email" required
                 placeholder="{l s='La tua email' mod='faper3newsletter'}">
        </label>
        <button type="submit" class="btn btn-primary f3n-submit">{l s='Iscriviti' mod='faper3newsletter'}</button>
      </div>

      <label class="f3n-privacy">
        <input type="checkbox" name="privacy" value="1" required>
        <span>
          {$f3n.privacy_text|escape:'html':'UTF-8'}
          {if $f3n.privacy_url}
            <a href="{$f3n.privacy_url|escape:'html':'UTF-8'}" target="_blank" rel="noopener">{l s='Informativa privacy' mod='faper3newsletter'}</a>
          {/if}
        </span>
      </label>

      {if $f3n.captcha == 'turnstile'}
        <div class="f3n-turnstile"></div>
      {/if}

      <p class="f3n-message" role="status" aria-live="polite" hidden></p>
    </form>
  </div>
</div>
