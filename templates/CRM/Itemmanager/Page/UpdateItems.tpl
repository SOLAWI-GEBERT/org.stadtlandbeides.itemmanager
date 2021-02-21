
{if $submit_url}
<form class="crm-form-block" id='item_update_list' name="item_update_list" action="{$submit_url}" method="post">
    <input type="hidden" id="filter_sync" value="{$filter_sync}" />
    <input type="hidden" id="filter_harmonize" value="{$filter_harmonize}" />
    <input type="hidden" id="filter_url" value="{$filter_url}" />
    <input type="hidden" name="contact_id" value="{$contact_id}" />

    <div class="crm-actions-ribbon">

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

            <span class="refresh crm-button">
                <a title="{ts domain="org.project60.sepa"}Preview{/ts}"
                   id="Preview_Button"
                   href="{$filter_url}&harm={$filter_harmonize}&sync={$filter_sync}">
                    <span>
                      <div class="icon refresh-icon  ui-icon-refresh"></div>
                      {ts domain="org.stadtlandbeides.itemmanager"}Preview{/ts}
                    </span>
                </a>
             </span>

            <span class="refresh crm-button crm-submit-buttons">
                <div class="icon edit-icon ui-icon-pencil"></div>
                <input type="submit" name="items_update" value="{ts domain="org.stadtlandbeides.itemmanager"}Update Items{/ts}">
            </span>


        <div class="clear"></div>
    </div>
    <div class="crm-block">
        <h3>{ts domain="org.stadtlandbeides.itemmanager"}Found items to be updated{/ts}</h3>

        <table class="crm-content-block">
            <thead>
            <tr class="columnheader">
                <td width="5%"><input type="checkbox" name="all" id="select_all" onchange="SelectAll(CRM.$, CRM._)"/></td>
                <td width="45%">{ts domain="org.stadtlandbeides.itemmanager"}Referred to{/ts}</td>
                <td width="5%">{ts domain="org.stadtlandbeides.itemmanager"}Quantity{/ts}</td>
                <td width="40%">{ts domain="org.stadtlandbeides.itemmanager"}Item{/ts}</td>
            </tr>
            </thead>

            <tbody>

                {foreach from=$base_list item=ritem}
                    <tr class="{cycle values="odd-row,even-row"}">
                        <td width="5%"><input type="checkbox" name="viewlist[]" value="{$ritem.line_id}"/></td>
                        <td width="45%">{$ritem.member_name}</td>
                        <td width="5%">{$ritem.item_quantity}</td>
                        <td width="40%">{$ritem.item_label}</td>

                    </tr>
                {/foreach}

            </tbody>
        </table>
    </div>


</form>

{/if}


{literal}
<script type="text/javascript">


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
        URL += "&backtrace=1&smartyDebug=1";
        return URL;
    }

    //Select all
    function SelectAll($, _)
    {
        var all=document.getElementById('select_all');
        var inputs = document.getElementsByName('viewlist');
        // loop through all the inputs, skipping the first one
        for (var i = 0, input; input = inputs[i++]; ) {
           input.checked = all.checked;
        }
    }

    (function($) {



        $('#crm-actions-ribbon')
            .on('click', 'a.button, a.action-item[href*="action=preview"]', CRM.popup)
            .on('crmPopupFormSuccess', 'a.button, a.action-item[href*="action=preview"]', function() {

                // Refresh datatable when form completes
                $('#crm-form-block').crmSnippet('refresh');
            });


    })(CRM.$);

    $('#crm-actions-ribbon')
        .on('click', function() {

            alert("Ich bin hier");
            // Refresh datatable when form completes
            //$('#crm-form-block').crmSnippet('refresh');
        });


    })(CRM.$);

</script>
{/literal}