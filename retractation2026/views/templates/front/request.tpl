{extends file='page.tpl'}

{block name='page_title'}
  {l s='Demande de rétractation' mod='retractation2026'}
{/block}

{block name='page_content'}
  <div class="retractation-form-container">

    {if isset($show_lookup) && $show_lookup}
      <div class="card">
        <div class="card-body">
          <p class="card-text mb-3">
            {l s='Pour exercer votre droit de rétractation, veuillez renseigner votre adresse email et la référence de votre commande.' mod='retractation2026'}
          </p>

          <form method="post" action="{$link->getModuleLink('retractation2026', 'request')}">
            <div class="form-group row">
              <label for="lookup-email" class="col-md-4 col-form-label">{l s='Adresse email' mod='retractation2026'}</label>
              <div class="col-md-8">
                <input type="email" id="lookup-email" name="lookup_email" class="form-control" required placeholder="{l s='Email utilisé lors de la commande' mod='retractation2026'}" />
              </div>
            </div>

            <div class="form-group row">
              <label for="lookup-reference" class="col-md-4 col-form-label">{l s='Référence de commande' mod='retractation2026'}</label>
              <div class="col-md-8">
                <input type="text" id="lookup-reference" name="lookup_reference" class="form-control" required placeholder="{l s='Ex: ABCDEFGH' mod='retractation2026'}" />
              </div>
            </div>

            <div class="form-group row">
              <div class="col-md-8 offset-md-4">
                <button type="submit" name="submitLookup" class="btn btn-primary">
                  {l s='Rechercher ma commande' mod='retractation2026'}
                </button>
              </div>
            </div>
          </form>
        </div>
      </div>

    {else}
      <div class="alert alert-info">
        <i class="material-icons">&#xE8B5;</i>
        {l s='Date limite de rétractation :' mod='retractation2026'} <strong>{$retractation_deadline|date_format:'%d/%m/%Y %H:%M'}</strong>
      </div>

      <div class="card">
        <div class="card-body">
          <form method="post" action="{$link->getModuleLink('retractation2026', 'request')}">
            <input type="hidden" name="id_order" value="{$order->id}" />
            <input type="hidden" name="retractation_token" value="{$retractation_token}" />
            {if $is_guest}
              <input type="hidden" name="guest_email" value="{$guest_email|escape:'htmlall':'UTF-8'}" />
              <input type="hidden" name="order_reference" value="{$order_reference|escape:'htmlall':'UTF-8'}" />
            {/if}

            <div class="form-group row">
              <label class="col-md-4 col-form-label">{l s='Prénom' mod='retractation2026'}</label>
              <div class="col-md-8">
                <input type="text" class="form-control" value="{$customer_firstname|escape:'htmlall':'UTF-8'}" readonly />
              </div>
            </div>

            <div class="form-group row">
              <label class="col-md-4 col-form-label">{l s='Nom' mod='retractation2026'}</label>
              <div class="col-md-8">
                <input type="text" class="form-control" value="{$customer_lastname|escape:'htmlall':'UTF-8'}" readonly />
              </div>
            </div>

            <div class="form-group row">
              <label class="col-md-4 col-form-label">{l s='Email' mod='retractation2026'}</label>
              <div class="col-md-8">
                <input type="email" class="form-control" value="{$customer_email|escape:'htmlall':'UTF-8'}" readonly />
              </div>
            </div>

            <div class="form-group row">
              <label class="col-md-4 col-form-label">{l s='Référence de commande' mod='retractation2026'}</label>
              <div class="col-md-8">
                <input type="text" class="form-control" value="{$order_reference}" readonly />
              </div>
            </div>

            <div class="form-group row">
              <label for="retractation-reason" class="col-md-4 col-form-label">{l s='Motif (facultatif)' mod='retractation2026'}</label>
              <div class="col-md-8">
                <textarea id="retractation-reason" name="reason" class="form-control" rows="3" placeholder="{l s='Indiquez le motif de votre rétractation si vous le souhaitez' mod='retractation2026'}"></textarea>
              </div>
            </div>

            <div class="form-group row">
              <div class="col-md-8 offset-md-4">
                <button type="submit" name="submitRetractation" class="btn btn-primary">
                  {l s='Confirmer la rétractation' mod='retractation2026'}
                </button>
              </div>
            </div>
          </form>
        </div>
      </div>
    {/if}

  </div>
{/block}
