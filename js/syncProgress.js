CRM.$(function($) {
    $(document).on('click', '#itemmanager-sync-btn', function(e) {
        e.preventDefault();

        var syncUrl = $(this).data('sync-url');
        var settingsUrl = $(this).data('settings-url');

        // Create progress bar overlay
        var $overlay = $(
            '<div id="itemmanager-sync-overlay" style="' +
                'position:fixed;top:0;left:0;width:100%;height:100%;' +
                'background:rgba(0,0,0,0.5);z-index:10000;' +
                'display:flex;align-items:center;justify-content:center;">' +
            '<div style="background:#fff;padding:30px 40px;border-radius:8px;' +
                'min-width:400px;box-shadow:0 4px 20px rgba(0,0,0,0.3);text-align:center;">' +
                '<h3 id="sync-status-text" style="margin:0 0 15px 0;">' + ts('Synchronizing...') + '</h3>' +
                '<div style="background:#e0e0e0;border-radius:4px;height:24px;overflow:hidden;margin-bottom:10px;">' +
                    '<div id="sync-progress-bar" style="' +
                        'width:0%;height:100%;background:#4CAF50;border-radius:4px;' +
                        'transition:width 0.3s ease;"></div>' +
                '</div>' +
                '<div id="sync-progress-label" style="color:#666;font-size:0.9em;">' + ts('Please wait...') + '</div>' +
            '</div>' +
            '</div>'
        );

        $('body').append($overlay);

        // Animate progress bar while waiting
        var progress = 0;
        var progressInterval = setInterval(function() {
            // Slow down as we approach 90%
            if (progress < 30) {
                progress += 3;
            } else if (progress < 60) {
                progress += 2;
            } else if (progress < 85) {
                progress += 0.5;
            }
            $('#sync-progress-bar').css('width', Math.min(progress, 85) + '%');
        }, 200);

        $.ajax({
            url: syncUrl,
            type: 'GET',
            dataType: 'json'
        })
        .done(function(response) {
            clearInterval(progressInterval);

            if (response.is_error) {
                $('#sync-progress-bar').css({'width': '100%', 'background': '#f44336'});
                $('#sync-status-text').text(ts('Errors occurred'));
                var msgHtml = '';
                $.each(response.messages, function(i, msg) {
                    msgHtml += '<div style="color:#c00;text-align:left;font-size:0.85em;margin-top:5px;">' + msg + '</div>';
                });
                $('#sync-progress-label').html(msgHtml + '<div style="margin-top:15px;"><a href="' + settingsUrl + '" class="button">' + ts('Back') + '</a></div>');
            } else {
                $('#sync-progress-bar').css('width', '100%');
                $('#sync-status-text').text(ts('Synchronized!'));
                $('#sync-progress-label').text(ts('Reloading...'));
                setTimeout(function() {
                    window.location.href = settingsUrl;
                }, 800);
            }
        })
        .fail(function() {
            clearInterval(progressInterval);
            $('#sync-progress-bar').css({'width': '100%', 'background': '#f44336'});
            $('#sync-status-text').text(ts('Error'));
            $('#sync-progress-label').html(
                '<div style="color:#c00;">' + ts('Connection error. Please try again.') + '</div>' +
                '<div style="margin-top:15px;"><a href="' + settingsUrl + '" class="button">' + ts('Back') + '</a></div>'
            );
        });
    });
});
