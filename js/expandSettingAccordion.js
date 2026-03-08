CRM.$(function($) {
    $('body')
        .on('click.crm-accordion-header', 'div.crm-accordion-header', '#SettingAccordionExpander', function(e) {
            var $body = $(this).next('div.crm-accordion-body').children('div');
            var $link = this.dataset.url;

            if (!$link || $(this).hasClass('loaded'))
                return;

            CRM.loadPage($link, {target: $('div', $body).animate({minHeight: '3em'}, 'fast')});

            $(this).toggleClass('loaded');
            e.preventDefault();
        });
});
