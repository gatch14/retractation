<div class="card mt-2">
    <div class="card-header">
        <h3 class="card-header-title">{$retractation_labels.title|escape:'html':'UTF-8'}</h3>
    </div>
    <div class="card-body">
        {if $retractation_request}
            <p>
                <strong>{$retractation_labels.status|escape:'html':'UTF-8'}</strong>
                {if $retractation_request.status == 'pending'}
                    <span class="badge badge-warning">{$retractation_labels.pending|escape:'html':'UTF-8'}</span>
                {elseif $retractation_request.status == 'accepted'}
                    <span class="badge badge-success">{$retractation_labels.accepted|escape:'html':'UTF-8'}</span>
                {elseif $retractation_request.status == 'rejected'}
                    <span class="badge badge-danger">{$retractation_labels.rejected|escape:'html':'UTF-8'}</span>
                {else}
                    <span class="badge badge-secondary">{$retractation_request.status|escape:'html':'UTF-8'}</span>
                {/if}
            </p>
            <p>
                <strong>{$retractation_labels.retractation_date|escape:'html':'UTF-8'}</strong>
                {$retractation_request.retractation_date|escape:'html':'UTF-8'}
            </p>
            <p>
                <strong>{$retractation_labels.deadline|escape:'html':'UTF-8'}</strong>
                {$retractation_request.deadline_date|escape:'html':'UTF-8'}
            </p>
            <p>
                <strong>{$retractation_labels.source|escape:'html':'UTF-8'}</strong>
                {$retractation_request.deadline_source|escape:'html':'UTF-8'}
            </p>
        {else}
            {if $retractation_eligibility.eligible}
                <p>
                    <span class="badge badge-info">{$retractation_labels.eligible|escape:'html':'UTF-8'}</span>
                </p>
                <p>
                    <strong>{$retractation_labels.deadline|escape:'html':'UTF-8'}</strong>
                    {$retractation_eligibility.deadline|escape:'html':'UTF-8'}
                </p>
            {else}
                <p>
                    <span class="badge badge-secondary">{$retractation_labels.not_eligible|escape:'html':'UTF-8'}</span>
                </p>
                <p>
                    <strong>{$retractation_labels.reason|escape:'html':'UTF-8'}</strong>
                    {$retractation_eligibility.reason|escape:'html':'UTF-8'}
                </p>
            {/if}
        {/if}
        <a href="{$retractation_module_link|escape:'html':'UTF-8'}" class="btn btn-outline-primary btn-sm mt-2">
            {$retractation_labels.dashboard|escape:'html':'UTF-8'}
        </a>
    </div>
</div>
