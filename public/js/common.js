$.ajaxSetup({
    headers: {
      'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    }
});

$.fn.dataTable.ext.errMode = 'none';

$(document).ajaxError(function (event, jqxhr, settings, thrownError) {
   if (jqxhr.status === 401) {
      document.location.href = admin_url+'/login';
   }
});

$(document).ready(function(){


    $('#datatable').on('click', '.btn-delete', function (e)
    {
        e.preventDefault();
        var reload_datatable = $("#datatable").dataTable( { bRetrieve : true } );
        var url= $(this).attr('href');

        $.confirm({
            title: 'Confirm!',
            content: 'Are you sure! You want remove this record',
            type: 'red',
            typeAnimated: true,
            closeIcon: true,
            buttons: {
                confirm: function () {
                    $("#datatable tbody").LoadingOverlay("show");
                    $.ajax({
                        type: "delete",
                        url: url,
                        dataType: "json",
                        complete:function (res) {
                            $("#datatable tbody").LoadingOverlay("hide");
                            var j = JSON.parse(res.responseText);
                            var result = j.result;
                            if(res.status == 200){
                                if(reload_datatable != ""){
                                   reload_datatable.fnDraw();
                                }

                                success_message(result.message);

                            }else{
                                error_message(result.message);
                            }
                        },
                          error: function (request, status, error) {
                              $("#datatable tbody").LoadingOverlay("hide");
                              var result = request.responseJSON.result;
                              var err = JSON.parse(request.responseText);
                              if(status == 401){
                                error_message(result.message);
                            }else{
                                error_message(err.message);
                            }
                          }
                    });
                },
                cancel: function () { },
            }
        });

        return false;
    });



$(document).ready(function(){
    $(".select2").select2({
      placeholder: "Select option",
      allowClear: true
  });
});




});




//$(document).ajaxStart(function () {
//    Pace.restart();
//});
function create_datatables(url,columns, index_field = true,ordering = [], pageLength = 10){

    if(index_field){
        $('#datatable thead tr').prepend("<th>#</th>");
        $('#datatable tfoot tr').prepend("<th>#</th>");

        columns.unshift({data: 'index',width: '2%', className: 'text-center',orderable: false, searchable: false});
    }

    var t = $('#datatable').DataTable({
        processing: true,
        serverSide: true,
        ordering: true,
        responsive: true,
        ajax: url,
        columns: columns,
        pageLength: -1,
        order: ordering,
        drawCallback: function( settings ) {
        var api = this.api();

        //console.log( api.rows( {page:'current'} ).data()) );
        if(index_field){
            api.column(0).nodes().each( function (cell, i) {
                  var index = (i+1) + (t.page.info().page * t.page.info().length);
                  cell.innerHTML = index;
              } );
        }
        }
  });

}

function remove_record(url,reload_datatable,method){
    // confirm then
    swal({
        title: "Are you sure?",
        text: "You will not be able to recover this record!",
        type: "warning",
        showCancelButton: true,
        confirmButtonColor: "#DD6B55",
        confirmButtonText: "Yes, delete it!",
        cancelButtonText: "No, cancel!",
        closeOnConfirm: false,
        closeOnCancel: false
      },
      function(isConfirm){
        if (isConfirm) {
            $.ajax({
              type: method,
              url: url,
              dataType: "json",
              complete:function (res) {
                  swal.close();
                  var j = JSON.parse(res.responseText);
                  var result = j.result;
                  if(res.status == 200){
                      if(reload_datatable != ""){
                         reload_datatable.fnDraw();
                      }

                      toastr.success(result.success);

                  }else{
                      toastr.error(result.error);
                  }
              },
                error: function (request, status, error) {
                    swal.close();
                    var result = request.responseJSON.result;
                    var err = JSON.parse(request.responseText);
                    if(status == 401){
                      toastr.error(result.error);
                  }else{
                      toastr.error(err.message);
                  }
                }
          });
        } else {
            swal.close();
            toastr.info("Your record is safe :)");
        }
      });
}

/**
 * Show Ajax Error Message
 * @param response
 */
function showAjaxErrorMessage(response, form = false)
{
    let responseJson = JSON.parse(response.responseText);
    let errors = responseJson.errors;
    
    if (form) {
        if (errors !== undefined) {
            Object.keys(errors).forEach(function (item) {
                for (let value of errors[item]) {                    
                    $('[name=' + item + ']').parent('.form-group').find(".text-danger").text(value);
                    $('#' + item + '-error').text(value);
                    $('[name=' + item + ']').addClass('is-invalid');
                }
            });
        }
    } 
    if (errors !== undefined) {
        Object.keys(errors).forEach(function (item) {
            for (let value of errors[item]) {
                errorMessage(value);
            }
        });
    } else if (responseJson.message !== undefined) {
        errorMessage(responseJson.message);
    }
    
}

/**
 * Loading overlay js
 * @param _ele
 */
let loadingOverlay = (_ele, show = true) => {
    if (show) {
        _ele.LoadingOverlay('show');
    }    
}

/**
 * Stopping overlay js
 * @param _ele
 */
let stopOverlay = (_ele, hide = true) => {
    if (hide) {
        _ele.LoadingOverlay('hide');
    }
}

function success_message(message)
{
    successMessage(message);
}

function error_message(message)
{
    errorMessage(message);
}

/**
 * Show Success Message
 * @param message
 * @param title
 */
function successMessage(message, title='')
{
    if (!title) title = "Success!";
    toastr.remove();
    toastr.success(message, title, {
        closeButton: true,
        timeOut: 4000,
        progressBar: true,
        newestOnTop: true
    }); 
}

/**
 * Show Error Message
 * @param message
 * @param title
 */
function errorMessage(message, title='')
{
    if (!title) title = "Error!";
    toastr.remove();
    toastr.error(message, title, {
        closeButton: true,
        timeOut: 4000,
        progressBar: true,
        newestOnTop: true
    }); 
}

function    generateRandomNumber(length) {
    if(!length) { length = 16; }
    //var chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    var chars = "1234567890";
    var result="";

    for (var i = length; i > 0; --i)
        result += chars[Math.round(Math.random() * (chars.length - 1))]
    return result
}

$(document).ready(function(){
    $('form').validator().on('submit', function (e) {
        if (e.isDefaultPrevented()) {
            $("input[type=submit]").removeProp("disabled");
        }else{
            $("input[type=submit]").prop("disabled", "disabled");
        }
    });
});
