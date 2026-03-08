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
