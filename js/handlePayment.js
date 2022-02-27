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


if (!String.prototype.startsWith) {
    Object.defineProperty(String.prototype, 'startsWith', {
        value: function(search, rawPos) {
            var pos = rawPos > 0 ? rawPos|0 : 0;
            return this.substring(pos, pos + search.length) === search;
        }
    });
}



cj(document).ready(function() {

   console.log('Dokument geladen');
    var fid_hidden = document.getElementById('financial_id');
    var fid = fid_hidden.dataset.financial;
    var back = CRM.vars['itemmanager_SEPA_backward_search'+fid];
    console.log(back)




});

CRM.$(function($) {

    function Sleep(milliseconds) {
        return new Promise(resolve => setTimeout(resolve, milliseconds));
    }

    /**
     * Call the api function to relate a payment
     * @param back_reference reference with the payment infos
     */
    async function createPaymentbyLink(back_reference)
    {


        if(!back_reference.hasOwnProperty('add_payment'))
                CRM.alert(ts('Could not find the payment data to add for ') + back_reference['element_cross_name'],
                    ts('Add SEPA payment relation'),'error')

        var payparam = back_reference['add_payment'];
        var working = true;

        console.log('Create Payment ' + payparam['contribution_id']);

        CRM.api3('Payment', 'create', payparam).done(function(result) {

            if(result['iserror']) {
                CRM.alert(result['error_message'], ts('Add SEPA payment relation'), 'error');
                working = false;
                return;
            }

            var trx = result;

            let update_param = {};
            update_param['id'] = payparam['contribution_id'];
            update_param['receive_date'] =  payparam['contribution_date_copy']

            console.log('Update Contribution ' + payparam['contribution_id']);
            CRM.api3('Contribution', 'create',update_param).done(function(result) {

                if(result['iserror']) {
                    CRM.alert(result['error_message'], ts('Add SEPA payment relation'), 'error');
                    working = false;
                    return;
                }

                CRM.alert(payparam['trxn_id'] + trx['id'] , ts('SEPA payment relation added'), 'success');
                working = false;

            });

        });

        console.log('Add payment completed '  + payparam['contribution_id']);

    }

    /**
     * Call the api function to delete a payment
     * @param delete_param reference with the delete infos
     */
    async function deletePaymentbyLink(delete_param)
    {

        var working = true;
        let entity = {}
        entity['entity_id'] = delete_param['contribution_id']
        console.log('GEt Payment ' + delete_param['contribution_id']);

        CRM.api3('Payment', 'get', entity).done(function(result) {

            if(result['iserror']) {
                CRM.alert(result['error_message'], ts('Delete SEPA payment relation'), 'error');
                working = false;
                return;
            }

            var trxs = result;

            for(var trx in trxs['values'])
            {
                if (trxs['values'].hasOwnProperty(trx)) {
                    var item = trxs['values'][trx];
                    //check if we set the reference for ourself
                    if(!item.hasOwnProperty('trxn_id')) continue;
                    if (!item['trxn_id'].startsWith('SDD@')) continue;
                    let elements = item['trxn_id'].split('#finance#');
                    if(elements.length !== 2) continue;

                    var finance_sdd_id = elements[1];


                    let param = {};
                    param['id'] = item['id'];

                    console.log('Delete Payment' +  + delete_param['contribution_id']);
                    let trx_id = item['trxn_id'];
                    CRM.api3('Payment', 'delete',param).done(function(result) {

                        if(result['iserror']) {
                            CRM.alert(result['error_message'], ts('Delete SEPA payment relation'), 'error');
                            working = false;
                            return;
                        }

                        CRM.alert(trx_id, ts('SEPA payment relation deleted'), 'success');


                    });

                }

            }

            working = false;

        });

        console.log('Delete payment completed ' +  delete_param['contribution_id']);

    }

    /**
     * Add here the new payment per API for any direct link
     */
    $('body')
        .on('change.cm-toggle','#LinkPayment',function(e) {


            // show busy indicator
            cj("#separetry-text").hide();
            cj("#separetry-busy").show();

            try {
                var ident = this.dataset.ident;
                var fid_hidden = document.getElementById('financial_id');
                var fid = fid_hidden.dataset.financial;
                var identarray = ident.split('_');
                var fid_ident = identarray[1];
                var back = CRM.vars['itemmanager_SEPA_backward_search'+fid_ident];
                var ischecked = this.checked;

                this.disabled = true;

                if (!ident in back) {
                    console.log('Could not relate the element' + ident + ' in backward search')
                    CRM.alert('Unknown element' + ident);
                    return;
                }
                var reference = back[ident];

                //decide what to do
                if (ischecked && !reference['is_direct_trxn'])
                {
                        //we want all related contributions
                        for (var key in back) {

                            if(back.hasOwnProperty(key))
                            {
                                if (back[key]['entity'] !== 'contr_cross' ||
                                    back[key]['reference_month'] !== reference['reference_month'])
                                        continue;
                                createPaymentbyLink(back[key]);
                            }

                    }
                }
                else if(!ischecked && reference['is_direct_trxn'])
                {
                    //we want all related contributions
                    for (var key_del in back) {

                        if(back.hasOwnProperty(key_del))
                        {
                            if (back[key_del]['entity'] !== 'contr_cross' ||
                                back[key_del]['reference_month'] !== reference['reference_month'])
                                continue;
                            //double check

                            if(!back[key_del]['is_trxn']) continue;

                            let deleteparam = {};
                            deleteparam['contribution_id'] = back[key_del]['contribution_id'];
                            deleteparam['financial_id'] = reference['financial_id'];

                            deletePaymentbyLink(deleteparam);
                        }

                    }

                }

                this.disabled = false;

            } catch (e) {
                CRM.alert('Try to add the payment failed ' + e);
                this.disabled = false;
                globalsepalinkwait = false;
            }


            // show busy indicator
            cj("#separetry-text").show();
            cj("#separetry-busy").hide();

        }) //here start the group stuff
        .on('change.cm-toggle','#FinancialGrouplink', async function(e) {

            // show busy indicator
            cj("#separetry-text").hide();
            cj("#separetry-busy").show();

            while(globalsepalinkwait)
                await Sleep(1000);

            try {
                var fid_ident = this.dataset.financial;
                var back = CRM.vars['itemmanager_SEPA_backward_search'+fid_ident];
                var ischecked = this.checked;

                this.disabled = true;

                //we want all related contributions
                for (var key in back) {

                    if(back.hasOwnProperty(key))
                    {
                        if (back[key]['entity'] !== 'contr_cross')
                            continue;

                        //decide what to do
                        if (ischecked && !back[key]['is_direct_trxn'])
                        {
                            createPaymentbyLink(back[key]);
                        }

                    }

                }
                $(this).toggleClass('linked');
                // show busy indicator
                cj("#separetry-text").show();
                cj("#separetry-busy").hide();

                this.disabled = false;

            } catch (e) {
                CRM.alert('Try to add the payments failed ' + e);
                this.disabled = false;
            }


        });

});