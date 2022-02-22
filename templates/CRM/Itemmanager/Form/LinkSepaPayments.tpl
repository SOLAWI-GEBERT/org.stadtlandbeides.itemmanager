{* HEADER *}
{literal}
  <style>
    .contrib_table {
      display: table;
      width: 100%;
    }
    .contrib_table-row { display: flex;

    }
    .contrib_table .contrib_table-cell
    {
      display: table-cell;
      padding: 2px;
    }

    .col-md-fix-big {
      width:30%;

    }

    .col-md-fix-small {
      width:20%;
    }

    .col-md-fix-tiny {
      width:5%;
    }

    .col-md-auto {
      -moz-box-flex: 1;
      flex:1 1 auto;
      width: auto;
    }


  </style>
{/literal}

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
              <div>
                  <span class="crm-i fa-random" style="padding-right:5px;">Cross Link</span>
              </div>
  </a>
</div>

{if $relations}
  {foreach from=$relations item=relation}

    {* make here some better access *}
    {assign var="contributions" value=$relation.contributions}

    {if $relation.valid}

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
                    <div>
                        <span class="crm-i fa-toggle-off" style="padding-right:5px;"></span>
                    </div>
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
                  <td class="contrib_table">
                    {foreach from=$contribution.related_contributions item=related}
                      <div class="contrib_table-row">
                        <div class="contrib_table-cell col-md-fix-big">{$related.item_label}</div>
                        <div class="contrib_table-cell col-md-fix-tiny"><span class="crm-i fa-bars"></span> {$related.line_count}</div>
                        <div class="contrib_table-cell col-md-fix-small">&sum; {$related.total_display}</div>
                        <div class="contrib_table-cell col-md-fix-small"><span class="crm-i fa-calendar-o"></span> {$related.contribution_date}</div>
                        <div class="contrib_table-cell col-md-auto">check</div>
                      </div>

                    {/foreach}

                    {if $contribution.multiline}
                      <div class="contrib_table-row">
                        <div class="contrib_table-cell col-md-fix-big"></div>
                        <div class="contrib_table-cell col-md-fix-tiny" ></div>
                        <div class="contrib_table-cell col-md-fix-small" style="border-top: 1px solid #000;" >&sum; {$contribution.related_total_display}</div>
                        <div class="contrib_table-cell col-md-fix-small" ></div>
                        <div class="contrib_table-cell col-md-auto" ></div>
                      </div>
                    {/if}
                  </td>

                  {* button page *}
                  <td>
                    <a id="middle_id_1"
                       class="crm-designer-edit-custom"
                       >
                      <div>
                          <span class="crm-i fa-toggle-off" style="padding-right:5px;"></span>
                      </div>
                    </a>

                  </td>

                  {if $contribution.sdd}
                    {assign var="sdd" value=$contribution.sdd}
                    {* right page *}

                    <td class="contrib_table">
                      <div class="contrib_table-row">
                        <div class="contrib_table-cell col-md-auto">check</div>
                        <div class="contrib_table-cell col-md-fix-small"><span class="crm-i fa-calendar-o"></span> {$sdd.sdd_contribution_date}</div>
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


        </div>
      </div>

    {/if}

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
