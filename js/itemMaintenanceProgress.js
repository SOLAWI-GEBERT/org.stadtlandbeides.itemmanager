CRM.$(function($) {
    var analyzeUrl = $('#itemmanager-analyze-url').val();
    var allItems = [];
    var batchSize = 10;

    $('#itemmanager-start-analysis').on('click', function(e) {
        e.preventDefault();
        allItems = [];
        $('#itemmanager-results-area').hide();
        $('#itemmanager-stats-area').hide();
        $('#itemmanager-progress-area').show();
        $('#itemmanager-progress-bar').css('width', '0%');
        $('#itemmanager-progress-label').text(ts('Starting analysis...'));
        $('#itemmanager-start-analysis').prop('disabled', true);

        runBatch(0);
    });

    // Tag dropdown toggle
    $('#tag-dropdown-toggle').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $('#tag-dropdown-panel').toggle();
    });

    $(document).on('click', function(e) {
        if (!$(e.target).closest('.itemmanager-tag-dropdown').length) {
            $('#tag-dropdown-panel').hide();
        }
    });

    // Update count badge when checkboxes change
    $(document).on('change', '.filter-tag-cb', function() {
        var count = $('.filter-tag-cb:checked').length;
        $('#tag-dropdown-count').text(count > 0 ? count : '');
    });

    function getFilters() {
        var excludeTags = [];
        $('.filter-tag-cb:checked').each(function() {
            excludeTags.push($(this).val());
        });
        return {
            filter_sync: $('#filter_sync').is(':checked') ? 1 : 0,
            filter_harmonize: $('#filter_harmonize').is(':checked') ? 1 : 0,
            filter_orphan: $('#filter_orphan').is(':checked') ? 1 : 0,
            date_from: $('#date_from').val(),
            exclude_tag_ids: excludeTags.join(',')
        };
    }

    function runBatch(offset) {
        var filters = getFilters();
        $.ajax({
            url: analyzeUrl,
            type: 'GET',
            dataType: 'json',
            data: {
                mode: 'analyze',
                offset: offset,
                limit: batchSize,
                filter_sync: filters.filter_sync,
                filter_harmonize: filters.filter_harmonize,
                filter_orphan: filters.filter_orphan,
                date_from: filters.date_from,
                exclude_tag_ids: filters.exclude_tag_ids
            }
        })
        .done(function(response) {
            if (response.items && response.items.length > 0) {
                allItems = allItems.concat(response.items);
            }

            var pct = response.total > 0
                ? Math.round((response.processed / response.total) * 100)
                : 100;
            $('#itemmanager-progress-bar').css('width', pct + '%');
            $('#itemmanager-progress-label').text(
                ts('Analyzed %1 of %2 contacts...', {1: response.processed, 2: response.total})
            );

            if (response.done) {
                analysisComplete();
            } else {
                runBatch(response.processed);
            }
        })
        .fail(function() {
            $('#itemmanager-progress-bar').css({'width': '100%', 'background': '#f44336'});
            $('#itemmanager-progress-label').text(ts('Connection error. Please try again.'));
            $('#itemmanager-start-analysis').prop('disabled', false);
        });
    }

    function analysisComplete() {
        $('#itemmanager-progress-bar').css('width', '100%');
        $('#itemmanager-progress-label').text(ts('Analysis complete.'));
        $('#itemmanager-start-analysis').prop('disabled', false);

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
            contactCount: 0
        };

        var contactSet = {};
        for (var i = 0; i < allItems.length; i++) {
            var item = allItems[i];
            contactSet[item.contact_id] = true;
            if (item.update_label) stats.labelChanges++;
            if (item.update_price) stats.priceChanges++;
            if (item.update_date) stats.dateChanges++;
            if (item.empty_relation_id) stats.orphans++;
        }
        stats.contactCount = Object.keys(contactSet).length;

        var html = '<table class="report-layout">' +
            '<tr><td><strong>' + ts('Affected contacts') + '</strong></td><td>' + stats.contactCount + '</td></tr>' +
            '<tr><td><strong>' + ts('Total affected items') + '</strong></td><td>' + stats.totalItems + '</td></tr>' +
            '<tr><td><strong>' + ts('Label changes') + '</strong></td><td>' + stats.labelChanges + '</td></tr>' +
            '<tr><td><strong>' + ts('Price changes') + '</strong></td><td>' + stats.priceChanges + '</td></tr>' +
            '<tr><td><strong>' + ts('Date changes') + '</strong></td><td>' + stats.dateChanges + '</td></tr>' +
            '<tr><td><strong>' + ts('Orphaned relations') + '</strong></td><td>' + stats.orphans + '</td></tr>' +
            '</table>';

        $('#itemmanager-stats-content').html(html);
        $('#itemmanager-stats-area').show();
    }

    function renderResults() {
        if (allItems.length === 0) {
            $('#itemmanager-results-body').html(
                '<tr><td colspan="11" class="help">' +
                ts('No items found that need updating. Try different filter options.') +
                '</td></tr>'
            );
            $('#itemmanager-results-area').show();
            $('#itemmanager-update-btn').hide();
            return;
        }

        var html = '';
        for (var i = 0; i < allItems.length; i++) {
            var item = allItems[i];

            if (item.empty_relation_id) {
                html += '<tr class="' + (i % 2 === 0 ? 'odd-row' : 'even-row') + ' orphan-row">' +
                    '<td><label class="orphan-delete-label">' +
                        '<input type="checkbox" name="deletelist[]" value="' + item.empty_relation_id + '"/>' +
                        ' ' + ts('Delete') + '</label></td>' +
                    '<td>&mdash;</td>' +
                    '<td>' + escHtml(item.display_name) + '</td>' +
                    '<td>' + renderTags(item.tags) + '</td>' +
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

            html += '<tr class="' + (i % 2 === 0 ? 'odd-row' : 'even-row') + '">';
            html += '<td><input type="checkbox" name="viewlist[]" value="' + item.line_id + '"/></td>';

            // Date
            if (item.update_date) {
                html += '<td><span class="changed_data">' + escHtml(item.contrib_date) +
                    '<br/>' + ts('change to') + '<br/>' + escHtml(item.change_date) + '</span></td>';
            } else {
                html += '<td>' + escHtml(item.contrib_date || '') + '</td>';
            }

            // Contact
            html += '<td><a href="' + CRM.url('civicrm/contact/view', {reset: 1, cid: item.contact_id}) + '" target="_blank">' +
                escHtml(item.display_name || '') + '</a></td>';

            // Tags
            html += '<td>' + renderTags(item.tags) + '</td>';

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
                html += '<td><span class="changed_data">' + item.item_price +
                    '<br/>' + ts('change to') + '<br/>' + item.change_price + '</span></td>';
                html += '<td><span class="changed_data">' + item.item_total +
                    '<br/>' + ts('change to') + '<br/>' + item.change_total + '</span></td>';
                html += '<td><span class="changed_data">' + item.item_tax +
                    '<br/>' + ts('change to') + '<br/>' + item.change_tax + '</span></td>';
            } else {
                html += '<td>' + (item.item_price || '') + '</td>';
                html += '<td>' + (item.item_total || '') + '</td>';
                html += '<td>' + (item.item_tax || '') + '</td>';
            }

            html += '<td><span class="changed_data">' + escHtml(item.change_error || '') + '</span></td>';
            html += '</tr>';
        }

        $('#itemmanager-results-body').html(html);
        $('#itemmanager-results-area').show();
        $('#itemmanager-update-btn').show();
    }

    // Select all
    $('#select_all').on('change', function() {
        var checked = $(this).is(':checked');
        $('input[name="viewlist[]"], input[name="deletelist[]"]').prop('checked', checked);
    });

    // Update button
    $('#itemmanager-update-btn').on('click', function(e) {
        e.preventDefault();

        var viewlist = [];
        var deletelist = [];
        $('input[name="viewlist[]"]:checked').each(function() {
            viewlist.push($(this).val());
        });
        $('input[name="deletelist[]"]:checked').each(function() {
            deletelist.push($(this).val());
        });

        if (viewlist.length === 0 && deletelist.length === 0) {
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
                mode: 'update',
                filter_sync: filters.filter_sync,
                filter_harmonize: filters.filter_harmonize,
                viewlist: viewlist,
                deletelist: deletelist
            })
        })
        .done(function(response) {
            var msgs = [];
            if (response.updated > 0)
                msgs.push(ts('%1 item(s) updated.', {1: response.updated}));
            if (response.deleted > 0)
                msgs.push(ts('%1 orphan(s) deleted.', {1: response.deleted}));
            if (response.errors && response.errors.length > 0)
                msgs.push(ts('%1 error(s).', {1: response.errors.length}));

            if (response.errors && response.errors.length > 0) {
                CRM.alert(msgs.join(' ') + '<br/>' + response.errors.join('<br/>'), ts('Update'), 'warning');
            } else {
                CRM.alert(msgs.join(' '), ts('Success'), 'success');
            }
            $btn.prop('disabled', false);
        })
        .fail(function() {
            CRM.alert(ts('Connection error. Please try again.'), ts('Error'), 'error');
            $btn.prop('disabled', false);
        });
    });

    function renderTags(tags) {
        if (!tags || tags.length === 0) return '';
        var html = '';
        for (var i = 0; i < tags.length; i++) {
            html += '<span class="itemmanager-tag">' + escHtml(tags[i].label || '') + '</span>';
        }
        return html;
    }

    function escHtml(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }
});
