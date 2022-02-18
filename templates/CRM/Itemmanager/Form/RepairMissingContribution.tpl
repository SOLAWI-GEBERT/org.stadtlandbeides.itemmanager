{* HEADER *}

{* FIELD EXAMPLE: OPTION 1 (AUTOMATIC LAYOUT) *}

{* remove this message, since we have some meaningful content below.
 * Later, if that meaningful content is based on configurabel options (based on
 * on the idea that we might support configurable list of "properties you can
 * edit while cloning"), we  might want such a message when there are no
 * such options available.
<div class="messages status no-popup">
  <div class="icon inform-icon"></div>
  {ts}Are you sure, create a clone of this contribution?{/ts}
</div>
*}
<div class="help">
  {ts domain="org.stadtlandbeides.itemmanager"}
    Change the required contribution status for the cloned dataset.
  {/ts}

</div>

<div  style="padding: 25px;">
  <span class="label">{$form.contribution_status_id.label}</span>
  <span class="content">{$form.contribution_status_id.html}</span>
</div>


<div class="crm-block crm-form-block crm-form-title-here-form-block">
  {if $lineitems}
    <table>
      <thead>
      <tr class="columnheader">
        <th width="20%">{ts}Lineitem{/ts}</th>
        <th width="2%">{ts}Quantity{/ts}</th>
        <th width="2%">{ts}Total{/ts}</th>
      </tr>

      </thead>

      <tbody>
      {foreach from=$lineitems item=lineitem}

        {assign var="field" value=$lineitem.linedata}
        <tr class="{cycle values="odd-row,even-row"}">
          <td width="20%">{$field.label}</td>
          <td width="2%">{$field.qty}</td>
          <td width="2%">{$field.line_total}</td>

        </tr>

      {/foreach}

      </tbody>

    </table>

  {/if}
</div>



{* FOOTER *}
<div class="crm-submit-buttons">
  {include file="CRM/common/formButtons.tpl" location="bottom"}
</div>
