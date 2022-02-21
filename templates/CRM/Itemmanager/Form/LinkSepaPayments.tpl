{* HEADER *}


<h3>{ts domain="org.stadtlandbeides.itemmanager"}SEPA Payment Assignments{/ts}</h3>

<div class="help">

  {ts domain="org.stadtlandbeides.itemmanager"}
    Link here the SEPA payments to the payment plan contributions.
    You can also set the payment contributions manually to paid here.
  {/ts}
</div>

<div class="crm-submit-buttons">
  {include file="CRM/common/formButtons.tpl" location="top"}
</div>

<div class="crm-block crm-form-block crm-form-title-here-form-block" style="margin: auto">
  <label>
    <input type="checkbox" >
    Differ
  </label>
  <span class="label">Checkbox</span>
  <a id="top_button"
     class="button"
  >
              <span>
                  <div class="crm-i fa-random" style="padding-right:5px;">Cross Link</div>
              </span>
  </a>
</div>

{if $relations}
  {foreach from=$relations item=relation}

    {* make here some better access *}
    {assign var="contributions" value=$relation.contributions}

    <div class="crm-accordion-wrapper open">
      <div class="crm-accordion-header">
        {ts}Contribution Type{/ts} {$relation.financial_name}
      </div>
      <div class="crm-accordion-body">
        {* List and Editor to assign payments *}
        <TABLE>
          <thead>
            <tr>
              <th>{ts}Contribution{/ts} {ts}Item{/ts}</th>
              <th>
                <a id="middle_id_top"
                   class="crm-designer-edit-custom"
                >
                  <span>
                      <div class="crm-i fa-toggle-off" style="padding-right:5px;"></div>
                  </span>
                </a>
              </th>
              <th>SEPA {ts}Payments{/ts}</th>
            </tr>
          </thead>

          <tbody>
            {* Contribution List *}
            {foreach from=$contributions item=contribution}

              <tr class="{cycle values="odd-row,even-row"}">
                {* left page *}
                <td style="display:flex; justify-content : space-between;">
                  <div><span class="crm-i fa-calendar-o"></span> {$contribution.contribution_date}</div>
                  <div>{$contribution.item_label}</div>
                  <div><span class="crm-i fa-bars"></span> {$contribution.line_count}</div>
                  <div>&sum; {$contribution.total_display}</div>
                  <div>check</div></td>

                {* button page *}
                <td>
                  <a id="middle_id_1"
                     class="crm-designer-edit-custom"
                     >
                    <span>
                        <div class="crm-i fa-toggle-off" style="padding-right:5px;"></div>
                    </span>
                  </a>

                </td>

                {* right page *}
                <td>
                  Right

                </td>

              </tr>

            {/foreach}

            {* Example linked *}
            <tr>
              {* left page *}
              <td colspan="3">Left very long </td>

            </tr>

            <tr>

              {* detail row *}
              <td colspan="3">
                <div style="padding-left: 20px;">

                  <a id="middle_id_1"
                      class="crm-designer-edit-custom"
                  >
                  <span>
                      <div class="crm-i fa-toggle-on" style="padding-right:5px;"></div>
                  </span>
                  </a>

                </div>

              </td>


            </tr>



          </tbody>


        </TABLE>


      </div>
    </div>

  {/foreach}

{/if}


{* FIELD EXAMPLE: OPTION 1 (AUTOMATIC LAYOUT) *}

{foreach from=$elementNames item=elementName}
  <div class="crm-section">
    <div class="label">{$form.$elementName.label}</div>
    <div class="content">{$form.$elementName.html}</div>
    <div class="clear"></div>
  </div>
{/foreach}

{* FIELD EXAMPLE: OPTION 2 (MANUAL LAYOUT)

  <div>
    <span>{$form.favorite_color.label}</span>
    <span>{$form.favorite_color.html}</span>
  </div>

{* FOOTER *}
<div class="crm-submit-buttons">
{include file="CRM/common/formButtons.tpl" location="bottom"}
</div>
