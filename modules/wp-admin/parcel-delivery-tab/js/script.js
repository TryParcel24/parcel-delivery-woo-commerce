var pdMethod = pdVar.pd_shipping_calculator_method

var pdPickupLocation = (pdVar.pd_pickup_location && pdVar.pd_pickup_location.pickup_block_json && pdVar.pd_pickup_location.pickup_block_json.id) ? pdVar.pd_pickup_location.pickup_block_json.id : '';

var manualBlockSelect = jQuery('#pd_manual_block');

var apiBlockSelect = jQuery('#pd_api_block');

var pickup_block = jQuery('#pickup_block');

var manualBlocksInfoJson = [];

var apiBlocksInfoJson = [];

var manualBlocksInfo = pdVar.pd_manual_blocks_info

var apiBlocksInfo = pdVar.pd_api_blocks_info

var pd_all_blocks_info = pdVar.pd_all_blocks_info

var allBlocks;

var manual_block_table ;

var api_block_table ;



jQuery(document).ready(function () {



    

    var form = jQuery('.pd_shipcal_container').parents('form')

    jQuery('.pd_shipcal_container').parents('form').attr('novalidate','')

    

    manualBlockSelect.add(apiBlockSelect).on("change", function () {

        changeOnBlockSelectElement(jQuery(this));

    });

    pickup_block.on("change", function () {

        var id = jQuery(this).val();

        var index = (pd_all_blocks_info.block_data).findIndex(x => x.id === parseInt(id));

        if (index != -1) {

            pdVar.pickup_location = pd_all_blocks_info.block_data[index];

        }

    });



    // drawBlocksTable({'manual':manualBlocksInfo,'api':apiBlocksInfo});

    

    // select2Initialization({'manual':manualBlockSelect,'api':apiBlockSelect,'pickup_location':pickup_location});

    loadAllBlocks(['manual','api'])

    drawBlocksTable({'manual':manualBlocksInfoJson,'api':apiBlocksInfoJson});

    select2Initialization({'pickup_block':pickup_block});

    // jQuery.fn.DataTable.ext.pager.numbers_length = 5;

    manual_block_table = loadDatatable('.manual_blocks_table_container .custom-pagination');

    api_block_table = loadDatatable('.api_blocks_table_container .custom-pagination');

    

    jQuery(document).on('change', 'input[name="manual_block_status"], input[name="api_block_status"]', function () {

        

        var tr = jQuery(this).parents('tr').attr('id');

        var method = jQuery(this).parents('tr').data('method');        

        var BlocksInfoJson = (method == 'api') ? apiBlocksInfoJson : manualBlocksInfoJson;

        var index = BlocksInfoJson.findIndex(x => x.id === parseInt(tr));

       

        if (index != -1) {

            if (jQuery(this).is(':checked')) {

                BlocksInfoJson[index].status = true

                // if(jQuery(`.${method}BlockCheck`).length === jQuery(`.${method}BlockCheck:checked`).length)

                //     jQuery(`input[name="${method}_all_block"]`).prop("checked", true);

            } else {

                BlocksInfoJson[index].status = false

                // jQuery(`input[name="${method}_all_block"]`).prop("checked", false);

            }

        }

    })



    jQuery(document).on('change', 'input[name="manual_block_min_order"], input[name="api_block_min_order"]', function () {

        var tr = jQuery(this).parents('tr').attr('id');

        var method = jQuery(this).parents('tr').data('method');

        var BlocksInfoJson = (method == 'api') ? apiBlocksInfoJson : manualBlocksInfoJson;

        var index = BlocksInfoJson.findIndex(x => x.id === parseInt(tr));

        if (index != -1) {

            BlocksInfoJson[index].min_order = parseFloat(jQuery(this).val())

        }

    })



    jQuery(document).on('change', 'input[name="manual_block_charge"], input[name="api_block_charge"]', function () {

        console.log('working')

        var tr = jQuery(this).parents('tr').attr('id');

        var method = jQuery(this).parents('tr').data('method');

        var BlocksInfoJson = (method == 'api') ? apiBlocksInfoJson : manualBlocksInfoJson;

        var index = BlocksInfoJson.findIndex(x => x.id === parseInt(tr));

        if (index != -1) {

            BlocksInfoJson[index].del_charge = parseFloat(jQuery(this).val())

        }

    })



    jQuery(document).on('change', 'input[name="pd_shipping_calculator_method"]', function () {

        jQuery('.pd_shipcal_method_wrap').hide()
        if (jQuery(this).val() == 'api') {

            jQuery('.pd_shipcal_api_method').show()
            manual_block_table.fixedHeader.disable();
            api_block_table.fixedHeader.enable();

        } else {

            jQuery('.pd_shipcal_manual_method').show()
            api_block_table.fixedHeader.disable();
            manual_block_table.fixedHeader.enable();
        }

    })



    form.submit(function (e) {

        // e.preventDefault()

        // Use the global helpers from the inline script instead of appending
        // duplicate hidden inputs (which break when JSON contains quotes).
        if (typeof window.pdFlushStore === 'function') { window.pdFlushStore(); }
        if (typeof window.pdSyncPickup  === 'function') { window.pdSyncPickup(); }

        // Also remove any previously appended .final_blocks inputs that may
        // have been added by an older version of this handler.
        jQuery('.pd_shipcal_container').parents('form').find('p.submit input.final_blocks').remove();

    })



    // jQuery(document).on('click', 'input[name="manual_all_block"],input[name="api_all_block"]',function(){

    //     var method = jQuery(this).data('method');

    //     var BlocksInfoJson = (method == 'api') ? apiBlocksInfoJson : manualBlocksInfoJson;

    //     jQuery(`.${method}BlockCheck`).prop("checked", jQuery(this).prop("checked"));

    //     if (jQuery(this).is(':checked')) {

    //         jQuery.each(BlocksInfoJson, function(index, value) {

    //             BlocksInfoJson[index].enabled = true

    //         })

    //     } else {

    //         jQuery.each(BlocksInfoJson, function(index, value) {

    //             BlocksInfoJson[index].enabled = false

                

    //         })

    //     }

    // }) 

    



    jQuery(document).on('click', '.headerActionBtn', function(e){

        e.preventDefault()

        var popup_id = jQuery(this).data('popup_id')

        if(popup_id){

            jQuery.magnificPopup.open({

                  items: {

                      src: '#'+popup_id,

                  },

                  type: 'inline',

                  closeOnBgClick: false,

                  showCloseBtn:false

              });

        }

    })



    jQuery(document).on('click','.cancel_btn' ,function(){

        var method = jQuery(this).data('method')
        var container = jQuery(this).parent().parent()
        var type = container.find('input').attr('type')
        if(type == 'checkbox')
            container.find('input').prop("checked",false)
        else 
            container.find('input').val('')
        jQuery.magnificPopup.close();

    })



    jQuery(document).on('click', '.set_charge',function(){

        var method = jQuery(this).data('method')

        var container = jQuery(this).parents(`.${method}ChargePopup`)

        var charge = (container.find('input[name="delivery_charge_for_all"]').val())

        var BlocksInfoJson = (method == 'api') ? apiBlocksInfoJson : manualBlocksInfoJson;

        if(BlocksInfoJson.length > 0 && charge){

            jQuery.each(BlocksInfoJson, function( key, value ) {

                BlocksInfoJson[key].del_charge = parseFloat(charge)

            })

            var datatable = getDatatableObject(method);

            (datatable).rows().nodes().to$().find(`input[name="${method}_block_charge"]`).val(parseFloat(charge)).change();

        }
        container.find('input[name="delivery_charge_for_all"]').val('')
        jQuery.magnificPopup.close();

    })



    jQuery(document).on('click', '.set_status',function(){

        var method = jQuery(this).data('method')

        var container = jQuery(this).parents(`.${method}StatusPopup`)

        var BlocksInfoJson = (method == 'api') ? apiBlocksInfoJson : manualBlocksInfoJson;

        if(BlocksInfoJson.length > 0){
            

            if (container.find('input[name="status_for_all"]').is(':checked')) {

                

                jQuery.each(BlocksInfoJson, function( key, value ) {

                    BlocksInfoJson[key].status = true

                })

            } else {

                

                jQuery.each(BlocksInfoJson, function( key, value ) {

                    BlocksInfoJson[key].status = false

                })

            }

            var datatable = getDatatableObject(method);

            (datatable).rows().nodes().to$().find(`input[name="${method}_block_status"]`).prop("checked", container.find('input[name="status_for_all"]').prop("checked")).change();

        }

        container.find('input[name="status_for_all"]').prop("checked",false)
        jQuery.magnificPopup.close();

    })


    jQuery(document).on('click' , '.saveChangesBtn',function(){
        jQuery('button[name="save"]').click()
    })

    // Reload-blocks button: bust the server-side transient cache and force a
    // fresh fetch from the Parcel API. Reload the page on success so the new
    // data flows through wp_localize_script and the tables redraw normally.
    jQuery(document).on('click', '.pd_reload_blocks', function () {
        var $btn    = jQuery(this);
        var $status = jQuery('.pd_reload_blocks_status');
        $btn.prop('disabled', true);
        $status.text('Loading...');
        jQuery.post(pdVar.adminurl, {
            action: 'pd_load_blocks',
            nonce:  pdVar.nonce,
            force:  1
        }).done(function (resp) {
            if (resp && resp.status && Array.isArray(resp.block_data) && resp.block_data.length) {
                $status.text('Loaded ' + resp.block_data.length + ' blocks. Reloading page...');
                window.location.reload();
            } else {
                var msg = (resp && resp.message) ? resp.message : 'No blocks returned by the API.';
                $status.text(msg);
                $btn.prop('disabled', false);
            }
        }).fail(function (xhr) {
            $status.text('Request failed (' + xhr.status + '). Check the server error log.');
            $btn.prop('disabled', false);
        });
    });
 

    

})





// functions



function select2Initialization(selectElements){

    if(pd_all_blocks_info.status){

        jQuery.each(selectElements, function(key, value) {

            if(key == 'pickup_block'){

                singleSelect2Init1(value,key)

            }else{



                singleSelect2Init(value,key)

            }

        });

    }

    

}



function singleSelect2Init(selectElement,method) {

    var BlocksInfoJson = manualBlocksInfoJson;

    if(method == 'api') BlocksInfoJson = apiBlocksInfoJson

    var results = jQuery.map(pd_all_blocks_info.block_data, function (item) {

        var res = {

            text: item.name,

            id: item.id,

            nameAr: item.nameAr,

            _id: item._id,

            latitude: item.latitude,

            longitude: item.longitude,

            status: true,

            min_order: 0,

            del_charge: 0

        };

        var demo = (BlocksInfoJson).find(x => x.id === item.id);

        if (demo) {

            res.disabled = true;

        }

        return res;

    });



    selectElement.select2({

        data:results,

        width: '100%',

        placeholder: "select block"



    }).on("select2:select", function(e) {

        console.dir(e.params.data);

        e.params.data.disabled = true;

    });

    

    selectElement.val('').trigger('change');

    

}



function singleSelect2Init1(selectElement,method){

    var results = jQuery.map(pd_all_blocks_info.block_data, function (item) {

        var res = {

            text: item.name,

            id: item.id,

            nameAr: item.nameAr,

            _id: item._id,

            latitude: item.latitude,

            longitude: item.longitude,

            status: true,

            min_order: 0,

            del_charge: 0

        };

        return res;

    });



    selectElement.select2({

        data:results,

        width: '100%',

        placeholder: "select block"

    })

    var savedPickup = pdVar.pd_pickup_location || {};
    if(pdPickupLocation) selectElement.val(pdPickupLocation).trigger('change');

    if(savedPickup.pickup_latitude) jQuery('input[name="pickup_lat"]').val(savedPickup.pickup_latitude)

    if(savedPickup.pickup_longitude) jQuery('input[name="pickup_long"]').val(savedPickup.pickup_longitude)

    if(savedPickup.pickup_address1) jQuery('input[name="pickup_address1"]').val(savedPickup.pickup_address1)

    if(savedPickup.pickup_address2) jQuery('input[name="pickup_address2"]').val(savedPickup.pickup_address2)

    if(pdVar.pd_delivery_vehicle) jQuery('select[name="delivery_vehicle"]').val(pdVar.pd_delivery_vehicle).change();

 

}



function changeOnBlockSelectElement(This){

    var method = This.data('method');

    var BlocksInfoJson = (method == 'api') ? apiBlocksInfoJson : manualBlocksInfoJson;

    var selectData = This.select2('data');

    if (!jQuery.isEmptyObject(selectData)) {

        var id = parseInt(selectData[0].id);

        var text = (selectData[0].text);

        var block_array = {

            "id": id,

            "name": text,

            "nameAr": selectData[0].nameAr,

            "_id": selectData[0]._id,

            "latitude": selectData[0].latitude,

            "longitude": selectData[0].longitude,

            "status": selectData[0].status,

            "min_order": selectData[0].min_order,

            "del_charge": selectData[0].del_charge

        }

        BlocksInfoJson.push(block_array);

        This.val('').trigger('change');

        

        drawblockTableRow(BlocksInfoJson,method);

               

    }

}



function drawBlocksTable(blocksInfo){

    jQuery.each(blocksInfo, function(method, blockInfo) { // key,value

        var blockInfoJson =  (method == 'api') ? apiBlocksInfoJson : manualBlocksInfoJson;

        if (blockInfo.length > 0) {



            // for (var i = 0; i < blockInfo.length; i++) {

            //     var id = parseInt(blockInfo[i]['id']);

            //     var block_array = {

            //         "id": id,

            //         "name": blockInfo[i]['name'],

            //         "nameAr": blockInfo[i]['nameAr'],

            //         "_id": blockInfo[i]['_id'],

            //         "latitude": blockInfo[i]['latitude'],

            //         "longitude": blockInfo[i]['longitude'],

            //         "status": blockInfo[i]['status'],

            //         "min_order": blockInfo[i]['min_order'],

            //         "del_charge": blockInfo[i]['del_charge']

            //     }

            //     blockInfoJson.push(block_array);

            // }

            drawblockTableRow(blockInfoJson, method);

        } else {

            console.log('no block selected.');

        }

    });

    
}



function drawblockTableRow(rowData, method) {

    var html = '';

    jQuery(document).find('.'+method+'_blocks_table_container').show();

    jQuery(document).find('table.'+method+'_blocks_table tbody').html('');

    var allBlocksStatus = true;



    for (var i = 0; i < rowData.length; i++) {

        if(!rowData[i].status) allBlocksStatus = false;

        html += `<tr id='${rowData[i].id}' data-_id='${rowData[i]._id}' data-method='${method}'>

        <td><div class="pdBlockNo"><span>${rowData[i].id}</span></div></td>

        <td><div class="pdBlockName"><span>${(rowData[i].name).replace(/"/g, "'")}</span></div></td>

        <td><div class="pdMinOrder pdInputDiv"><span class="pdCurrency">${pdVar.wc_currency}</span><input type="number" min="0"  step="1" max class="input-text price" name="${method}_block_min_order" value="${rowData[i].min_order}" data-index="${i}" onwheel="return false;"></div></td>

        <td><div class="pdBlockCharge pdInputDiv"><span class="pdCurrency">${pdVar.wc_currency}</span><input type="number"  step="1" max class="input-text price" name="${method}_block_charge" value="${rowData[i].del_charge}" data-index="${i}" onwheel="return false;"></div></td>

        <td><div class="pdStatus"><label class="pdSwitch pdSwitchFlat"><input class="pdSwitchInput" name="${method}_block_status" ${rowData[i].status ? "checked" : ''} class="${method}BlockStatus" type="checkbox" /><span class="pdSwitchLabel" data-on="On" data-off="Off"></span> <span class="pdSwitchHandle"></span> </label></div></td></tr>`;

    }

    if(allBlocksStatus) jQuery(`.${method}StatusPopup`).find('input[name="status_for_all"]').prop("checked", true)



    //old

    // for (var i = 0; i < rowData.length; i++) {

    //     html += `<tr id='${rowData[i].id}' data-_id='${rowData[i]._id}' data-method='${method}'>

    //     <td>${i+1}</td>

    //     <td><div class="pdBlockNo"><span>${rowData[i].id}</span></div></td>

    //     <td><div class="pdBlockName"><span>${rowData[i].name}</span></div></td>

    //     <td><div class="pdMinOrder pdInputDiv"><span class="pdCurrency">${pdVar.wc_currency}</span><input type="number" min="0"  step="1" max class="input-text price" name="${method}_block_min_order" value="${rowData[i].min_order}" data-index="${i}"></div></td>

    //     <td><div class="pdBlockCharge pdInputDiv"><span class="pdCurrency">${pdVar.wc_currency}</span><input type="number" min="0"  step="1" max class="input-text price" name="${method}_block_charge" value="${rowData[i].del_charge}" data-index="${i}"></div></td>

    //     <td><div class="pdStatus"><label class="pdSwitch pdSwitchFlat"><input class="pdSwitchInput" name="${method}_block_status" ${rowData[i].status ? "checked" : ''} class="${method}BlockStatus" type="checkbox" /><span class="pdSwitchLabel" data-on="On" data-off="Off"></span> <span class="pdSwitchHandle"></span> </label></div></td></tr>`;

    // }

    jQuery(document).find('table.'+method+'_blocks_table tbody').append(html);



    // if(jQuery(`.${method}BlockCheck`).length === jQuery(`.${method}BlockCheck:checked`).length)

    //     jQuery(`input[name="${method}_all_block"]`).prop("checked", true);



}



// new change

function loadAllBlocks(methods) {

    if(pd_all_blocks_info && pd_all_blocks_info.status && Array.isArray(pd_all_blocks_info.block_data)){

        jQuery.each(methods, function(key, method) {

            

            var savedBlockInfo = (method == 'api') ? apiBlocksInfo : manualBlocksInfo;

            // Saved info may arrive as an empty string (no settings yet) or as
            // an array. Normalise so the .find() below never throws.
            if ( ! Array.isArray(savedBlockInfo) ) {
                savedBlockInfo = [];
            }

            var results = jQuery.map(pd_all_blocks_info.block_data, function (item) {

                if ( ! item || typeof item !== 'object' ) {
                    return null;
                }

                // Defensive: API has occasionally returned blocks with null
                // name / nameAr. Coerce to string before calling string ops so
                // a single bad row does not abort the whole table render.
                var rawName = (item.name == null) ? '' : String(item.name);
                var name = rawName.replace(/'/g, '"');

                var res = {

                    name: name,

                    id: item.id,

                    nameAr: (item.nameAr == null) ? '' : String(item.nameAr),

                    _id: item._id,

                    latitude: item.latitude,

                    longitude: item.longitude,

                    status: false,

                    min_order: 0,

                    del_charge: 0

                };

                if(savedBlockInfo.length > 0){

                    var demo = (savedBlockInfo).find(x => x.id === item.id);

                    if (demo) {

                        // res.disabled = true;

                        res.status = demo.status,

                        res.min_order = demo.min_order,

                        res.del_charge = demo.del_charge

                    }

                }

                return res;

            });

            (method == 'api') ? apiBlocksInfoJson = results : manualBlocksInfoJson = results;

        });

    }   

    

}





/*********load datatable pagination start*********/

function loadDatatable(This, page = 0) {

    var table = jQuery(This).DataTable({

        "pageLength": 50,

        // "searching": false,

        // "bPaginate": false,

        "lengthChange": false,
		"fixedHeader": {
            "headerOffset": 82
        }

        // "filter": false,

        // "info": false,

        // "autoWidth": false,

        // "ordering": false,

        // "order": [[0, 'asc']],

        // language: {

        //     paginate: {

        //         next: '&#8594;', // or '→'

        //         previous: '&#8592;' // or '←' 

        //         // next: '',

        //         // previous: ''

        //     }

        // }

        

        

    });

    // (table.page(page).draw('page'));

    

    return table;

}

/*********load datatable pagination end*********/



function getDatatableObject(method){

    return (method == 'api') ? api_block_table : manual_block_table;

}


