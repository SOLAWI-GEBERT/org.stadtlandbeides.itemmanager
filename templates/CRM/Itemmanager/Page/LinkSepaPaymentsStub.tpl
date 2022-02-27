<div id="SEPALINKSTUB{$financial_id}"
     data-callback-url="{crmURL p='civicrm/sepastub' q="action=browse&cid=`$contact_id`&fid=`$financial_id`&smartyDebug=1"}">
    {* make here some better access *}
    {assign var="contributions" value=$relation.contributions}

    <div hidden id="financial_id" data-financial="{$financial_id}"></div>

    {debug}
    <p>
        <img src="{$config->resourceBase}i/loading-overlay.gif" width="32"/>
    <div id="separetry-text" style="text-align: center; font-size: 1.6em;"></div>
    <div id="separetry-busy" align="center" style="display: none;"><img src="{$config->resourceBase}i/loading-overlay.gif" width="32"/></div>
    </p>

    {if $relation.valid}
        <TABLE id="SEPASTUBTABLE">
            <thead>
            <tr>
                <th>{ts}Contribution{/ts} {ts}Item{/ts}</th>
                <th>
                    <label>
                        <input type="checkbox" class="cm-toggle" id="FinancialGrouplink"
                               data-financial="{$financial_id}">
                    </label>
                </th>
                <th>SEPA {ts}Payments{/ts}</th>
            </tr>
            </thead>

            <tbody>
            {* Contribution List *}
            {foreach from=$contributions item=contribution}
                {assign var="element_contrib_relation_name" value=$contribution.element_link_name}
                <tr class="{cycle values="odd-row,even-row"}">
                    {* left page *}
                    <td class="contrib_table">
                        {foreach from=$contribution.related_contributions item=related}
                            {assign var="element_contrib_cross_name" value=$related.element_cross_name}
                            <div class="contrib_table-row">
                                <div class="contrib_table-cell col-md-fix-big">{$related.item_label}</div>
                                <div class="contrib_table-cell col-md-fix-tiny"><span
                                            class="crm-i fa-bars"></span> {$related.line_count}</div>
                                <div class="contrib_table-cell col-md-fix-tiny">
                                    <a class="nowrap bold crm-expand-row"
                                       title="{ts}view payments{/ts}"
                                       href="{crmURL p='civicrm/payment' q="view=transaction&component=contribution&action=browse&cid=`$contact_id`&id=`$related.contribution_id`&selector=1"}">
                                    </a>
                                </div>
                                <div class="contrib_table-cell col-md-fix-small">&sum; {$related.total_display}</div>
                                <div class="contrib_table-cell col-md-auto">
                                    <span class="crm-i fa-calendar-o"></span> {$related.contribution_date}</div>
                                <div class="contrib_table-cell col-md-fix-small">
                                    {if $related.empty}
                                        -
                                    {else}
                                        <label><input type="checkbox" name="{$element_contrib_cross_name}"></label>
                                    {/if}

                                </div>
                            </div>
                        {/foreach}

                        {if $contribution.multiline}
                            <div class="contrib_table-row">
                                <div class="contrib_table-cell col-md-fix-big"></div>
                                <div class="contrib_table-cell col-md-fix-tiny"></div>
                                <div class="contrib_table-cell col-md-fix-tiny"></div>
                                <div class="contrib_table-cell col-md-fix-small" style="border-top: 1px solid #000;">
                                    &sum; {$contribution.related_total_display}</div>
                                <div class="contrib_table-cell col-md-auto"></div>
                                <div class="contrib_table-cell col-md-fix-small"></div>
                            </div>
                        {/if}
                    </td>


                    {* link button *}
                    <td>
                        <label><input
                                    type="checkbox"
                                    class="cm-toggle"
                                    data-ident="{$element_contrib_relation_name}"
                                    id="LinkPayment"
                                    {if $contribution.is_direct_trxn}
                                        checked
                                    {/if}
                            ></label>
                    </td>

                    {if $contribution.sdd}
                        {assign var="sdd" value=$contribution.sdd}
                        {assign var="element_sdd_cross_name" value=$sdd.element_cross_name}
                        {* right page *}
                        <td class="contrib_table">
                            <div class="contrib_table-row">
                                <div class="contrib_table-cell col-md-auto">
                                    <label><input type="checkbox" name="{$element_sdd_cross_name}"></label>
                                </div>
                                <div class="contrib_table-cell col-md-fix-small"><span
                                            class="crm-i fa-calendar-o"></span> {$sdd.sdd_contribution_date}</div>
                                <div class="contrib_table-cell col-md-fix-small">&sum; {$sdd.sdd_total_display}</div>
                                <div class="contrib_table-cell col-md-fix-big">{$sdd.sdd_source}</div>
                                <div class="contrib_table-cell col-md-fix-big">{$sdd.sdd_mandate}</div>
                            </div>


                        </td>
                    {/if}


                </tr>
            {/foreach}


            </tbody>


        </TABLE>
    {/if}
</div>

{crmScript file='js/crm.expandRow.js'}
