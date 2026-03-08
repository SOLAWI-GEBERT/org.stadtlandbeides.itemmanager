<div class="crm-content">

<h3>{ts domain="org.stadtlandbeides.itemmanager"}Item Dashboard{/ts}</h3>

<div>
    <a id="items_renew_extra_button"
       class="button crm-popup"
       href="{crmURL p="civicrm/items/renewperiods" q="action=renew_item_periods&cid=$contact_id"}">
        <span>
            <div class="crm-i fa-retweet" style="padding-right:5px;"></div>{ts domain="org.stadtlandbeides.itemmanager"}Renew Periods{/ts}
        </span>
    </a>
    <a id="items_update_extra_button"
       class="button crm-popup"
       href="{crmURL p="civicrm/items/update" q="action=update&cid=$contact_id&harm=1&sync=1"}">
        <span>
            <div class="crm-i fa-level-up" style="padding-right:5px;"></div>{ts domain="org.stadtlandbeides.itemmanager"}Update Items{/ts}
        </span>
    </a>

    {* &smartyDebug=1 *}
    <a id="items_link_to_sepa_button"
       class="button crm-popup"
       href="{crmURL p="civicrm/items/linksepawrapper" q="action=sepalink&cid=$contact_id"}">
        <span>
            <div class="crm-i fa-money" style="padding-right:5px;"></div>{ts domain="org.stadtlandbeides.itemmanager"}Link Sepa Payments{/ts}
        </span>
    </a>



    <a title="{ts domain="org.project60.sepa"}Refresh{/ts}"
       id="refresh_dashboard"
       class="button"
       href="{crmURL p="civicrm/contact/view" q="action=refresh&reset=1&cid=$contact_id&selectedChild=itemmanager"}">
                    <span>
                      <div class="crm-i fa-refresh" style="padding-right:5px;"></div>
                      {ts domain="org.stadtlandbeides.itemmanager"}Refresh{/ts}
                    </span>
    </a>
    <br/>
    <br/>
</div>

</br>
<div class="help">{ts domain="org.stadtlandbeides.itemmanager"}To start with a new set of monthly contributions click Renew Periods{/ts}</div>

{if not $data_error}
    {if $member_list}

        {foreach from=$member_list item=member}
            {assign var="mid" value=$member.membership_id}
            <div class="crm-accordion-wrapper collapsed">

                <div class="crm-accordion-header" id="DashboardAccordionExpander"
                     data-url="{crmURL p='civicrm/items/tabstub' q="action=browse&cid=$contact_id&mid=$mid"}"
                >
                    {ts domain="org.stadtlandbeides.itemmanager"}Membership of {$member.member_name} {/ts}
                    {ts}Status{/ts} {$member.status}
                </div>
                <div class="crm-accordion-body">
                    <div class="crm-clear">
                        <div>{ts domain="org.stadtlandbeides.itemmanager"}Please wait...{/ts}</div>
                    </div>
                </div>
            </div>
        {/foreach}


    {else}
        <div id="help">
            {ts domain="org.stadtlandbeides.itemmanager"}This contact has no recorded line items.{/ts}
        </div>
    {/if}
{else}
    <div id="error" class="crm-error">
        {$error_title}
        <span>{$error_message}</br></span>
        {if $detail_member}
            <span>Membership {$detail_member}<br/></span>
        {/if}
        {if $detail_contribution}
            <span>Contribution ID {$detail_contribution}<br/></span>
        {/if}
        {if $detail_lineitem}
            <span>Lineitem {$detail_lineitem}<br/></span>
        {/if}
    </div>
{/if}


{crmScript ext="org.stadtlandbeides.itemmanager" file="js/expandDashboardAccordion.js"}

    <script type="application/javascript">
    {literal}
        CRM.$(function($) {
            $(document).on('crmPopupClose', function(event) {
                if($(event.target).attr('href') && $(event.target).attr('href').includes('civicrm/items/update')) {
                    $("#items_update_extra_button").closest("div.crm-ajax-container").crmSnippet('refresh');
                }
            });

            $('#crm-content')
                .on('click', 'a.button, a.action-item[href*="action=refresh"]', CRM.popup)
                .on('crmPopupFormSuccess', function() {
                    CRM.reloadPage();
                });
        });
    {/literal}
</script>
</div>