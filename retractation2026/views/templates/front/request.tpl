{extends file='page.tpl'}

{block name='page_title'}
  {l s='Demande de rétractation' d='Modules.Retractation2026.Front'}
{/block}

{block name='page_content'}
  <div class="retractation-form-container">

    {if $show_lookup}
      <p class="retractation-lookup-intro">
        {l s='Pour exercer votre droit de rétractation, veuillez renseigner votre adresse email et la référence de votre commande.' d='Modules.Retractation2026.Front'}
      </p>

      <form method="post" action="{$link->getModuleLink('retractation2026', 'request')}">
        <div class="form-group">
          <label for="lookup-email">{l s='Adresse email' d='Modules.Retractation2026.Front'}</label>
          <input type="email" id="lookup-email" name="lookup_email" class="form-control" required placeholder="{l s='Email utilisé lors de la commande' d='Modules.Retractation2026.Front'}" />
        </div>

        <div class="form-group">
          <label for="lookup-reference">{l s='Référence de commande' d='Modules.Retractation2026.Front'}</label>
          <input type="text" id="lookup-reference" name="lookup_reference" class="form-control" required placeholder="{l s='Ex: ABCDEFGH' d='Modules.Retractation2026.Front'}" />
        </div>

        <div class="form-group">
          <button type="submit" name="submitLookup" class="btn btn-primary">
            {l s='Rechercher ma commande' d='Modules.Retractation2026.Front'}
          </button>
        </div>
      </form>

    {else}
      <p class="retractation-deadline-info">
        {l s='Date limite de rétractation :' d='Modules.Retractation2026.Front'} {$retractation_deadline|date_format:'%d/%m/%Y %H:%M'}
      </p>

      <form method="post" action="{$link->getModuleLink('retractation2026', 'request')}">
        <input type="hidden" name="id_order" value="{$order->id}" />
        <input type="hidden" name="retractation_token" value="{$retractation_token}" />
        {if $is_guest}
          <input type="hidden" name="guest_email" value="{$guest_email|escape:'htmlall':'UTF-8'}" />
          <input type="hidden" name="order_reference" value="{$order_reference|escape:'htmlall':'UTF-8'}" />
        {/if}

        <div class="form-group">
          <label>{l s='Prénom' d='Modules.Retractation2026.Front'}</label>
          <input type="text" class="form-control" value="{$customer_firstname|escape:'htmlall':'UTF-8'}" readonly />
        </div>

        <div class="form-group">
          <label>{l s='Nom' d='Modules.Retractation2026.Front'}</label>
          <input type="text" class="form-control" value="{$customer_lastname|escape:'htmlall':'UTF-8'}" readonly />
        </div>

        <div class="form-group">
          <label>{l s='Email' d='Modules.Retractation2026.Front'}</label>
          <input type="email" class="form-control" value="{$customer_email|escape:'htmlall':'UTF-8'}" readonly />
        </div>

        <div class="form-group">
          <label>{l s='Référence de commande' d='Modules.Retractation2026.Front'}</label>
          <input type="text" class="form-control" value="{$order_reference}" readonly />
        </div>

        <div class="form-group">
          <label for="retractation-reason">{l s='Motif (facultatif)' d='Modules.Retractation2026.Front'}</label>
          <textarea id="retractation-reason" name="reason" class="form-control" rows="4" placeholder="{l s='Indiquez le motif de votre rétractation si vous le souhaitez' d='Modules.Retractation2026.Front'}"></textarea>
        </div>

        <div class="form-group">
          <button type="submit" name="submitRetractation" class="btn btn-primary">
            {l s='Confirmer la rétractation' d='Modules.Retractation2026.Front'}
          </button>
        </div>
      </form>
    {/if}

  </div>
{/block}
