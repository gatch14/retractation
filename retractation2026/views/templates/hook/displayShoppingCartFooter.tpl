<div class="retractation-notice retractation-notice--cart">
  <p>
    {if $retractation_cart_notice_text}
      {$retractation_cart_notice_text nofilter}
    {else}
      {l s='In accordance with applicable consumer protection law, you have a right of withdrawal that you may exercise within the legal deadline after receiving your order.' mod='retractation2026'}<br>
      {if $customer.is_logged}
        {l s='You can submit your withdrawal request from the "My retractations" section of your customer account.' mod='retractation2026'}
      {else}
        {l s='You can submit your withdrawal request from our online withdrawal form.' mod='retractation2026'}
      {/if}
    {/if}
  </p>
</div>
