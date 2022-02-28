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


    function setCheckContributionByDataIdent(identity,status)
    {
        var collection = document.getElementsByClassName('cm-toggle');
        for(var element in collection)
        {
            if(collection.hasOwnProperty(element))
                if(collection[element].dataset.hasOwnProperty('ident'))
                    if(collection[element].dataset.ident === identity)
                    {
                        collection[element].checked = status;
                        break;
                    }


        }

    }


    /**
     * Call the api function to relate a payment
     * @param back_reference reference with the payment infos
     */
    async function createPaymentbyLink(back_reference)
    {

        // show busy indicator
        cj("#separetry-text").hide();
        cj("#separetry-busy").show();

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

        while(working)
            await Sleep(1000)

        console.log('Add payment completed '  + payparam['contribution_id']);

        // show busy indicator
        cj("#separetry-text").show();
        cj("#separetry-busy").hide();

    }

    /**
     * Call the api function to delete a payment
     * @param delete_param reference with the delete infos
     */
    async function deletePaymentbyLink(delete_param, receive_date)
    {

        // show busy indicator
        cj("#separetry-text").hide();
        cj("#separetry-busy").show();


        var working = true;
        let entity = {}
        entity['entity_id'] = delete_param['contribution_id']
        console.log('Get Payment for contribution ' + delete_param['contribution_id']);

        var workarray = []
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
                    workarray.push(trx)

                    var item = trxs['values'][trx];
                    //check if we set the reference for ourself
                    if(!item.hasOwnProperty('trxn_id')) continue;
                    if (!item['trxn_id'].startsWith('SDD@')) continue;
                    let elements = item['trxn_id'].split('#finance#');
                    if(elements.length !== 2) continue;

                    var finance_sdd_id = elements[1];


                    let param = {};
                    param['id'] = item['id'];


                    let trx_id = item['trxn_id'];
                    console.log('Delete Payment' +  trx_id);
                    CRM.api3('Payment', 'delete',param).done(function(result) {

                        if(result['iserror']) {
                            CRM.alert(result['error_message'], ts('Delete SEPA payment relation'), 'error');
                            workarray.pop()
                            return;
                        }

                        CRM.alert(trx_id, ts('SEPA payment relation deleted'), 'success');
                        workarray.pop()

                    });

                }

            }

            working = false;

        });

        while(working)
            await Sleep(1000);

        while(workarray.length > 0)
            await Sleep(1000);

        working = true;

        let update_param = {};
        update_param['id'] = delete_param['contribution_id'];
        update_param['receive_date'] =  receive_date

        console.log('Update Contribution ' + delete_param['contribution_id']);
        CRM.api3('Contribution', 'create',update_param).done(function(result) {

            if(result['iserror']) {
                CRM.alert(result['error_message'], ts('Update Contribution'), 'error');
                working = false;
                return;
            }

            CRM.alert(ts('Date')+' '+ receive_date, ts('Contribution updated'), 'success');
            working = false;

        });

        while(working)
            await Sleep(1000);

        console.log('Delete payment completed for contribution ' +  delete_param['contribution_id']);

        // show busy indicator
        cj("#separetry-text").show();
        cj("#separetry-busy").hide();

    }

    /**
     * Add here the new payment per API for any direct link
     */
    $('body')
        .on('change.cm-toggle','#LinkPayment',function(e) {


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

                            deletePaymentbyLink(deleteparam, back[key_del]['add_payment']['contribution_date_copy']);
                        }

                    }

                }

                this.disabled = false;

            } catch (e) {
                CRM.alert('Try to add the payment failed ' + e);
                this.disabled = false;
                globalsepalinkwait = false;
            }




        }) //here start the group stuff
        .on('change.cm-toggle','#FinancialGrouplink', async function(e) {




            try {
                var fid_ident = this.dataset.financial;
                var back = CRM.vars['itemmanager_SEPA_backward_search'+fid_ident];
                var ischecked = this.checked;
                var checklist = [];

                this.disabled = true;

                //we want all related contributions
                for (var key in back) {

                    if(back.hasOwnProperty(key))
                    {

                        if (back[key]['entity'] === 'link' )
                        {
                            var ident = back[key]['element'];
                            checklist.push(ident);

                        }

                        if (back[key]['entity'] !== 'contr_cross')
                            continue;



                        //decide what to do
                        if (ischecked && !back[key]['is_direct_trxn'])
                        {

                            createPaymentbyLink(back[key]);
                        }

                    }

                }

                for(var link in checklist)
                {
                    if(checklist.hasOwnProperty(link))
                        setCheckContributionByDataIdent(checklist[link], true);
                }


                $(this).toggleClass('linked');



                this.disabled = false;

            } catch (e) {
                CRM.alert('Try to add the payments failed ' + e);
                this.disabled = false;
            }


        })
        .on('change','#SelectContribution', function(e)
        {
            alert('CLick')
            e.preventDefault();
        });

});