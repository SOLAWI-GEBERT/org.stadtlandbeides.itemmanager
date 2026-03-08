CRM.$(function($) {
    $(document).on('click', 'div.crm-accordion-header[data-url]', function() {
        var $header = $(this);
        if ($header.hasClass('loaded'))
            return;

        var url = $header.data('url');
        if (!url)
            return;

        var $body = $header.next('div.crm-accordion-body').find('div.crm-clear');

        CRM.loadPage(url, {target: $body.animate({minHeight: '3em'}, 'fast')});

        $header.addClass('loaded');
    });
});
