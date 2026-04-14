{extends file='page.tpl'}

{block name='page_title'}
  {l s='My retractation requests' d='Modules.Retractation2026.Front'}
{/block}

{block name='page_content'}
  <div class="retractation-list">
    {if $retractations|count > 0}
      <div class="table-responsive">
        <table class="table table-striped">
          <thead>
            <tr>
              <th>{l s='Order Reference' d='Modules.Retractation2026.Front'}</th>
              <th>{l s='Status' d='Modules.Retractation2026.Front'}</th>
              <th>{l s='Retractation Date' d='Modules.Retractation2026.Front'}</th>
              <th>{l s='Deadline Date' d='Modules.Retractation2026.Front'}</th>
            </tr>
          </thead>
          <tbody>
            {foreach from=$retractations item=retractation}
              <tr>
                <td>{$retractation.order_reference|escape:'html':'UTF-8'}</td>
                <td>
                  {if $retractation.status == 'pending'}
                    <span class="badge badge-warning">{l s='Pending' d='Modules.Retractation2026.Front'}</span>
                  {elseif $retractation.status == 'accepted'}
                    <span class="badge badge-success">{l s='Accepted' d='Modules.Retractation2026.Front'}</span>
                  {elseif $retractation.status == 'rejected'}
                    <span class="badge badge-danger">{l s='Rejected' d='Modules.Retractation2026.Front'}</span>
                  {elseif $retractation.status == 'cancelled'}
                    <span class="badge badge-secondary">{l s='Cancelled' d='Modules.Retractation2026.Front'}</span>
                  {else}
                    <span class="badge badge-secondary">{$retractation.status|escape:'html':'UTF-8'}</span>
                  {/if}
                </td>
                <td>{$retractation.retractation_date|escape:'html':'UTF-8'}</td>
                <td>{$retractation.deadline_date|escape:'html':'UTF-8'}</td>
              </tr>
            {/foreach}
          </tbody>
        </table>
      </div>
    {else}
      <div class="alert alert-info">
        {l s='You have no retractation requests.' d='Modules.Retractation2026.Front'}
      </div>
    {/if}

    <a href="{$link->getPageLink('my-account')}" class="btn btn-primary">
      {l s='Back to my account' d='Modules.Retractation2026.Front'}
    </a>
  </div>
{/block}
