
$(document).ready(function(){ addTableInit(); });

function addTableInit() {
  _updateAdvancedDescription('');

  initSubmitFormWithAjax();

  // IE doesn't register radio change events til blur, so use click event to trigger change event (Note: This won't work with keyboard selectors in IE)
  // Reference: http://stackoverflow.com/questions/208471/getting-jquery-to-recognise-change-in-ie/1080243#1080243
  if ( navigator.userAgent.indexOf('MSIE') != -1 || navigator.userAgent.indexOf('Trident') != -1 ) {
    $('input[type=radio]').click(function() { this.blur(); this.focus(); });
  }

  // on pulldown change - check related radio
  $('#preset').change(function() {
    $('#menuType-preset').attr('checked','checked');
    menuTypeChange();
  });

  $('#copy').change(function() {
    $('#menuType-copy').attr('checked','checked');
    menuTypeChange();
  });

  $('#advancedType').change(function() {
    $('#menuType-advanced').attr('checked','checked');
    menuTypeChange();
  });

  // on radio change
  $('[name=menuType]').change( menuTypeChange );
}

function resetAddTableFields() {
  $('#addTableForm select').val('');
  $('#addTableForm input[type=text]').val('');
  $('#addTableForm input[type=radio]').attr('checked', false);
}



//
function initSubmitFormWithAjax() {

  $('#addTableForm').ajaxForm({
//    beforeSubmit:  formErrorChecking,        // pre-submit callback
    success:       function(responseText) {  // post-submit callback - close window

      // show errors
      if (responseText != '') { alert(responseText); }

      // close modal
      else {
        $('#addEditorModal').modal('hide');
        window.location.href = '?menu=database';
      }

    },
    error:  function(XMLHttpRequest, textStatus, errorThrown){
      alert("There was an error sending the request! (" +XMLHttpRequest['status']+" "+XMLHttpRequest['statusText'] +")");
    }
  });

  return false;
}

// onMenuTypeChange
function menuTypeChange() {

  // get menuType
  var menuType = $('[name=menuType]:checked').val(); // multi, single, preset, copy, or advanced

  // enable tableName field
  $('#tableName').attr('disabled', false).css('background-color','');


  // preset menus
  if (menuType == 'preset') {
    // set menuName and tableName
    var menuName  = $('#preset :selected').text();
    menuName     = menuName.replace(/^.*:\s*/, '');           // remove everything up to  ": " - this allows us to name the preset "Realty Website : Listings" and have "Listings" show as the menu label
    $('#menuName').val(menuName);

    var tableName = $('#preset').val();
    tableName    = tableName.replace(/^website_realty_/, ""); // remove "website_" which is used to sort website presets to the bottom but isn't needed for tablenames
    $('#tableName').val(tableName);
  }

  // preset menus
  if (menuType == 'copy') {
    // set menuName and tableName
    var menuName  = "Copy of " + $('#copy :selected').text();
    $('#menuName').val(menuName);

    var tableName = $('#copy').val();
    tableName     = "copy_of_" + tableName;
    $('#tableName').val(tableName);
  }

  // advanced options
  if (menuType == 'advanced') {
    var advancedType = $('#advancedType').val();
    _updateAdvancedDescription(advancedType);

    if (advancedType == 'menugroup' || advancedType == 'textlink') {
      //$('#tableName').attr('disabled', true).css('background-color','#B9B9B9').val('');
    }
  }

}

//
function autoFillTableName() {
  var sourceValue      = document.getElementById('menuName').value;
  var advancedMenuType = document.getElementById('advancedType').value;

  var tableName   = sourceValue;
  tableName = tableName.toLowerCase();                 // lowercase
  tableName = tableName.replace(/[']/ig, '');          // remove punctuation
  tableName = tableName.replace(/[^a-z0-9\_]/ig, '_'); // replace non-alphanumeric
  tableName = tableName.replace(/_+/ig, '_');          // remove duplicate underscores
  tableName = tableName.replace(/(^_+|_+$)/ig, '');    // remove leading/trailing underscores
  if(advancedMenuType == 'menugroup' && tableName) {   // add _menu_group suffix if required
    tableName = tableName + '_menugroup';
  }

  // set value
  document.getElementById('tableName').value = tableName;
}
