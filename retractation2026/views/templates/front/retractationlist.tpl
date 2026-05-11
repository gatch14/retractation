{extends file='page.tpl'}

{block name='page_title'}
  {l s='My retractation requests' mod='retractation2026'}
{/block}

{block name='page_content'}
  <div class="retractation-list">
    {if $retractations|count > 0}
      <div class="table-responsive">
        <table class="table table-striped">
          <thead>
            <tr>
              <th>{l s='Order Reference' mod='retractation2026'}</th>
              <th>{l s='Status' mod='retractation2026'}</th>
              <th>{l s='Retractation Date' mod='retractation2026'}</th>
              <th>{l s='Deadline Date' mod='retractation2026'}</th>
            </tr>
          </thead>
          <tbody>
            {foreach from=$retractations item=retractation}
              <tr>
                <td>{$retractation.order_reference|escape:'html':'UTF-8'}</td>
                <td>
                  {if $retractation.status == 'pending'}
                    <span class="badge badge-warning">{l s='Pending' mod='retractation2026'}</span>
                  {elseif $retractation.status == 'accepted'}
                    <span class="badge badge-success">{l s='Accepted' mod='retractation2026'}</span>
                  {elseif $retractation.status == 'rejected'}
                    <span class="badge badge-danger">{l s='Rejected' mod='retractation2026'}</span>
                  {elseif $retractation.status == 'cancelled'}
                    <span class="badge badge-secondary">{l s='Cancelled' mod='retractation2026'}</span>
                  {else}
                    <span class="badge badge-secondary">{$retractation.status|escape:'html':'UTF-8'}</span>
                  {/if}
                </td>
                <td>{$retractation.retractation_date|date_format:'%d/%m/%Y'}</td>
                <td>{$retractation.deadline_date|date_format:'%d/%m/%Y'}</td>
              </tr>
            {/foreach}
          </tbody>
        </table>
      </div>
    {else}
      <div class="alert alert-info">
        {l s='You have no retractation requests.' mod='retractation2026'}
      </div>
    {/if}

    <a href="{$link->getPageLink('my-account')}" class="btn btn-primary">
      {l s='Back to my account' mod='retractation2026'}
    </a>
  </div>
{/block}
