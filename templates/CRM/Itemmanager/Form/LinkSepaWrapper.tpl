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

<div hidden data-filter="{$SEPAFilterOptions}" id="SEPAFilterOptionUrl"></div>

<div class="crm-block crm-form-block crm-form-title-here-form-block" style="margin: auto">
    <label class="crm-form-checkbox">
        <input type="checkbox" id="SEPAFilterOpen" {$filteropencheck} >
        {ts domain="org.stadtlandbeides.itemmanager"}Filter open payments{/ts}
    </label>
    <label class="crm-form-checkbox">
        <input type="checkbox" id="SEPAFilterPast" {$filterpastcheck}>
        {ts domain="org.stadtlandbeides.itemmanager"}Filter past contributions{/ts}
    </label>
</div>

{if $relations}
    {foreach from=$relations item=relation}

        {* make here some better access *}
        {* &smartyDebug=1 *}
        {assign var="element_group_relation_name" value=$relation.element_link_name}

        <div class="crm-accordion-wrapper collapsed"
             style="padding: 5px;"
                     >
                <div class="crm-accordion-header" id="AccordionExpander"
                     data-url="{crmURL p='civicrm/sepastub' q="action=browse&cid=`$contact_id`&fid=`$relation.financial_id`"}"

                >{ts}Contribution Type{/ts} {$relation.financial_name}</div>
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
{crmScript ext="org.stadtlandbeides.itemmanager" file="js/expandAccordion.js"}
{crmScript ext="org.stadtlandbeides.itemmanager" file="js/filterSEPAOptions.js"}
{crmScript ext="org.stadtlandbeides.itemmanager" file="js/handlePayment.js"}





