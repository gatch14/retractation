{extends file='page.tpl'}

{block name='page_title'}
  {l s='Confirmation de rétractation' mod='retractation2026'}
{/block}

{block name='page_content'}
  <div class="retractation-confirmation">
    <div class="alert alert-success">
      <h4>{l s='Votre demande de rétractation a été enregistrée avec succès.' mod='retractation2026'}</h4>
    </div>

    <div class="retractation-details">
      <p><strong>{l s='Référence de commande :' mod='retractation2026'}</strong> {$order_reference|escape:'htmlall':'UTF-8'}</p>
      <p><strong>{l s='Date de rétractation :' mod='retractation2026'}</strong> {$retractation_date}</p>
      <p><strong>{l s='Heure de rétractation :' mod='retractation2026'}</strong> {$retractation_time}</p>
    </div>

    <p class="retractation-email-notice">
      {l s='Un email de confirmation vous sera envoyé.' mod='retractation2026'}
    </p>

    {if $is_guest}
      <a href="{$link->getPageLink('guest-tracking')}" class="btn btn-primary">
        {l s='Retour au suivi de commande' mod='retractation2026'}
      </a>
    {else}
      <a href="{$link->getPageLink('history')}" class="btn btn-primary">
        {l s='Retour à mes commandes' mod='retractation2026'}
      </a>
    {/if}
  </div>
{/block}
