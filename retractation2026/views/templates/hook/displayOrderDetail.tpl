{if $retractation_eligible}
<div class="retractation-button-container box">
  <p class="retractation-deadline">
    {l s='Vous pouvez exercer votre droit de rétractation jusqu\'au' mod='retractation2026'} {$retractation_deadline|date_format:'%d/%m/%Y %H:%M'}
  </p>
  <a href="{$retractation_url}" class="btn btn-primary">
    {l s='Renoncer au contrat ici' mod='retractation2026'}
  </a>
</div>
{/if}
