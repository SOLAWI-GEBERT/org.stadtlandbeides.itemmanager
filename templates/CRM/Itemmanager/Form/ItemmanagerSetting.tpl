{* HEADER *}
<div class="help">
  {ts domain="org.stadtlandbeides.itemmanager"}
    Edit here the successor of each price field item.
    The given start date just defines day and month. The year has no meaning.
  {/ts}</div>
{if $unsynced_count > 0}
<div class="messages status no-popup">
    <i class="crm-i fa-exclamation-triangle" style="color:#e6a100;"></i>
    {ts domain="org.stadtlandbeides.itemmanager" 1=$unsynced_count}There are %1 new price set(s) that are not yet synchronized. Click "Synchronize" to update.{/ts}
</div>
{/if}
<div class="crm-submit-buttons">
{include file="CRM/common/formButtons.tpl" location="top"}
    <a id="itemmanager-sync-btn" class="button"
       data-sync-url="{$sync_url}"
       data-settings-url="{$settings_url}">
        <span><i class="crm-i fa-refresh" style="padding-right:5px;"></i>{ts}Synchronize{/ts}</span>
    </a>
</div>
{if $errormessages}
  <div class="crm-error">
    {foreach from=$errormessages item=message}
      <span>{$message}</br></span>
    {/foreach}
  </div>

{/if}

{if $itemsettings}
  {foreach from=$itemsettings item=priceset}
    <div class="crm-accordion-wrapper collapsed">
        {assign var="element_period_periods" value=$priceset.element_period_periods}
        {assign var="element_period_start_on" value=$priceset.element_period_start_on}
        {assign var="element_period_type" value=$priceset.element_period_type}
        {assign var="element_period_hide" value=$priceset.element_period_hide}
        {assign var="element_period_reverse" value=$priceset.element_period_reverse}
        {assign var="element_period_successor" value=$priceset.element_period_successor}

        <div class="crm-accordion-header" id="SettingAccordionExpander"
             data-url="{$priceset.stub_url}">
        {ts}Priceset{/ts} {$priceset.price_label}
      </div>
      <div class="crm-accordion-body">
        <div class="crm-block crm-form-block crm-form-title-here-form-block" style="margin: auto">

            <span class="label">{$form.$element_period_periods.label}</span>
            <span class="content">{$form.$element_period_periods.html}</span>
            <span class="label">{$form.$element_period_type.label}</span>
            <span class="content">{$form.$element_period_type.html}</span>
            <span class="label">{$form.$element_period_start_on.label}</span>
            <span class="content">{$form.$element_period_start_on.html}</span>
            <span class="label">{$form.$element_period_hide.label}</span>
            <span class="content">{$form.$element_period_hide.html}</span>
            <span class="label">{$form.$element_period_reverse.label}</span>
            <span class="content">{$form.$element_period_reverse.html}</span>
            <span class="label">{$form.$element_period_successor.label}</span>
            <span class="content">{$form.$element_period_successor.html}</span>
        </div>
        <div class="crm-clear">
            <div>{ts domain="org.stadtlandbeides.itemmanager"}Please wait...{/ts}</div>
        </div>


        </div>
    </div>
  {/foreach}


{/if}

{* FOOTER *}
<div class="clear"></div>
<div class="crm-submit-buttons">
    {include file="CRM/common/formButtons.tpl" location="bottom"}
    <a id="itemmanager-sync-btn" class="button"
       data-sync-url="{$sync_url}"
       data-settings-url="{$settings_url}">
        <span><i class="crm-i fa-refresh" style="padding-right:5px;"></i>{ts}Synchronize{/ts}</span>
    </a>
</div>
{crmScript ext="org.stadtlandbeides.itemmanager" file="js/expandSettingAccordion.js"}
{crmScript ext="org.stadtlandbeides.itemmanager" file="js/syncProgress.js"}
