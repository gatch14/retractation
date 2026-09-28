<div class="retractation-notice retractation-notice--cart">
  <p>
    {if $retractation_cart_notice_text}
      {$retractation_cart_notice_text nofilter}
    {else}
      {l s='In accordance with Article L.221-18 of the French Consumer Code, you have a right of withdrawal of 14 calendar days from receipt of your order. If the deadline expires on a Saturday, Sunday or public holiday, it is extended to the next working day.' d='Modules.Retractation2026.Shop'}<br>
      <a href="{$retractation_form_url|escape:'htmlall':'UTF-8'}">{l s='Withdrawal form' d='Modules.Retractation2026.Shop'}</a>
    {/if}
  </p>
</div>
