{* HEADER *}
{literal}
  <style>

    .contrib_table {
      display: table;
      width: 100%;
    }

    .contrib_table_left {
      display: table;
      width: 100%;
    }

    .contrib_table_right {
      display: table;
      width: 80%;
    }

    .contrib_table-row { display: flex;

    }
    .contrib_table_right .contrib_table_left .contrib_table .contrib_table-cell
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

    /* Toggle Button */
    .cm-toggle {
      -webkit-appearance: none;
      -webkit-tap-highlight-color: transparent;
      position: relative;
      border: 0;
      outline: 0;
      cursor: pointer;
      margin: 5px;
    }

    /* To create surface of toggle button */
    .cm-toggle:after {
      content: '';
      width: 30px;
      height: 14px;
      display: inline-block;
      background: rgba(196, 195, 195, 0.55);
      border-radius: 9px;
      clear: both;
    }

    /* Contents before checkbox to create toggle handle */
    .cm-toggle:before {
      content: '';
      width: 16px;
      height: 16px;
      display: block;
      position: absolute;
      left: 0;
      top: -0px;
      border-radius: 50%;
      background: rgb(255, 255, 255);
      box-shadow: 1px 1px 3px rgba(0, 0, 0, 0.6);
    }

    /* Shift the handle to left on check event */
    .cm-toggle:checked:before {
      left: 19px;
      box-shadow: -1px 1px 3px rgba(0, 0, 0, 0.6);
    }
    /* Background color when toggle button will be active */
    .cm-toggle:checked:after {
      background: #2A71B4;
    }

    /* Transition for smoothness */
    .cm-toggle,
    .cm-toggle:before,
    .cm-toggle:after,
    .cm-toggle:checked:before,
    .cm-toggle:checked:after {
      transition: ease .3s;
      -webkit-transition: ease .3s;
      -moz-transition: ease .3s;
      -o-transition: ease .3s;
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
    {assign var="element_group_relation_name" value=$relation.element_link_name}

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
                  <span class="content">{$form.$element_group_relation_name.html}</span>
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
                        <div class="contrib_table-cell col-md-fix-tiny"><span class="crm-i fa-bars"></span> {$related.line_count}</div>
                        <div class="contrib_table-cell col-md-fix-small">&sum; {$related.total_display}</div>
                        <div class="contrib_table-cell col-md-auto"><span class="crm-i fa-calendar-o"></span> {$related.contribution_date}</div>
                        <div class="contrib_table-cell col-md-fix-small">
                          {if $related.empty}
                          -
                          {else}
                            <span class="content">{$form.$element_contrib_cross_name.html}</span>
                          {/if}

                        </div>
                      </div>

                    {/foreach}

                    {if $contribution.multiline}
                      <div class="contrib_table-row">
                        <div class="contrib_table-cell col-md-fix-big"></div>
                        <div class="contrib_table-cell col-md-fix-tiny" ></div>
                        <div class="contrib_table-cell col-md-fix-small" style="border-top: 1px solid #000;" >&sum; {$contribution.related_total_display}</div>
                        <div class="contrib_table-cell col-md-auto" ></div>
                        <div class="contrib_table-cell col-md-fix-small" ></div>
                      </div>
                    {/if}
                  </td>

                  {* link button *}
                  <td>
                    <span class="content">{$form.$element_contrib_relation_name.html}</span>
                  </td>

                  {if $contribution.sdd}
                    {assign var="sdd" value=$contribution.sdd}
                    {assign var="element_sdd_cross_name" value=$sdd.element_cross_name}
                    {* right page *}

                    <td class="contrib_table_right">
                      <div class="contrib_table-row">
                        <div class="contrib_table-cell col-md-auto">
                          <span class="content">{$form.$element_sdd_cross_name.html}</span>
                        </div>
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


{* FOOTER *}
<div class="crm-submit-buttons">
{include file="CRM/common/formButtons.tpl" location="bottom"}
</div>
