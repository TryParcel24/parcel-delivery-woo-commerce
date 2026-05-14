var ajax_url = orderVar.adminurl;

jQuery(document).ready(function ($) {
  $(document).on('click', 'button[name="request_single_delivery"]', function (e) {

    e.preventDefault()
    var order_id = $(this).attr("order_id");
    var order_ids = [order_id];
    console.log(order_ids);
    var This = jQuery(this)

    if (order_ids.length > 0) {

      var data = {

        action: 'request_delivery_bulk_action',

        order_ids: order_ids,

        nonce: orderVar.nonce

      };

      This.html('Processing...');

      jQuery.ajax({

        type: "post",

        url: ajax_url,

        dataType: 'json',

        data: data,

        success: function (response) {

          This.html('Request Delivery');
          console.log(response)
          console.log(response.status)
          if (!alert(response.message)) {
            document.location.reload();
          }
        }

      });

    } else {

      alert('Please select some orders.');

    }

  });
  jQuery(document).on('click', '.delivery_request', function () {



    var order_id = jQuery(this).attr('data-id');

    var button_text = jQuery(this).attr('data-text');

    if (order_id == '' || order_id === undefined) {

      jQuery('.alrt_msg').html('Something went rong.');



    }

    jQuery('.delivery_request').html('Processing...');



    jQuery('.alrt_msg').html('');



    jQuery.ajax({

      type: "post",

      url: ajax_url,

      dataType: 'json',

      data: { action: "request_delivery_bulk_action", order_ids: [order_id], nonce: orderVar.nonce },

      success: function (response) {

        console.log(response)

        jQuery('.delivery_request').html(button_text);





        if (1 == response.status) {





          jQuery('.alrt_msg').html('<div class="alert alert-success">Delivery requested successfully</div>');



          setTimeout(function () {

            pdSafeReload();

          }, 2000);


        }


        else if (2 == response.status) {

          jQuery('.alrt_msg').html('<div class="alert alert-success">Delivery already created</div>');


        }

        else {

          if (response.data) {

            console.log(response.data);

          }

          jQuery('.alrt_msg').html('<div class="alert alert-danger">Something went wrong or api error.</div>');

        }

        setTimeout(function () {

          jQuery('.alrt_msg').html('');

        }, 3000);

      }

    });


  })



  jQuery(document).on('click', '.delivery_cancel', function (e) {

    e.preventDefault()

    jQuery.magnificPopup.open({

      items: {

        src: '#parcel-delivery-cancel-popup',

      },

      type: 'inline',

      closeOnBgClick: false,

      showCloseBtn: false

    });

  })



  jQuery(document).on('click', '.cancel_no_btn', function () {

    jQuery.magnificPopup.close();

  })



  function pdSafeReload() {
    try { window.onbeforeunload = null; } catch (e) {}
    try { jQuery(window).off('beforeunload'); } catch (e) {}
    if (window.wp && window.wp.autosave && window.wp.autosave.server) {
      try { window.wp.autosave.server.suspend(); } catch (e) {}
    }
    document.location.reload();
  }

  jQuery(document).on('click', '.cancel_ok_btn', function () {

    // jQuery.magnificPopup.close();

    pdSafeReload();

  })



  jQuery(document).on('click', '.cancel_yes_btn', function () {

    var order_id = jQuery('.delivery_cancel').attr('data-id');

    var task_relation = jQuery('.parcel_delivery_request_box input[name="delivery_task_relation"]').val();

    var delivery_id = jQuery('.parcel_delivery_request_box input[name="delivery_task_id"]').val();

    var data = { action: "cancel_order_delivery_task", order_id: order_id, task_relation: task_relation, delivery_id: delivery_id, nonce: orderVar.nonce }





    jQuery.ajax({

      type: "post",

      url: ajax_url,

      dataType: 'json',

      data: data,

      success: function (response) {

        if (1 == response.status) {

          jQuery.magnificPopup.close();

          jQuery.magnificPopup.open({

            items: {

              src: '#parcel-delivery-cancel-popup-msg',

            },

            type: 'inline',

            closeOnBgClick: false,

            showCloseBtn: false

          });


        }
        else {

          alert(response.message)

        }

      }

    });

  })


})


function myFunction() {

  var copyText = document.getElementById("myInput");

  copyText.select();

  copyText.setSelectionRange(0, 99999);

  navigator.clipboard.writeText(copyText.value);



  var tooltip = document.getElementById("myTooltip");

  tooltip.innerHTML = "Copied";

}
