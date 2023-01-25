

$(document).ready(function(){
  showHideNoUploadsMessage();
  initSortable(null, updateDragSortOrder);

  var fieldName = $('#fieldName').val();
  self.parent.resizeIframe(fieldName + '_iframe');
});

// Callback for drag sorting
function updateDragSortOrder(row, table) {
  // get new order
  var rows       = table.tBodies[0].rows;
  var uploadNums = '';
  for (var i=0; i<rows.length; i++) {
      var order = $("._uploadNum", rows[i]).val();
      if (order) {
        if (uploadNums != '') { uploadNums += ','; }
        uploadNums += order;
      }
  }

  // save changes via ajax
  $.ajax({
    url: '?',
    type: "POST",
    data: {
      menu:          $('#menu').val(),
      action:        'uploadListReOrder',
      fieldName:     $('#fieldName').val(),
      num:           $('#num').val(),
      preSaveTempId: $('#preSaveTempId').val(),
      uploadNums:    uploadNums,
      _CSRFToken:    $('[name=_CSRFToken]').val()
    },
    error:  function(XMLHttpRequest, textStatus, errorThrown){
      alert("There was an error sending the request! (" +XMLHttpRequest['status']+" "+XMLHttpRequest['statusText'] +")");
    },
    success: function(msg){ if (msg) { alert("Error: " + msg); }}
  });
}

//
function showHideNoUploadsMessage() {

  var uploadRows = $("tr.uploadRow").length;

  if (uploadRows == 0) { $(".noUploads").show(); }
  else                 { $(".noUploads").hide(); }
}

function removeUpload(uploadNum, filename, rowChildEl) {

  // confirm erase
  var message = lang_confirm_erase_image.replace("%s", filename);
  if (!confirm(message)) { return false; }

  // erase record
  $.ajax({
    url: '?',
    type: "POST",
    data: {
      menu:              $('#menu').val(),
      action:            'uploadErase',
      fieldName:         $('#fieldName').val(),
      uploadNum:         uploadNum,
      num:               $('#num').val(),
      preSaveTempId:     $('#preSaveTempId').val(),
      _CSRFToken:        $('[name=_CSRFToken]').val()
    },
    
    error:  function(msg){ alert("There was an error sending the request!"); },
    success: function(msg){
      if (msg) { return alert("Error: " + msg); };

      $(rowChildEl).closest('tr').remove(); // remove field html
      showHideNoUploadsMessage();

      // resize parent iframe
      var iframeName = $('#fieldName').val() + '_iframe'
      self.parent.resizeIframe(iframeName);
      return true;
    }
  });

  return false;
}


//
function modifyUpload(uploadNum, filename, rowChildEl) {

  var modifyUrl = "?menu=" + $('#menu').val()
                + "&action=uploadModify"
                + "&fieldName=" + $('#fieldName').val()
                + "&num=" + $('#num').val()
                + "&preSaveTempId=" + $('#preSaveTempId').val()
                + "&uploadNums=" + uploadNum;

  // show modal
  self.parent.showModal(modifyUrl);

}
