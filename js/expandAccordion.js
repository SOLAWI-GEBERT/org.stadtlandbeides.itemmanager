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
        .on('click.crm-accordion-wrapper', 'div.crm-accordion-wrapper', function(e) {

            var $body = $(this).children('div.crm-accordion-body').children('div');
            var $link = this.dataset.url;

            console.log($link)

            if ($(this).hasClass('collapsed')) {
                $body.children('div').children('div').remove();
            }
            else
            {
                CRM.loadPage($link, {target: $('div', $body).animate({minHeight: '3em'}, 'fast')});
            }

            $(this).toggleClass('expanded');
            e.preventDefault();

        });
});

