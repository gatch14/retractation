{extends file='page.tpl'}

{block name='page_title'}
  {l s='Confirmation de rétractation' d='Modules.Retractation2026.Front'}
{/block}

{block name='page_content'}
  <div class="retractation-confirmation">
    <div class="alert alert-success">
      <h4>{l s='Votre demande de rétractation a été enregistrée avec succès.' d='Modules.Retractation2026.Front'}</h4>
    </div>

    <div class="retractation-details">
      <p><strong>{l s='Référence de commande :' d='Modules.Retractation2026.Front'}</strong> {$order_reference}</p>
      <p><strong>{l s='Date de rétractation :' d='Modules.Retractation2026.Front'}</strong> {$retractation_date}</p>
      <p><strong>{l s='Heure de rétractation :' d='Modules.Retractation2026.Front'}</strong> {$retractation_time}</p>
    </div>

    <p class="retractation-email-notice">
      {l s='Un email de confirmation vous sera envoyé.' d='Modules.Retractation2026.Front'}
    </p>

    {if $is_guest}
      <a href="{$link->getPageLink('guest-tracking')}" class="btn btn-primary">
        {l s='Retour au suivi de commande' d='Modules.Retractation2026.Front'}
      </a>
    {else}
      <a href="{$link->getPageLink('history')}" class="btn btn-primary">
        {l s='Retour à mes commandes' d='Modules.Retractation2026.Front'}
      </a>
    {/if}
  </div>
{/block}
