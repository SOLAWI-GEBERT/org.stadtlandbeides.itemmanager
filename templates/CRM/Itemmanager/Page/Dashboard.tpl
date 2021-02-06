<h3>Dashboard</h3>

{* Example: Display a variable directly *}
<p>Updated time is {$currentTime}</p>

{* Example: Display a translated string -- which happens to include a variable *}
<p>{ts 1=$currentTime}(In your native language) The current time is %1.{/ts}</p>

{if $item_bases}

    <h3>{ts domain="org.stadtlandbeides.itemmanager"}Booked items{/ts}</h3>
    <table>
        <thead>
        <tr class="columnheader">
            <td>{ts domain="org.stadtlandbeides.itemmanager"}Item{/ts}</td>
            <td>{ts domain="org.stadtlandbeides.itemmanager"}Referred to{/ts}</td>
            <td></td>
        </tr>
        </thead>

        <tbody>
        {foreach from=$item_bases item=ritem}
            <tr class="{cycle values="odd-row,even-row"}">
                <td>{$ritem.item_label}</td>
                <td>{$ritem.member_name}</td>
            </tr>
        {/foreach}
        </tbody>
    </table>
{else}
    <div id="help">
        {ts domain="org.stadtlandbeides.itemmanager"}This contact has no recorded line items.{/ts}
    </div>
{/if}

