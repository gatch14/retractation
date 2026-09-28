<div class="retractation-notice retractation-notice--product">
  <p>
    {if $retractation_product_notice_text}
      {$retractation_product_notice_text nofilter}
    {elseif $retractation_product_eligible}
      {l s='In accordance with Article L.221-18 of the French Consumer Code, you have a right of withdrawal of 14 calendar days from receipt of this product.' mod='retractation2026'}<br>
      <a href="{$retractation_form_url|escape:'htmlall':'UTF-8'}">{l s='Withdrawal form' mod='retractation2026'}</a>
    {else}
      {l s='This product is excluded from the right of withdrawal in accordance with Article L.221-28 of the French Consumer Code.' mod='retractation2026'}
    {/if}
  </p>
</div>
