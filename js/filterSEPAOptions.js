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
    $('#SEPAFilterOpen')
        .on('click', function(e) {

            console.log('click open option');
            var option_element = document.getElementById('SEPAFilterOptionUrl');
            var openfilter = this.checked ? 'filteropen=1':'filteropen=0';
            var past_element = document.getElementById('SEPAFilterPast');
            var pastfilter = past_element.checked ? 'filterfuture=1':'filterfuture=0';
            option_element.dataset.filter =openfilter+'&'+ pastfilter;

        });

    $('#SEPAFilterPast')
        .on('click', function(e) {

            console.log('click past option');
            var option_element = document.getElementById('SEPAFilterOptionUrl');
            var pastfilter = this.checked ? 'filterfuture=1':'filterfuture=0';
            var open_element = document.getElementById('SEPAFilterOpen');
            var openfilter = open_element.checked ? 'filteropen=1':'filteropen=0';
            option_element.dataset.filter =openfilter+'&'+ pastfilter;

        });
});