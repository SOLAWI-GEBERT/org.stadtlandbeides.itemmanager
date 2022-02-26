/*
 * ------------------------------------------------------------+
 * | stadt, land, beides - CSA Support                                       |
 * | Copyright (C) 2021 Stadt, Land, Beides                               |
 * | Author: A. Gebert (webmaster -at- stadt-land-beides.de)  |
 * | https://stadt-land-beides.de/                                              |
 * +-------------------------------------------------------------+
 * | This program is released as free software under the          |
 * | Affero GPL license. You can redistribute it and/or               |
 * | modify it under the terms of this license which you            |
 * | can read by viewing the included agpl.txt or online             |
 * | at www.gnu.org/licenses/agpl.html. Removal of this            |
 * | copyright header is strictly prohibited without                    |
 * | written permission from the original author(s).                   |
 * +-------------------------------------------------------------
 */

CRM.$(function($) {
    $('body')
        .off('#AccordionExpander')
        .on('click.crm-accordion-header', 'div.crm-accordion-header','#AccordionExpander', function(e) {

            var $body = $(this).next('div.crm-accordion-body').children('div');
            var $link = this.dataset.url;

            console.log($link)

            if ($(this).hasClass('loaded'))
                return;

            CRM.loadPage($link, {target: $('div', $body).animate({minHeight: '3em'}, 'fast')});

            $(this).toggleClass('loaded');
            e.preventDefault();

        });
});

