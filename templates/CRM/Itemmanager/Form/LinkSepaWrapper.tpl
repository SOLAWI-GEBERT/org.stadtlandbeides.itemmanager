{* HEADER *}
<h3>{ts domain="org.stadtlandbeides.itemmanager"}SEPA Payment Assignments{/ts}</h3>

<div class="help">

    {ts domain="org.stadtlandbeides.itemmanager"}
        Link here the SEPA payments to the payment plan contributions.
        You can also set the payment contributions manually to paid here.
    {/ts}
</div>

{if $errormessages}
    <div class="crm-error">
        {foreach from=$errormessages item=message}
            <span>{$message}</br></span>
        {/foreach}
    </div>

{/if}


<div class="crm-block crm-form-block crm-form-title-here-form-block" style="margin: auto">
    <label>
        <input type="checkbox" >
        Differ
    </label>
    <span class="label">Checkbox</span>
    <a id="top_button"
       class="button"
    >
        <div>
            <span class="crm-i fa-random" style="padding-right:5px;">Cross Link</span>
        </div>
    </a>
</div>

{if $relations}
    {foreach from=$relations item=relation}

        {* make here some better access *}
        {assign var="element_group_relation_name" value=$relation.element_link_name}

        <div class="crm-accordion-wrapper collapsed"
             style="padding: 5px;"
             data-url="{crmURL p='civicrm/sepastub' q="edit=payment&cid=`$contact_id`&fid=`$relation.financial_id`&smartyDebug=1"}"
        >
                <div class="crm-accordion-header">{ts}Contribution Type{/ts} {$relation.financial_name}</div>
                <div class="crm-accordion-body">
                    <div class="crm-clear">

                        <div>{ts}Please wait...{/ts}</div>

                    </div>
                </div>
        </div>

    {/foreach}

{/if}

{* FOOTER *}
<div class="crm-submit-buttons">
{include file="CRM/common/formButtons.tpl" location="bottom"}
</div>
{include file='css/sepaLink.css'}
{include file='js/expandAccordion.js'}



