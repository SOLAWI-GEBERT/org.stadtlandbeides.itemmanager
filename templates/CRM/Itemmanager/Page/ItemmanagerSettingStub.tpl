{if $errormessages}
    <div class="crm-error">
        {foreach from=$errormessages item=message}
            <span>{$message}<br/></span>
        {/foreach}
    </div>
{/if}

{if $fields}
    <table>
        <thead>
        <tr class="columnheader">
            <th class="medium">{ts}Item Name{/ts}</th>
            <th class="small">{ts}Active On{/ts}</th>
            <th class="small">{ts}Expire On{/ts}</th>
            <th class="small">{ts}Active{/ts}</th>
            <th class="small">{ts}Ignore{/ts}</th>
            <th class="small">{ts}Extend{/ts}</th>
            <th class="small">{ts}Novitiate{/ts}</th>
            <th class="small">{ts}Bidding Round{/ts}</th>
            <th class="small">{ts}Exception{/ts}</th>
            <th class="small">{ts}Exception Periods{/ts}</th>
            <th>{ts}Successor{/ts}</th>
        </tr>
        </thead>
        <tbody>
        {foreach from=$fields item=field}
            <tr class="{cycle values="odd-row,even-row"}">
                <td class="medium">{$field.field_label}</td>
                <td class="small">{$field.active_on}</td>
                <td class="small">{$field.expire_on}</td>
                <td class="small">{$field.isactive}</td>
                <td class="small">
                    <input type="checkbox" name="{$field.element_period_field_ignore}"
                           value="1" {if $field.ignore}checked{/if} />
                </td>
                <td class="small">
                    <input type="checkbox" name="{$field.element_period_field_extend}"
                           value="1" {if $field.extend}checked{/if} />
                </td>
                <td class="small">
                    <input type="checkbox" name="{$field.element_period_field_novitiate}"
                           value="1" {if $field.novitiate}checked{/if} />
                </td>
                <td class="small">
                    <input type="checkbox" name="{$field.element_period_field_bidding}"
                           value="1" {if $field.bidding}checked{/if} />
                </td>
                <td class="small">
                    <input type="checkbox" name="{$field.element_enable_period_exception}"
                           value="1" {if $field.enable_period_exception}checked{/if} />
                </td>
                <td class="small">
                    <input type="text" name="{$field.element_exception_periods}"
                           value="{$field.exception_periods}" size="5" placeholder="1" />
                </td>
                <td>
                    <select name="{$field.element_period_field_successor}" style="width:300px;">
                        {foreach from=$field.selection key=sel_id item=sel_label}
                            <option value="{$sel_id}" {if $sel_id == $field.successor}selected{/if}>{$sel_label}</option>
                        {/foreach}
                    </select>
                    <input type="hidden" name="stub_field_ids[]" value="{$field.manager_id}" />
                </td>
            </tr>
        {/foreach}
        </tbody>
    </table>
{else}
    <div class="help">{ts domain="org.stadtlandbeides.itemmanager"}No price field items found for this period.{/ts}</div>
{/if}
