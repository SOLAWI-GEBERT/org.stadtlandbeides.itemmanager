CRM.$(function($) {
    var analyzeUrl = $('#futurescan-analyze-url').val();
    var allItems = [];
    var batchSize = 10;
    var referenceItems = null;

    $('#futurescan-start-analysis').on('click', function(e) {
        e.preventDefault();
        allItems = [];
        referenceItems = null;
        $('#futurescan-results-area').hide();
        $('#futurescan-stats-area').hide();
        $('#futurescan-progress-area').show();
        $('#futurescan-progress-bar').css('width', '0%');
        $('#futurescan-progress-label').text(ts('Starting scan...'));
        $('#futurescan-start-analysis').prop('disabled', true);

        runBatch(0);
    });

    function getFilters() {
        return {
            filter_sync: $('#filter_sync').is(':checked') ? 1 : 0,
            filter_harmonize: $('#filter_harmonize').is(':checked') ? 1 : 0,
            filter_orphan: $('#filter_orphan').is(':checked') ? 1 : 0,
            filter_composition: $('#filter_composition').is(':checked') ? 1 : 0,
            date_from: $('#date_from').val()
        };
    }

    function runBatch(offset) {
        var filters = getFilters();
        var data = {
            offset: offset,
            limit: batchSize,
            filter_sync: filters.filter_sync,
            filter_harmonize: filters.filter_harmonize,
            filter_orphan: filters.filter_orphan,
            filter_composition: filters.filter_composition,
            date_from: filters.date_from
        };
        // Forward reference items from first batch to subsequent batches
        if (filters.filter_composition && referenceItems) {
            data.reference_items = JSON.stringify(referenceItems);
        }

        $.ajax({
            url: analyzeUrl,
            type: 'GET',
            dataType: 'json',
            data: data
        })
        .done(function(response) {
            if (response.items && response.items.length > 0) {
                allItems = allItems.concat(response.items);
            }
            // Capture reference items from first batch
            if (response.reference_items && !referenceItems) {
                referenceItems = response.reference_items;
            }

            var pct = response.total > 0
                ? Math.round((response.processed / response.total) * 100)
                : 100;
            $('#futurescan-progress-bar').css('width', pct + '%');
            $('#futurescan-progress-label').text(
                ts('Scanned %1 of %2 contributions...', {1: response.processed, 2: response.total})
            );

            if (response.done) {
                analysisComplete();
            } else {
                runBatch(response.processed);
            }
        })
        .fail(function() {
            $('#futurescan-progress-bar').css({'width': '100%', 'background': '#f44336'});
            $('#futurescan-progress-label').text(ts('Connection error. Please try again.'));
            $('#futurescan-start-analysis').prop('disabled', false);
        });
    }

    function analysisComplete() {
        $('#futurescan-progress-bar').css('width', '100%');
        $('#futurescan-progress-label').text(ts('Scan complete.'));
        $('#futurescan-start-analysis').prop('disabled', false);

        showStatistics();
        renderResults();
    }

    function showStatistics() {
        var stats = {
            totalItems: allItems.length,
            labelChanges: 0,
            priceChanges: 0,
            dateChanges: 0,
            orphans: 0,
            missingItems: 0,
            extraItems: 0,
            qtyMismatches: 0
        };

        for (var i = 0; i < allItems.length; i++) {
            var item = allItems[i];
            if (item.update_label) stats.labelChanges++;
            if (item.update_price) stats.priceChanges++;
            if (item.update_date) stats.dateChanges++;
            if (item.empty_relation_id) stats.orphans++;
            if (item.missing_item) stats.missingItems++;
            if (item.extra_item) stats.extraItems++;
            if (item.qty_sync) stats.qtyMismatches++;
        }

        var html = '<table class="report-layout">' +
            '<tr><td><strong>' + ts('Total affected items') + '</strong></td><td>' + stats.totalItems + '</td></tr>' +
            '<tr><td><strong>' + ts('Label changes') + '</strong></td><td>' + stats.labelChanges + '</td></tr>' +
            '<tr><td><strong>' + ts('Price changes') + '</strong></td><td>' + stats.priceChanges + '</td></tr>' +
            '<tr><td><strong>' + ts('Date changes') + '</strong></td><td>' + stats.dateChanges + '</td></tr>' +
            '<tr><td><strong>' + ts('Orphaned relations') + '</strong></td><td>' + stats.orphans + '</td></tr>' +
            '<tr><td><strong>' + ts('Missing items') + '</strong></td><td>' + stats.missingItems + '</td></tr>' +
            '<tr><td><strong>' + ts('Extra items') + '</strong></td><td>' + stats.extraItems + '</td></tr>' +
            '<tr><td><strong>' + ts('Qty mismatches') + '</strong></td><td>' + stats.qtyMismatches + '</td></tr>' +
            '</table>';

        $('#futurescan-stats-content').html(html);
        $('#futurescan-stats-area').show();
    }

    function renderResults() {
        if (allItems.length === 0) {
            $('#futurescan-results-body').html(
                '<tr><td colspan="9" class="help">' +
                ts('No inconsistencies found in future contributions.') +
                '</td></tr>'
            );
            $('#futurescan-results-area').show();
            $('#futurescan-update-btn').hide();
            return;
        }

        var html = '';
        for (var i = 0; i < allItems.length; i++) {
            var item = allItems[i];

            // Orphan rows
            if (item.empty_relation_id) {
                html += '<tr class="' + (i % 2 === 0 ? 'odd-row' : 'even-row') + ' orphan-row">' +
                    '<td><label class="orphan-delete-label">' +
                        '<input type="checkbox" name="deletelist[]" value="' + item.empty_relation_id + '"/>' +
                        ' ' + ts('Delete') + '</label></td>' +
                    '<td>&mdash;</td>' +
                    '<td>' + escHtml(item.member_name || '') + '</td>' +
                    '<td>&mdash;</td>' +
                    '<td>&mdash;</td>' +
                    '<td>&mdash;</td>' +
                    '<td>&mdash;</td>' +
                    '<td>&mdash;</td>' +
                    '<td><span class="orphan-error">' + escHtml(item.change_error || '') + '</span></td>' +
                    '</tr>';
                continue;
            }

            // Missing item rows
            if (item.missing_item) {
                var mi = item.missing_item;
                html += '<tr class="' + (i % 2 === 0 ? 'odd-row' : 'even-row') + ' missing-row">' +
                    '<td><input type="checkbox" name="addlist[]" value=\'' + escAttr(JSON.stringify(mi)) + '\'/></td>' +
                    '<td>' + escHtml(item.contrib_date || '') + '</td>' +
                    '<td>' + escHtml(item.member_name || '') + '</td>' +
                    '<td><span class="missing-label">' + escHtml(mi.label || '') + '</span></td>' +
                    '<td>' + (mi.qty || '') + '</td>' +
                    '<td>' + formatMoney4(item.change_price) + '</td>' +
                    '<td>' + formatMoney4(item.change_total) + '</td>' +
                    '<td>' + formatMoney4(item.change_tax) + '</td>' +
                    '<td><span class="missing-label">' + escHtml(item.change_error || '') + '</span></td>' +
                    '</tr>';
                continue;
            }

            // Extra item rows
            if (item.extra_item) {
                html += '<tr class="' + (i % 2 === 0 ? 'odd-row' : 'even-row') + ' extra-row">' +
                    '<td><input type="checkbox" name="cancellist[]" value="' + item.extra_item + '"/></td>' +
                    '<td>' + escHtml(item.contrib_date || '') + '</td>' +
                    '<td>' + escHtml(item.member_name || '') + '</td>' +
                    '<td><span class="extra-label">' + escHtml(item.item_label || '') + '</span></td>' +
                    '<td>' + escHtml(item.item_quantity || '') + '</td>' +
                    '<td>' + formatMoney4(item.item_price) + '</td>' +
                    '<td>' + formatMoney4(item.item_total) + '</td>' +
                    '<td>' + formatMoney4(item.item_tax) + '</td>' +
                    '<td><span class="extra-label">' + escHtml(item.change_error || '') + '</span></td>' +
                    '</tr>';
                continue;
            }

            // Qty mismatch rows
            if (item.qty_sync) {
                var qs = item.qty_sync;
                html += '<tr class="' + (i % 2 === 0 ? 'odd-row' : 'even-row') + ' missing-row">' +
                    '<td><input type="checkbox" name="syncqtylist[]" value=\'' + escAttr(JSON.stringify(qs)) + '\'/></td>' +
                    '<td>' + escHtml(item.contrib_date || '') + '</td>' +
                    '<td>' + escHtml(item.member_name || '') + '</td>' +
                    '<td>' + escHtml(item.item_label || '') + '</td>' +
                    '<td><span class="changed_data">' + item.item_quantity +
                        '<br/>' + ts('change to') + '<br/>' + qs.target_qty + '</span></td>' +
                    '<td>' + formatMoney4(item.item_price) + '</td>' +
                    '<td>' + formatMoney4(item.change_total) + '</td>' +
                    '<td>' + formatMoney4(item.change_tax) + '</td>' +
                    '<td><span class="missing-label">' + escHtml(item.change_error || '') + '</span></td>' +
                    '</tr>';
                continue;
            }

            // Regular item rows
            html += '<tr class="' + (i % 2 === 0 ? 'odd-row' : 'even-row') + '">';
            html += '<td><input type="checkbox" name="viewlist[]" value="' + item.line_id + '"/></td>';

            // Date
            if (item.update_date) {
                html += '<td><span class="changed_data">' + escHtml(item.contrib_date) +
                    '<br/>' + ts('change to') + '<br/>' + escHtml(item.change_date) + '</span></td>';
            } else {
                html += '<td>' + escHtml(item.contrib_date || '') + '</td>';
            }

            // Membership
            html += '<td>' + escHtml(item.member_name || '') + '</td>';

            // Item label
            if (item.update_label) {
                html += '<td><span class="changed_data">' + escHtml(item.item_label || '') +
                    '<br/>' + ts('change to') + '<br/>' + escHtml(item.change_label || '') + '</span></td>';
            } else {
                html += '<td>' + escHtml(item.item_label || '') + '</td>';
            }

            html += '<td>' + escHtml(item.item_quantity || '') + '</td>';

            // Price
            if (item.update_price) {
                html += '<td><span class="changed_data">' + formatMoney4(item.item_price) +
                    '<br/>' + ts('change to') + '<br/>' + formatMoney4(item.change_price) + '</span></td>';
                html += '<td><span class="changed_data">' + formatMoney4(item.item_total) +
                    '<br/>' + ts('change to') + '<br/>' + formatMoney4(item.change_total) + '</span></td>';
                html += '<td><span class="changed_data">' + formatMoney4(item.item_tax) +
                    '<br/>' + ts('change to') + '<br/>' + formatMoney4(item.change_tax) + '</span></td>';
            } else {
                html += '<td>' + formatMoney4(item.item_price) + '</td>';
                html += '<td>' + formatMoney4(item.item_total) + '</td>';
                html += '<td>' + formatMoney4(item.item_tax) + '</td>';
            }

            html += '<td><span class="changed_data">' + escHtml(item.change_error || '') + '</span></td>';
            html += '</tr>';
        }

        $('#futurescan-results-body').html(html);
        $('#futurescan-results-area').show();
        $('#futurescan-update-btn').show();
    }

    // Select all
    $('#select_all').on('change', function() {
        var checked = $(this).is(':checked');
        $('input[name="viewlist[]"], input[name="deletelist[]"], input[name="addlist[]"], input[name="cancellist[]"], input[name="syncqtylist[]"]').prop('checked', checked);
    });

    // Update button
    $('#futurescan-update-btn').on('click', function(e) {
        e.preventDefault();

        var viewlist = [];
        var deletelist = [];
        var addlist = [];
        var cancellist = [];
        var syncqtylist = [];
        $('input[name="viewlist[]"]:checked').each(function() {
            viewlist.push($(this).val());
        });
        $('input[name="deletelist[]"]:checked').each(function() {
            deletelist.push($(this).val());
        });
        $('input[name="addlist[]"]:checked').each(function() {
            addlist.push(JSON.parse($(this).val()));
        });
        $('input[name="cancellist[]"]:checked').each(function() {
            cancellist.push($(this).val());
        });
        $('input[name="syncqtylist[]"]:checked').each(function() {
            syncqtylist.push(JSON.parse($(this).val()));
        });

        if (viewlist.length === 0 && deletelist.length === 0 && addlist.length === 0 && cancellist.length === 0 && syncqtylist.length === 0) {
            CRM.alert(ts('No items selected.'), ts('Info'), 'info');
            return;
        }

        var filters = getFilters();
        $(this).prop('disabled', true);
        var $btn = $(this);

        $.ajax({
            url: analyzeUrl,
            type: 'POST',
            contentType: 'application/json',
            dataType: 'json',
            data: JSON.stringify({
                filter_sync: filters.filter_sync,
                filter_harmonize: filters.filter_harmonize,
                viewlist: viewlist,
                deletelist: deletelist,
                addlist: addlist,
                cancellist: cancellist,
                syncqtylist: syncqtylist
            })
        })
        .done(function(response) {
            var msgs = [];
            if (response.updated > 0)
                msgs.push(ts('%1 item(s) updated.', {1: response.updated}));
            if (response.deleted > 0)
                msgs.push(ts('%1 orphan(s) deleted.', {1: response.deleted}));
            if (response.added > 0)
                msgs.push(ts('%1 item(s) added.', {1: response.added}));
            if (response.cancelled > 0)
                msgs.push(ts('%1 item(s) cancelled.', {1: response.cancelled}));
            if (response.synced > 0)
                msgs.push(ts('%1 qty synced.', {1: response.synced}));
            if (response.errors && response.errors.length > 0)
                msgs.push(ts('%1 error(s).', {1: response.errors.length}));

            if (response.errors && response.errors.length > 0) {
                CRM.alert(msgs.join(' ') + '<br/>' + response.errors.join('<br/>'), ts('Update'), 'warning');
            } else {
                CRM.alert(msgs.join(' '), ts('Success'), 'success');
            }
            $btn.prop('disabled', false);

            // Refresh the parent page (contribution list) when changes were made
            var hasChanges = (response.updated || 0) + (response.deleted || 0) +
                (response.added || 0) + (response.cancelled || 0) + (response.synced || 0);
            if (hasChanges > 0) {
                CRM.refreshParent('#futurescan-update-btn');
            }
        })
        .fail(function() {
            CRM.alert(ts('Connection error. Please try again.'), ts('Error'), 'error');
            $btn.prop('disabled', false);
        });
    });

    function formatMoney4(val) {
        var n = parseFloat(val);
        return isNaN(n) ? '0.0000' : n.toFixed(4);
    }

    function escHtml(str) {
        if (!str && str !== 0) return '';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(String(str)));
        return div.innerHTML;
    }

    function escAttr(str) {
        if (!str) return '';
        return str.replace(/&/g, '&amp;').replace(/'/g, '&#39;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }
});
