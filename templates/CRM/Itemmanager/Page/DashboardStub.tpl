{if $orphaned_payments}
    <div class="messages warning">
        <p>
            <i class="crm-i fa-exclamation-triangle"></i>
            {ts domain="org.stadtlandbeides.itemmanager" 1=$orphaned_payments|@count}Found %1 orphaned membership payment relation(s) pointing to deleted contributions.{/ts}
        </p>
        <ul>
            {foreach from=$orphaned_payments item=orphan}
                <li>{ts domain="org.stadtlandbeides.itemmanager"}Payment relation{/ts} #{$orphan.pay_id} &rarr; Contribution #{$orphan.contribution_id}</li>
            {/foreach}
        </ul>
        <button type="button" class="crm-button cleanup-orphans-btn" data-url="{$cleanup_url}">
            <i class="crm-i fa-trash"></i>
            {ts domain="org.stadtlandbeides.itemmanager"}Cleanup orphaned relations{/ts}
        </button>
    </div>
{/if}

{if not $data_error}
    {if $field_data}
        <table>
            <thead>
                <tr class="columnheader">
                    <td width="45%">{ts domain="org.stadtlandbeides.itemmanager"}Booked{/ts}</td>
                    <td width="5%">{ts domain="org.stadtlandbeides.itemmanager"}Quantity{/ts}</td>
                    <td width="40%">{ts domain="org.stadtlandbeides.itemmanager"}Item{/ts}</td>
                </tr>
            </thead>
            <tbody>
            {foreach from=$field_data item=quantity}
                {foreach from=$quantity item=ritem}
                    <tr class="{cycle values="odd-row,even-row"}">
                        <td width="45%">{$ritem.min} to {$ritem.max}</td>
                        <td width="5%">{$ritem.item_quantity}</td>
                        <td width="40%">{$ritem.item_label}</td>
                    </tr>
                {/foreach}
            {/foreach}
            </tbody>
        </table>
    {else}
        <div class="help">{ts domain="org.stadtlandbeides.itemmanager"}No line items found for this membership.{/ts}</div>
    {/if}
{else}
    <div class="crm-error">
        {$error_title}
        <span>{$error_message}<br/></span>
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

{literal}
<script type="text/javascript">
CRM.$(function($) {
    $('.cleanup-orphans-btn').on('click', function() {
        var btn = $(this);
        var url = btn.data('url');
        CRM.confirm({
            title: {/literal}'{ts domain="org.stadtlandbeides.itemmanager" escape="js"}Cleanup orphaned relations{/ts}'{literal},
            message: {/literal}'{ts domain="org.stadtlandbeides.itemmanager" escape="js"}This will permanently delete the orphaned membership payment records. Continue?{/ts}'{literal}
        }).on('crmConfirm:yes', function() {
            btn.prop('disabled', true);
            $.post(url).done(function() {
                btn.closest('.crm-accordion-body').crmSnippet('refresh');
            }).fail(function() {
                CRM.alert(
                    {/literal}'{ts domain="org.stadtlandbeides.itemmanager" escape="js"}Cleanup failed. Please try again.{/ts}'{literal},
                    {/literal}'{ts domain="org.stadtlandbeides.itemmanager" escape="js"}Error{/ts}'{literal},
                    'error'
                );
                btn.prop('disabled', false);
            });
        });
    });
});
</script>
{/literal}
