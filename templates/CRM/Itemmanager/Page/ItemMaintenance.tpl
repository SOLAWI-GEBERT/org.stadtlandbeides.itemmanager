{literal}
    <style>
        .changed_data {
            color: #c00;
        }
        .orphan-row {
            background-color: #fff3f3;
        }
        .orphan-row td {
            color: #999;
            font-style: italic;
        }
        .orphan-error {
            color: #c00;
            font-weight: bold;
            font-style: normal;
        }
        .orphan-delete-label {
            color: #c00;
            font-style: normal;
            cursor: pointer;
            white-space: nowrap;
        }
        .itemmanager-toolbar {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 8px 4px;
            flex-wrap: wrap;
        }
        .itemmanager-toolbar .filter-group {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            border: 1px solid #d3d3d3;
            border-radius: 4px;
            padding: 6px 12px;
            background: #f9f9f9;
        }
        .itemmanager-toolbar .filter-group label {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            cursor: pointer;
            white-space: nowrap;
            font-weight: normal;
            margin: 0;
        }
        .itemmanager-toolbar .filter-group label.orphan-filter {
            color: #c00;
        }
        .itemmanager-toolbar .filter-group .separator {
            width: 1px;
            height: 20px;
            background: #d3d3d3;
        }
        .itemmanager-toolbar .action-group {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-left: auto;
        }
        #itemmanager-progress-area {
            margin: 20px 0;
        }
        .itemmanager-progress-container {
            background: #e0e0e0;
            border-radius: 4px;
            height: 24px;
            overflow: hidden;
            margin-bottom: 10px;
        }
        #itemmanager-progress-bar {
            width: 0%;
            height: 100%;
            background: #4CAF50;
            border-radius: 4px;
            transition: width 0.3s ease;
        }
        #itemmanager-progress-label {
            color: #666;
            font-size: 0.9em;
        }
        #itemmanager-stats-area {
            margin: 15px 0;
        }
        #itemmanager-stats-area .report-layout {
            width: auto;
        }
        #itemmanager-stats-area .report-layout td {
            padding: 4px 12px;
        }
        .itemmanager-tag {
            display: inline-block;
            padding: 1px 6px;
            border-radius: 3px;
            font-size: 0.85em;
            margin: 1px 2px;
            background: #e0e0e0;
            white-space: nowrap;
        }
        .itemmanager-tag-dropdown {
            position: relative;
            display: inline-block;
        }
        .tag-dropdown-panel {
            position: absolute;
            top: 100%;
            left: 0;
            z-index: 100;
            background: #fff;
            border: 1px solid #d3d3d3;
            border-radius: 4px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
            padding: 6px 0;
            min-width: 200px;
            max-height: 300px;
            overflow-y: auto;
        }
        .tag-dropdown-item {
            display: block;
            padding: 4px 12px;
            cursor: pointer;
            white-space: nowrap;
            font-weight: normal;
            margin: 0;
        }
        .tag-dropdown-item:hover {
            background: #f0f0f0;
        }
        .tag-dropdown-empty {
            padding: 8px 12px;
            color: #999;
            font-style: italic;
        }
        #tag-dropdown-count:not(:empty)::before {
            content: '(';
        }
        #tag-dropdown-count:not(:empty)::after {
            content: ')';
        }
    </style>
{/literal}

<input type="hidden" id="itemmanager-analyze-url" value="{$analyze_url}" />

<div class="crm-block crm-content-block">
    <h3>{ts domain="org.stadtlandbeides.itemmanager"}Item Maintenance{/ts}</h3>
    <div class="help">
        {ts domain="org.stadtlandbeides.itemmanager"}Analyze and update line items across all contacts with memberships. Select filters and a start date, then run the analysis.{/ts}
    </div>

    <div class="itemmanager-toolbar">
        <div class="filter-group">
            <label>
                <input type="date" id="date_from" value="{$default_date}" />
                {ts domain="org.stadtlandbeides.itemmanager"}From date{/ts}
            </label>
            <div class="separator"></div>
            <label>
                <input type="checkbox" id="filter_sync" checked />
                {ts domain="org.stadtlandbeides.itemmanager"}Sync price items{/ts}
            </label>
            <div class="separator"></div>
            <label>
                <input type="checkbox" id="filter_harmonize" />
                {ts domain="org.stadtlandbeides.itemmanager"}Harmonize date{/ts}
            </label>
            <div class="separator"></div>
            <label class="orphan-filter">
                <input type="checkbox" id="filter_orphan" />
                {ts domain="org.stadtlandbeides.itemmanager"}Cleanup orphaned relations{/ts}
            </label>
            <div class="separator"></div>
            <div class="itemmanager-tag-dropdown">
                <button type="button" id="tag-dropdown-toggle" class="crm-button">
                    <i class="crm-i fa-ban"></i>
                    {ts domain="org.stadtlandbeides.itemmanager"}Exclude Tags{/ts}
                    <span id="tag-dropdown-count"></span>
                    <i class="crm-i fa-caret-down"></i>
                </button>
                <div id="tag-dropdown-panel" class="tag-dropdown-panel" style="display:none;">
                    {foreach from=$tags item=tag}
                        <label class="tag-dropdown-item">
                            <input type="checkbox" class="filter-tag-cb" value="{$tag.id}" />
                            {$tag.label}
                        </label>
                    {/foreach}
                    {if !$tags}
                        <div class="tag-dropdown-empty">{ts domain="org.stadtlandbeides.itemmanager"}No tags available{/ts}</div>
                    {/if}
                </div>
            </div>
        </div>

        <div class="action-group">
            <button id="itemmanager-start-analysis" class="crm-button crm-form-submit">
                <i class="crm-i fa-search"></i>
                {ts domain="org.stadtlandbeides.itemmanager"}Start Analysis{/ts}
            </button>
        </div>
    </div>

    <div id="itemmanager-progress-area" style="display:none;">
        <div class="itemmanager-progress-container">
            <div id="itemmanager-progress-bar"></div>
        </div>
        <div id="itemmanager-progress-label"></div>
    </div>

    <div id="itemmanager-stats-area" style="display:none;">
        <h3>{ts domain="org.stadtlandbeides.itemmanager"}Statistics{/ts}</h3>
        <div id="itemmanager-stats-content"></div>
    </div>

    <div id="itemmanager-results-area" style="display:none;">
        <h3>{ts domain="org.stadtlandbeides.itemmanager"}Found items to be updated{/ts}</h3>

        <table class="crm-content-block">
            <thead>
            <tr class="columnheader">
                <td width="3%"><input type="checkbox" id="select_all" /></td>
                <td width="8%">{ts domain="org.stadtlandbeides.itemmanager"}Date{/ts}</td>
                <td width="12%">{ts domain="org.stadtlandbeides.itemmanager"}Contact{/ts}</td>
                <td width="10%">{ts domain="org.stadtlandbeides.itemmanager"}Tags{/ts}</td>
                <td width="8%">{ts domain="org.stadtlandbeides.itemmanager"}Referred to{/ts}</td>
                <td width="17%">{ts domain="org.stadtlandbeides.itemmanager"}Item{/ts}</td>
                <td width="5%">{ts domain="org.stadtlandbeides.itemmanager"}Quantity{/ts}</td>
                <td width="5%">{ts domain="org.stadtlandbeides.itemmanager"}Unit price{/ts}</td>
                <td width="5%">{ts domain="org.stadtlandbeides.itemmanager"}Total{/ts}</td>
                <td width="5%">{ts domain="org.stadtlandbeides.itemmanager"}Tax{/ts}</td>
                <td width="10%">{ts domain="org.stadtlandbeides.itemmanager"}Error{/ts}</td>
            </tr>
            </thead>
            <tbody id="itemmanager-results-body">
            </tbody>
        </table>

        <div class="action-group" style="margin-top: 10px;">
            <button id="itemmanager-update-btn" class="crm-button crm-form-submit" style="display:none;">
                <i class="crm-i fa-level-up"></i>
                {ts domain="org.stadtlandbeides.itemmanager"}Update Items{/ts}
            </button>
        </div>
    </div>
</div>

{crmScript ext="org.stadtlandbeides.itemmanager" file="js/itemMaintenanceProgress.js"}
