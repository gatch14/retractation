<div class="card mt-2">
    <div class="card-header">
        <h3 class="card-header-title">{l s='Rétractation' mod='retractation2026'}</h3>
    </div>
    <div class="card-body">
        {if $retractation_request}
            <p>
                <strong>{l s='Statut :' mod='retractation2026'}</strong>
                {if $retractation_request.status == 'pending'}
                    <span class="badge badge-warning">{l s='En attente' mod='retractation2026'}</span>
                {elseif $retractation_request.status == 'accepted'}
                    <span class="badge badge-success">{l s='Acceptée' mod='retractation2026'}</span>
                {elseif $retractation_request.status == 'rejected'}
                    <span class="badge badge-danger">{l s='Refusée' mod='retractation2026'}</span>
                {else}
                    <span class="badge badge-secondary">{$retractation_request.status|escape:'html':'UTF-8'}</span>
                {/if}
            </p>
            <p>
                <strong>{l s='Date de rétractation :' mod='retractation2026'}</strong>
                {$retractation_request.retractation_date|escape:'html':'UTF-8'}
            </p>
            <p>
                <strong>{l s='Date limite :' mod='retractation2026'}</strong>
                {$retractation_request.deadline_date|escape:'html':'UTF-8'}
            </p>
            <p>
                <strong>{l s='Source :' mod='retractation2026'}</strong>
                {$retractation_request.deadline_source|escape:'html':'UTF-8'}
            </p>
        {else}
            {if $retractation_eligibility.eligible}
                <p>
                    <span class="badge badge-info">{l s='Éligible' mod='retractation2026'}</span>
                </p>
                <p>
                    <strong>{l s='Date limite :' mod='retractation2026'}</strong>
                    {$retractation_eligibility.deadline|escape:'html':'UTF-8'}
                </p>
            {else}
                <p>
                    <span class="badge badge-secondary">{l s='Non éligible' mod='retractation2026'}</span>
                </p>
                <p>
                    <strong>{l s='Raison :' mod='retractation2026'}</strong>
                    {$retractation_eligibility.reason|escape:'html':'UTF-8'}
                </p>
            {/if}
        {/if}
        <a href="{$retractation_module_link|escape:'html':'UTF-8'}" class="btn btn-outline-primary btn-sm mt-2">
            {l s='Voir le tableau de bord' mod='retractation2026'}
        </a>
    </div>
</div>
