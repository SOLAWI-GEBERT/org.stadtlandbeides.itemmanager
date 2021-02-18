<div class="crm-actions-ribbon">
    <fieldset>
        <span>
            <input type="checkbox" name="optionlist"/>
            {ts domain="org.stadtlandbeides.itemmanager"}Sync price items{/ts}
        </span>
        <span>
            <input type="checkbox" name="optionlist"/>
            {ts domain="org.stadtlandbeides.itemmanager"}Harmonize date{/ts}
        </span>

        <a title="{ts domain="org.project60.sepa"}Preview{/ts}" class="refresh button" >
            <span>
              <div class="icon refresh-icon  ui-icon-refresh"></div>
              {ts domain="org.stadtlandbeides.itemmanager"}Preview{/ts}
            </span>
        </a>

        <a title="{ts domain="org.project60.sepa"}Update Items{/ts}" class="refresh button" >
            <span>
              <div class="icon edit-icon ui-icon-pencil"></div>
              {ts domain="org.stadtlandbeides.itemmanager"}Update Items{/ts}
            </span>
        </a>

    </fieldset>


    <div class="clear"></div>
</div>
<h3>{ts domain="org.stadtlandbeides.itemmanager"}Found items to be updated{/ts}</h3>



{if $submit_url}
<form id='new_sepa_mandate' action="{$submit_url}" method="post">
    <input type="hidden" name="contact_id" value="{$contact_id}" />
    <fieldset>
    <table>
        <thead>
        <tr class="columnheader">
            <td width="5%"><input type="checkbox" name="viewlist" value="select_all"/></td>
            <td width="45%">{ts domain="org.stadtlandbeides.itemmanager"}Referred to{/ts}</td>
            <td width="5%">{ts domain="org.stadtlandbeides.itemmanager"}Quantity{/ts}</td>
            <td width="40%">{ts domain="org.stadtlandbeides.itemmanager"}Item{/ts}</td>
        </tr>
        </thead>

        <tbody>
        {foreach from=$base_list item=ritem}
            <tr class="{cycle values="odd-row,even-row"}">
                <td width="5%"><input type="checkbox" name="viewlist" value="{$ritem.line_id}"/></td>
                <td width="45%">{$ritem.member_name}</td>
                <td width="5%">{$ritem.item_quantity}</td>
                <td width="40%">{$ritem.item_label}</td>

            </tr>
        {/foreach}
        </tbody>
    </table>
    </fieldset>

</form>

{/if}
