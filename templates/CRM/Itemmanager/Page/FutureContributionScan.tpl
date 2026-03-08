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
        .missing-row {
            background-color: #fff8e1;
        }
        .missing-row td {
            font-style: italic;
        }
        .missing-label {
            color: #e65100;
            font-weight: bold;
            font-style: normal;
        }
        .extra-row {
            background-color: #fce4ec;
        }
        .extra-row td {
            font-style: italic;
        }
        .extra-label {
            color: #c00;
            font-weight: bold;
            font-style: normal;
        }
        .futurescan-toolbar {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 8px 4px;
            flex-wrap: wrap;
        }
        .futurescan-toolbar .filter-group {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            border: 1px solid #d3d3d3;
            border-radius: 4px;
            padding: 6px 12px;
            background: #f9f9f9;
        }
        .futurescan-toolbar .filter-group label {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            cursor: pointer;
            white-space: nowrap;
            font-weight: normal;
            margin: 0;
        }
        .futurescan-toolbar .filter-group label.orphan-filter {
            color: #c00;
        }
        .futurescan-toolbar .filter-group .separator {
            width: 1px;
            height: 20px;
            background: #d3d3d3;
        }
        .futurescan-toolbar .action-group {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-left: auto;
        }
        #futurescan-progress-area {
            margin: 20px 0;
        }
        .futurescan-progress-container {
            background: #e0e0e0;
            border-radius: 4px;
            height: 24px;
            overflow: hidden;
            margin-bottom: 10px;
        }
        #futurescan-progress-bar {
            width: 0%;
            height: 100%;
            background: #4CAF50;
            border-radius: 4px;
            transition: width 0.3s ease;
        }
        #futurescan-progress-label {
            color: #666;
            font-size: 0.9em;
        }
        #futurescan-stats-area {
            margin: 15px 0;
        }
        #futurescan-stats-area .report-layout {
            width: auto;
        }
        #futurescan-stats-area .report-layout td {
            padding: 4px 12px;
        }
    </style>
{/literal}

<input type="hidden" id="futurescan-analyze-url" value="{$analyze_url}" />

<div class="crm-block crm-content-block">
    <h3>{ts domain="org.stadtlandbeides.itemmanager"}Future Item Scan{/ts}: {$display_name}</h3>
    <div class="help">
        {ts domain="org.stadtlandbeides.itemmanager"}Scan future contributions for this contact and check line items for inconsistencies (label, price, date). Select filters and start the scan.{/ts}
    </div>

    <div class="futurescan-toolbar">
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
                <input type="checkbox" id="filter_harmonize" checked />
                {ts domain="org.stadtlandbeides.itemmanager"}Harmonize date{/ts}
            </label>
            <div class="separator"></div>
            <label class="orphan-filter">
                <input type="checkbox" id="filter_orphan" checked />
                {ts domain="org.stadtlandbeides.itemmanager"}Cleanup orphaned relations{/ts}
            </label>
            <div class="separator"></div>
            <label>
                <input type="checkbox" id="filter_composition" checked />
                {ts domain="org.stadtlandbeides.itemmanager"}Check composition{/ts}
            </label>
        </div>

        <div class="action-group">
            <button id="futurescan-start-analysis" class="crm-button crm-form-submit">
                <i class="crm-i fa-search"></i>
                {ts domain="org.stadtlandbeides.itemmanager"}Start Scan{/ts}
            </button>
        </div>
    </div>

    <div id="futurescan-progress-area" style="display:none;">
        <div class="futurescan-progress-container">
            <div id="futurescan-progress-bar"></div>
        </div>
        <div id="futurescan-progress-label"></div>
    </div>

    <div id="futurescan-stats-area" style="display:none;">
        <h3>{ts domain="org.stadtlandbeides.itemmanager"}Statistics{/ts}</h3>
        <div id="futurescan-stats-content"></div>
    </div>

    <div id="futurescan-results-area" style="display:none;">
        <h3>{ts domain="org.stadtlandbeides.itemmanager"}Found items to be updated{/ts}</h3>

        <table class="crm-content-block">
            <thead>
            <tr class="columnheader">
                <td width="3%"><input type="checkbox" id="select_all" /></td>
                <td width="10%">{ts domain="org.stadtlandbeides.itemmanager"}Date{/ts}</td>
                <td width="10%">{ts domain="org.stadtlandbeides.itemmanager"}Membership{/ts}</td>
                <td width="20%">{ts domain="org.stadtlandbeides.itemmanager"}Item{/ts}</td>
                <td width="5%">{ts domain="org.stadtlandbeides.itemmanager"}Quantity{/ts}</td>
                <td width="8%">{ts domain="org.stadtlandbeides.itemmanager"}Unit price{/ts}</td>
                <td width="8%">{ts domain="org.stadtlandbeides.itemmanager"}Total{/ts}</td>
                <td width="8%">{ts domain="org.stadtlandbeides.itemmanager"}Tax{/ts}</td>
                <td width="15%">{ts domain="org.stadtlandbeides.itemmanager"}Error{/ts}</td>
            </tr>
            </thead>
            <tbody id="futurescan-results-body">
            </tbody>
        </table>

        <div class="action-group" style="margin-top: 10px;">
            <button id="futurescan-update-btn" class="crm-button crm-form-submit" style="display:none;">
                <i class="crm-i fa-level-up"></i>
                {ts domain="org.stadtlandbeides.itemmanager"}Update Items{/ts}
            </button>
        </div>
    </div>
</div>

{crmScript ext="org.stadtlandbeides.itemmanager" file="js/futureContributionScanProgress.js"}
