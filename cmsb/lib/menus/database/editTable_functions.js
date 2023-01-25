
$(document).ready(function(){ init(); });


//
function init() {
  initSortable(null, updateFieldOrder);
  
  // enable link to a tab for bootstrap nav-tabs - added in v3.06
  var hash = document.location.hash;
  var prefix = "tab_"; // add prefix to prevent from going to the anchor when tab is clicked after the page has been loaded
  if (hash) {
      $('.nav-tabs a[href='+hash.replace(prefix,"")+']').tab('show');
  } 
  // register tab click
  $('.nav-tabs a').on('shown.bs.tab', function (e) {
      window.location.hash = e.target.hash.replace("#", "#" + prefix);
  });

  //
  showHideIframeHeight();
  $('[name=_linkTarget]').change(function(){
    showHideIframeHeight();
  });
}

//
function updateFieldOrder(row, table){
  // get new order
  var rows     = table.tBodies[0].rows;
  var newOrder = "";
  for (var i=0; i<rows.length; i++) {
      var order = $("._fieldName", rows[i]).val();
      if (order) {
        if (newOrder != "") { newOrder += ","; }
        newOrder += order;
      }
  }

  // save changes via ajax
  $.ajax({
    url: '?',
    type: "POST",
    data: {
      menu:              'database',
      action:            'editTable',
      tableName:         $('#tableName').val(),
      saveFieldOrder:    1,
      newFieldnameOrder: newOrder, // force array to string
      _CSRFToken:        $('[name=_CSRFToken]').val()
    },
    error:  function(XMLHttpRequest, textStatus, errorThrown){
      alert("There was an error sending the request! (" +XMLHttpRequest['status']+" "+XMLHttpRequest['statusText'] +")");
    },
    success: function(msg){ if (msg) { alert("Error: " + msg); }}
  });
}

//
function updateFieldList() {
  var tableName = $('#tableName').val();

  // load fieldList
  var url = "?menu=database&action=editTable&tableName=" +tableName+ "&displayFieldList=1";
  $.ajax({
    url: url,
    error:  function(XMLHttpRequest, textStatus, errorThrown){
      alert("Error loading fieldlist!");
    },
    success: function(content){
      $("#fieldlistContainer").html(content);

      // initialize sortable rows
      initSortable(null, updateFieldOrder);
    }
  });

}

//
function quickAddField() {

  // error checking

  // save field (and report errors)
  $.ajax({
    url: '?',
    type: 'POST',
    data: {
      menu:         'database',
      action:       'editField',
      save:         '1',
//      addField:     '1',
      quickadd:     '1',
      fieldname:    '',
      tableName:    $('#tableName').val(),
      label:        $('#fieldLabel').val(),
      newFieldname: $('#fieldName').val(),
      type:         $('#fieldType').val(),
      _CSRFToken:   $('[name=_CSRFToken]').val()
    },
    error:  function(msg){ alert("There was an error sending the request!"); },
    success: function(msg){
      if (msg) { alert("Error: " + msg); } // only errors are returned
      else {
        $('#fieldName, #fieldLabel').val(""); // blank out quickadd fields
      }

      // refresh field list
      updateFieldList();

      // focus quick add label field
      document.getElementById('fieldLabel').focus();

    }
  });
}

//
function autoFillQuickAddFieldName() {
  var fieldLabel = $('#fieldLabel').val();
  var fieldName  = fieldLabel;

  fieldName = fieldName.toLowerCase();                     // lowercase
  fieldName = fieldName.replace(/[^a-z0-9\_]/ig, '_');   // replace non-alphanumeric
  fieldName = fieldName.replace(/_+/ig, '_');              // remove duplicate underscores
  fieldName = fieldName.replace(/(^_+|_+$)/ig, '');        // remove leading/trailing underscores

  // special cases
  if (fieldLabel == 'createdDate')      { fieldName = fieldLabel; }
  if (fieldLabel == 'createdByUserNum') { fieldName = fieldLabel; }
  if (fieldLabel == 'updatedDate')      { fieldName = fieldLabel; }
  if (fieldLabel == 'updatedByUserNum') { fieldName = fieldLabel; }
  if (fieldLabel == 'publishDate')      { fieldName = fieldLabel; }
  if (fieldLabel == 'removeDate')       { fieldName = fieldLabel; }
  if (fieldLabel == 'neverRemove')      { fieldName = fieldLabel; }
  if (fieldLabel == 'dragSortOrder')    { fieldName = fieldLabel; }

  $('#fieldName').val(fieldName);

}


//
function confirmEraseField(tablename, fieldname, el) {

  // confirm erase field
  var confirmed = confirm("Are you sure you want to erase this field?\n\n"+fieldname+"\n\nWARNING: ALL FIELD DATA WILL BE LOST!\n");
  if (confirmed) {

    // erase field
    $.ajax({
      url: "?",
      type: 'POST',
      data: {
        menu:         'database',
        action:       'editTable',
        eraseField:   '1',
        tableName:    tablename,
        fieldname:    fieldname,
        _CSRFToken:   $('[name=_CSRFToken]').val()
      },
      error:  function(msg){ alert("There was an error sending the request!"); },
      success: function(msg){ if (msg) { alert("Error: " + msg); }}
    });
    
    // remove field html
    $(el).closest('tr').remove(); // remove field html
  }
}

function showHideIframeHeight() {
  var linkTarget = $('[name=_linkTarget]').val();
  if (linkTarget == 'iframe') { $('#iframeHeightSpan').show(); }
  else                        { $('#iframeHeightSpan').hide(); }
}
