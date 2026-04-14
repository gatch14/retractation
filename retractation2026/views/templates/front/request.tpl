{extends file='page.tpl'}

{block name='page_title'}
  {l s='Demande de rétractation' d='Modules.Retractation2026.Front'}
{/block}

{block name='page_content'}
  <div class="retractation-form-container">
    <p class="retractation-deadline-info">
      {l s='Date limite de rétractation :' d='Modules.Retractation2026.Front'} {$retractation_deadline|date_format:'%d/%m/%Y %H:%M'}
    </p>

    <form method="post" action="{$link->getModuleLink('retractation2026', 'request')}">
      <input type="hidden" name="id_order" value="{$order->id}" />
      <input type="hidden" name="token" value="{$token}" />

      <div class="form-group">
        <label>{l s='Prénom' d='Modules.Retractation2026.Front'}</label>
        <input type="text" class="form-control" value="{$customer->firstname}" readonly />
      </div>

      <div class="form-group">
        <label>{l s='Nom' d='Modules.Retractation2026.Front'}</label>
        <input type="text" class="form-control" value="{$customer->lastname}" readonly />
      </div>

      <div class="form-group">
        <label>{l s='Email' d='Modules.Retractation2026.Front'}</label>
        <input type="email" class="form-control" value="{$customer->email}" readonly />
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
  </div>
{/block}
