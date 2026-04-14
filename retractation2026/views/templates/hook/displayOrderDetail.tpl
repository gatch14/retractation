{if $retractation_eligible}
<div class="retractation-button-container box">
  <p class="retractation-deadline">
    {l s='Vous pouvez exercer votre droit de rétractation jusqu\'au' d='Modules.Retractation2026.Front'} {$retractation_deadline|date_format:'%d/%m/%Y %H:%M'}
  </p>
  <a href="{$retractation_url}" class="btn btn-primary">
    {l s='Renoncer au contrat ici' d='Modules.Retractation2026.Front'}
  </a>
</div>
{/if}
