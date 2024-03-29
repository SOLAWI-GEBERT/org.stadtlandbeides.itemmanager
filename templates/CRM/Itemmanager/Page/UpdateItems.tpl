{literal}
    <style>
        .changed_data{
            color:red;
        }
    </style>
{/literal}


{if $submit_url}
<form class="crm-form-block" id='item_update_list' name="item_update_list" action="{$submit_url}" method="post">
    <input type="hidden" id="filter_sync" name="filter_sync" value="{$filter_sync}" />
    <input type="hidden" id="filter_harmonize" name="filter_harmonize" value="{$filter_harmonize}" />
    <input type="hidden" id="filter_url" value="{$filter_url}" />
    <input type="hidden" name="contact_id" value="{$contact_id}" />

    <div class="crm-actions-ribbon">
        {include file="CRM/common/formButtons.tpl" location="top"}
            <span class="crm-checkbox-list">
                <input type="checkbox" id='sync_price' name="optionlist" onchange="SetFilter(CRM.$, CRM._)"

                {if $filter_sync}
                    checked
                {/if}

                />
                {ts domain="org.stadtlandbeides.itemmanager"}Sync price items{/ts}
            </span>
            <span class="crm-checkbox-list">
                <input type="checkbox" id='harmonize_date' name="optionlist" onchange="SetFilter(CRM.$, CRM._)"
                {if $filter_harmonize}
                    checked
                {/if}
                />
                {ts domain="org.stadtlandbeides.itemmanager"}Harmonize date{/ts}
            </span>

            <span class="refresh crm-button" onclick="CloseDialog(CRM.$, CRM._)").dialog('destroy');">
                <a title="{ts domain="org.project60.sepa"}Preview{/ts}" name="preview_button"
                   id="Preview_Button"
                   href="{$filter_url}&harm={$filter_harmonize}&sync={$filter_sync}" class="crm-popup action-item">
                    <span>
                      <div class="icon refresh-icon  ui-icon-refresh"></div>
                      {ts domain="org.stadtlandbeides.itemmanager"}Preview{/ts}
                    </span>
                </a>
             </span>
        {if $base_list}
            <span class="refresh crm-button crm-submit-buttons">
                <div class="icon edit-icon ui-icon-pencil"></div>
                <input type="submit" name="items_update" value="{ts domain="org.stadtlandbeides.itemmanager"}Update Items{/ts}">
            </span>
        {/if}

        <div class="clear"></div>
    </div>
    <div class="crm-block">
        <h3>{ts domain="org.stadtlandbeides.itemmanager"}Found items to be updated{/ts}</h3>

        <table class="crm-content-block">
            <thead>
            <tr class="columnheader">
                <td width="5%"><input type="checkbox" name="all" id="select_all" onchange="SelectAll(CRM.$, CRM._)"/></td>
                <td width="10%">{ts domain="org.stadtlandbeides.itemmanager"}Date{/ts}</td>
                <td width="20%">{ts domain="org.stadtlandbeides.itemmanager"}Referred to{/ts}</td>
                <td width="30%">{ts domain="org.stadtlandbeides.itemmanager"}Item{/ts}</td>
                <td width="5%">{ts domain="org.stadtlandbeides.itemmanager"}Quantity{/ts}</td>
                <td width="5%">{ts domain="org.stadtlandbeides.itemmanager"}Unit price{/ts}</td>
                <td width="5%">{ts domain="org.stadtlandbeides.itemmanager"}Total{/ts}</td>
                <td width="5%">{ts domain="org.stadtlandbeides.itemmanager"}Tax{/ts}</td>
                <td width="15%">{ts domain="org.stadtlandbeides.itemmanager"}Error{/ts}</td>
            </tr>
            </thead>

            <tbody>

                {foreach from=$base_list item=ritem}
                    <tr class="{cycle values="odd-row,even-row"}">
                        <td width="5%"><input type="checkbox" name="viewlist[]" value="{$ritem.line_id}"/></td>
                        {if $ritem.update_date}
                            <td  width="10%"><span class="changed_data">{$ritem.contrib_date}
                                    </br> {ts domain="org.stadtlandbeides.itemmanager"}change to{/ts}
                                    </br> {$ritem.change_date}</span></td>
                        {else}
                            <td width="20%">{$ritem.contrib_date}</td>
                        {/if}

                        <td width="20%" >{$ritem.member_name}</td>

                        {if $ritem.update_label}
                            <td  width="30%"><span class="changed_data">{$ritem.item_label}
                                    </br> {ts domain="org.stadtlandbeides.itemmanager"}change to{/ts}
                                    </br> {$ritem.change_label}</span></td>

                        {else}
                            <td width="45%">{$ritem.item_label}</td>
                        {/if}


                        <td width="5%">{$ritem.item_quantity}</td>

                        <!-- Price block -->
                        {if $ritem.update_price}
                            <td  width="5%"><span class="changed_data">{$ritem.item_price}
                                    </br> {ts domain="org.stadtlandbeides.itemmanager"}change to{/ts}
                                    </br> {$ritem.change_price}</span></td>
                            <td  width="5%"><span class="changed_data">{$ritem.item_total}
                                    </br> {ts domain="org.stadtlandbeides.itemmanager"}change to{/ts}
                                    </br> {$ritem.change_total}</span></td>
                            <td  width="5%"><span class="changed_data">{$ritem.item_tax}
                                    </br> {ts domain="org.stadtlandbeides.itemmanager"}change to{/ts}
                                    </br> {$ritem.change_tax}</span></td>

                        {else}
                            <td width="5%">{$ritem.item_price}</td>
                            <td width="5%">{$ritem.item_total}</td>
                            <td width="5%">{$ritem.item_tax}</td>

                        {/if}

                        <td width="15%"><span class="changed_data">{$ritem.change_error}</span></td>


                    </tr>
                {/foreach}

            </tbody>
        </table>

    </div>


</form>
    {if !$base_list}
        </br>
        <div class="help">{ts domain="org.stadtlandbeides.itemmanager"}Nothing there for updating. Please use filter options to see more.{/ts}</div>
    {/if}

{/if}


{if $update_done}
<script type="text/javascript">
    {literal}
    cj(".ui-dialog > [id^=crm-ajax-dialog-]").dialog("destroy");
    {/literal}
</script>
{/if}

{if $destroy}
    <script type="text/javascript">
        {literal}
        cj(".ui-dialog > [id^=crm-ajax-dialog-]").dialog("destroy");
        {/literal}
    </script>
{/if}


<script type="text/javascript">
{literal}

    function CloseDialog($, _)
    {
        cj(".ui-dialog > [id^=crm-ajax-dialog-]").dialog("destroy");
    }

    //Set Filter
    function SetFilter($, _)
    {
        var p=document.getElementById('sync_price');
        var h=document.getElementById('harmonize_date');
        var s=document.getElementById('filter_sync');
        var d=document.getElementById('filter_harmonize');
        var b=document.getElementById('Preview_Button');
        if (p.checked) {
            s.value = 1;
        } else {
            s.value = 0;
        }
        if (h.checked) {
            d.value = 1;
        } else {
            d.value = 0;
        }

        b.href = updateURL(CRM.$, CRM._)
    }

    //Create Link with filter options
    function updateURL($, _)
    {
        //The variable to be returned
        var s=document.getElementById('filter_sync');
        var d=document.getElementById('filter_harmonize');
        var u=document.getElementById('filter_url');
        var URL = u.value
        URL += "&harm=" + d.value + "&sync=" + s.value;
        return URL;
    }

    //Select all
    function SelectAll($, _)
    {

        var all=document.getElementById('select_all');
        var inputs = document.getElementsByName('viewlist[]');
        // loop through all the inputs, skipping the first one
        for (var i = 0, input; input = inputs[i++]; ) {
           input.checked = all.checked;
        }
    }

    (function($) {

        $('#item_update_list')
            .crmSnippet()
            .on('click', 'a.button, a.action-item[href*="action=preview"]', CRM.popup)
            .on('crmPopupFormSuccess', 'a.button, a.action-item[href*="action=preview"]', function() {
                alert("hier");
                // Refresh datatable when form completes
                $('#item_update_list').crmSnippet('refresh');
            });


    })(CRM.$);


{/literal}
</script>

