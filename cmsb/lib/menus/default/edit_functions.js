
$(document).ready(function(){ init(); });

//
function init() {
  // Change multi select to select2 pillbox
  $('.js-basic-multiple').select2({ width: '100%' });

  // ***NOTE: If you update ajaxForm in init() also update it in saveRedirectAndReturn() (and vice-versa)
  // submit form with ajax
  $('FORM').ajaxForm({
    beforeSubmit:  function() {
      // disable any spellcheckers that are active before submiting otherwise we hit a bug that causes that editor to not actually submit its text (within IE only)
      // Note: workaround removed as of v2.15 as this is now fixed in tinymce - http://www.tinymce.com/develop/bugtracker_view.php?id=3167
      showUnsavedChangesWarning = false; // disabled unsaved changes warning before we redirect user
    },
    success: function(response) {  // post-submit callback - close window
      var recordNum   = 0;  // the record number is returned on success
      var errors      = ''; // anything else is an error message
      if (parseInt(response) == response)  { recordNum = response; }
      else                                 { errors    = response; }

      // show errors
      if (errors.match(/loginSubmit/gi)) { return self.location = "?"; } // redirect to login screen if session expired
      
      // javascript plugin hook
      if (typeof edit_preErrorCheck == 'function') {
        var doReturn = edit_preErrorCheck(errors); // return false to continue or true to return
        if (doReturn) { return true; }
      }
      
      if (errors != '') {
        errors = errors.replace(/\s+$/, ''); // remove trailing nextlines, Chrome 7 displays then as boxes and/or truncates the error message
        return alert(errors);
      }

      // javascript plugin hook
      if (typeof edit_postSave == 'function') {
        var doReturn = edit_postSave(recordNum); // return false to continue or true to return
        if (doReturn) { return true; }
      }

      // redirect or reload page on success
      var url = '';
      if ($('#returnUrl').val()) { url = $('#returnUrl').val(); }
      else                       { url = '?menu=' + $('#menu').val() + '&saved=' + recordNum; } // display default list page
      self.location = url;
      return true;
    },
    // v2.60 - show ajax errors instead of just no output and a message in the js console
    error:  function(XMLHttpRequest, textStatus, errorThrown){
      var error = '';
      //error += "There was an error sending the request!\n";
      error += XMLHttpRequest['status']+" "+XMLHttpRequest['statusText'] + "\n";
      error += XMLHttpRequest['responseText'];
      alert(error);
    }
  });
  
  // adjust upload iframe height when switching tabs
  $('a[data-toggle="tab"]').on('shown.bs.tab', function() {
    var $uploadIframes = $('iframe.uploadIframe');
    if ($uploadIframes.length) {
      $uploadIframes.each(function() {
        resizeIframe(this.id, 0);
      });
    }
  });
  
}

function showModal(iframeUrl) {
  $('#iframeModal iframe').css('height', ''); // reset height if it was set by resizeModal() function
  $('#iframeModal iframe').attr('src', iframeUrl);
  $('#iframeModal iframe').on('load', function() {
    $('#iframeModal').modal('show');
    $(this).off('load'); // removes "on load" events of the "#iframeModal iframe" so that modal doesn't get called again after hideModal() is called
  });
}
function hideModal() {
  $('#iframeModal').modal('hide');
}
function resizeModal(newHeight) {
  if (!newHeight) {
    newHeight = 800;
  }
  
  // set max height
  $('#iframeModal iframe').css('max-height', '800px');
  
  // resize modal when "Modify All" uploads is clicked when modifying an upload record
  $('#iframeModal iframe').animate({height:newHeight}, 300);
}

function editCancel() {
  if ($('#returnUrl').val()) { self.location = $('#returnUrl').val(); }
  else                       { self.location = '?menu=' + $('#menu').val(); }
}

// called when preview button is clicked
function editPreview() {
  
  // force any tinyMCE controls to update their form elements
  if (typeof tinyMCE.triggerSave == "function") { tinyMCE.triggerSave(); }

  // build an object of elements to submit by getting jQuery to serialize the existing form
  var params = [];
  var queryString = $('FORM').serialize();
  queryString = queryString.replace(/\+/g, '%20'); // unescape() doesn't play well with +
  var dataPairs = queryString.split('&');
  for (var i = 0; i < dataPairs.length; i++) {
    var keyAndValue = $.map(dataPairs[i].split('='), decodeURIComponent);
    params.push(['preview:' + keyAndValue[0], keyAndValue[1]]);
  }

  // post to url - construct a form on the fly to submit to the previewUrl
  // ... (window.open() won't work because the query string can get insanely long)
  var form = document.createElement('form');
  form.setAttribute('method', 'POST');
  form.setAttribute('target', '_blank');
  form.setAttribute('action', $('#previewUrl').val()); // note that url includes special number 9999999999 getRecords() uses to know this is a preview request
  for (i = 0; i < params.length; i++) {
    var hiddenField = document.createElement('input');
    hiddenField.setAttribute('type', 'hidden');
    hiddenField.setAttribute('name',  params[i][0]);
    hiddenField.setAttribute('value', params[i][1]);
    form.appendChild(hiddenField);
  }
  document.body.appendChild(form);
  form.submit();
  document.body.removeChild(form);
  // end: post to url
}

//
function wysiwygUploadBrowser(callback, value, meta) {
  
  // get editorId
  var editorId  = tinyMCE.activeEditor.id;
  var editorObj = tinyMCE.editors[editorId];
  if (editorObj.settings['fullscreen_is_enabled']) {
    editorId = editorObj.settings['fullscreen_editor_id'];
  }
  var fieldname = editorId.replace(/^field_/, '');

  // get uploadBrowser url
  var uploadBrowserUrl = "?menu=" + escape( $('#menu').val() )
                       + "&action=wysiwygUploads"
                       + "&fieldName="     + escape(fieldname)
                       + "&num="           + escape( $('#num').val() )
                       + "&preSaveTempId=" + escape( $('#preSaveTempId').val() )

  tinymce.activeEditor.windowManager.open({
    title: 'Select Image',
    url: uploadBrowserUrl,
    width : 590,
    height : 435
  },
  {
    metaFileType: meta.filetype,
    oninsert: function (url) {
      callback(url);
      }
  });
  
  //
  return false;
}

//
function showCreatedByUserPulldown() {

  // get pulldown options
  var options = "1";
  $.ajax({
    url: '?',
    type: "POST",
    data: {
      menu:   $('#menu').val(),
      action: 'ajaxGetUsersAsPulldown'
    },
    error:  function(XMLHttpRequest, textStatus, errorThrown){
      alert("There was an error sending the request! (" +XMLHttpRequest['status']+" "+XMLHttpRequest['statusText'] +")");
    },
    success: function(pulldownHTML){
      $('#createdByUserNumHTML').html(pulldownHTML);
      $('#createdByUserNumChangeLink').hide();
    }
  });
}

//
function updateListFieldOptions(fieldname, newFilterValue) {

  //Get the single/multi select field using jQuery
  if($("[name='" +fieldname+ "']").length) {
    $fieldSelector = $("[name='" +fieldname+ "']");
  }else{
    $fieldSelector = $("[name='" +fieldname+ "\\[\\]']");
  }

  // Get the current value of the pulldown we're about to update
  var selectedValue = $fieldSelector.val();

  // show "loading..." in the pulldown we're going to update
  $fieldSelector.html("<option value=''>Loading...</option>\n");

  // update pulldown options
  $.ajax({
    // Added false on sync to allow each ajax request to complete before the next one fires off this prevents interference if more than one pulldown depends on a select.change event.
    async: false, // v2.52 - multiple pulldowns updates being triggered by one field sometimes fail with when this is true.
    url: '?',
    type: "POST",
    data: {
      menu:           $('#menu').val(),
      fieldname:      fieldname,
      newFilterValue: newFilterValue,
      selectedValue:  selectedValue,
      action:         'ajaxUpdateListFieldOptions'
    },
    error:  function(XMLHttpRequest, textStatus, errorThrown){
      alert("There was an error sending the request! (" +XMLHttpRequest['status']+" "+XMLHttpRequest['statusText'] +")");
    },
    success: function(response){
      var html = response;
      //window.console && console.log("updateListFieldOptions() ajax success: "+  html);

      // show errors
      if (!html.match(/^<option/)) { alert("Error: " + html); }

      // update single pulldowns
      $fieldSelector.html(html);
      $fieldSelector.change();  // fire change event on this field so any child chained selects will also update

    }
  });
}

// configure uploadifive
function generateUploadifiveOptions( config ) {
  var newUploadNumsAsCSV = '';
  var errors             = '';
  var hasUpload = false;
  var onAllCompleteAlreadyFired = false; // workaround a bug where onAllComplete gets called twice if there was an error?!
  var options = {
    'uploadScript'      : config['uploadScript'],
    'auto'        : true,
    'multi'       : true,
    'fileType'    : config['fileType'],
    'width'       : '100%',
    'height'      : 30,
    'buttonText'  : config['buttonText'],
    'buttonClass' : config['buttonClass'],
    'hideButton'  : true,
    'queueID'     : config['queueID'],
    'fileTypeExts': config['fileTypeExts'],
    'formData'  : {
      '_defaultAction'         : 'uploadForm',
      'menu'                   : config['menu'],
      'fieldName'              : config['fieldName'],
      'num'                    : config['num'],
      'preSaveTempId'          : config['preSaveTempId'],
      'submitUploads'          : '1',
      '_action'                : 'uploadForm',
    },
    'onInit' : function(instance) {
      $('#'+config['fieldName']+'_uploadTips').show();
    },
    'onSelect' : function(file) {
      // now is as good a time as any to reset this
      onAllCompleteAlreadyFired = false;
    },
    'onUploadComplete' : function(fileObj, data) {
      hasUpload = true;
      // get uploadNums, if present
      var matches = data.match(/&uploadNums=([\d,]+)/);
      if (matches) {
        if (newUploadNumsAsCSV.length > 0) { newUploadNumsAsCSV += ','; }
        newUploadNumsAsCSV += matches[1];
      }
    
      // detect login page (if user was logged out)
      if (data.match(/<h3>(\r\n|\n|\r)[ ]*Login(\r\n|\n|\r)[ ]*<\/h3>/)) {
        // get login errors or alerts (if any)
        var matches = data.match(/^<div class="notification.*?<\/a>\s*<div>(.*)<\/div>/m); // match output from _displayNotificationType()
        if (matches) { errors += matches[1]; }

        // add login fail error to errors
        errors += 'The user is logged off.';
        
        // show message to login again
        return alert("Please login again. (or 'Disable HTML5 Uploader' under: Admin > General > Advanced Settings).\n\n" + errors);
      }
      
      // check for HTML page being returned
      else if (data.match(/<html/)) {
        
        // try to find PHP error messages
        var matches = data.match(/(<b>(Notice|Warning|Error)<\/b>: .*)<br/m);
        if (matches) { errors += matches[1]; }
        else         { errors += 'Unknown error.'; } // fall back to generic message
        
      }

      // report anything else but <script tags (expected response from ajax) as an error
      else if (!data.match(/^<script/i)) {
        errors += data;
      }

    },
    'onQueueComplete' : function( uploads ) {
      if (!hasUpload) { return false; }
      if (onAllCompleteAlreadyFired) { return false; }
      if (errors.match(/The user is logged off./)) { location.reload(); return false; } // when the user is logged off, refresh the page so the user can log in again.
      onAllCompleteAlreadyFired = true;

      // reload uploadlist
      if (config['modifyAfterSave']) {
        reloadIframe(config['fieldName']+'_iframe');          // reload uploadlist, don't show errors
      } else {
        reloadIframe(config['fieldName']+'_iframe', errors);  // reload uploadlist, SHOW errors
      }

      if (config['modifyAfterSave']) {
        // v2.51 - get recordNum and preSaveTempId dynamically from page to support Save & Copy
        var recordNum     = $("input[name='num']").val();
        var preSaveTempId = $("input[name='preSaveTempId']").val();
        var targetUrl  = "?action=uploadModify";
        targetUrl     += "&menu="          + config['menu'];
        targetUrl     += "&fieldName="     + config['fieldName'];
        targetUrl     += "&num="           + recordNum;
        targetUrl     += "&preSaveTempId=" + preSaveTempId;
        targetUrl     += "&uploadNums="    + newUploadNumsAsCSV;
        targetUrl     += '&errors='        + escape(errors);

        // modal to add captions, etc.
        showModal(targetUrl);
        //setTimeout(function() { showModal(targetUrl); }, 1000); // use this line instead if your server has timeout issues (and comment above line)
      }

      // reset variables for reuse
      newUploadNumsAsCSV = '';
      errors             = '';

      // finally, clear any errors out of the queue
      $(this).uploadifive('clearQueue');
    }
  };
  if (config['maxUploadSizeKB'] > 0) {
    options['fileSizeLimit'] = config['maxUploadSizeKB'];
  }
  return options;
}

// save record (displaying any ajax errors), then redirect to url setting returnUrl so subsequent save redirects back to edit page.
// Note: Replaces returnUrl automatically replaces ### in both urls with current record number (or record number of newly saved record).
// Note: This function is useful for buttons/links below "Related Records" list which need to refer to the current record number, which
//   ... we may not have yet because we haven't yet saved the record
function saveRedirectAndReturn(redirectUrl) {
// ***NOTE: If you update ajaxForm in init() also update it in saveRedirectAndReturn() (and vice-versa) //

  // submit form with ajax
  // tinyMCE.triggerSave(); // uncomment if this is needed in future - force any tinyMCE controls to update their form elements
  $('FORM').ajaxSubmit({
    beforeSubmit:  function() {
      // disable any spellcheckers that are active before submiting otherwise we hit a bug that causes that editor to not actually submit its text (within IE only)
      // Note: workaround removed as of v2.15 as this is now fixed in tinymce - http://www.tinymce.com/develop/bugtracker_view.php?id=3167
      showUnsavedChangesWarning = false; // disabled unsaved changes warning before we redirect user
    },
    success: function(response) {  // post-submit callback - close window
      var recordNum   = 0;  // the record number is returned on success
      var errors      = ''; // anything else is an error message
      if (parseInt(response) == response)  { recordNum = response; }
      else                                 { errors    = response; }

      // show errors
      if (errors.match(/loginSubmit/gi)) { return self.location = "?"; } // redirect to login screen if session expired
      
      // javascript plugin hook
      if (typeof edit_preErrorCheckRedirect == 'function') {
        var doReturn = edit_preErrorCheckRedirect(errors, redirectUrl); // return false to continue or true to return
        if (doReturn) { return true; }
      }
      
      if (errors != '') {
        errors = errors.replace(/\s+$/, ''); // remove trailing nextlines, Chrome 7 displays then as boxes and/or truncates the error message
        return alert(errors);
      }

      // redirect on success
      redirectUrl   = redirectUrl.replace('###', recordNum);                 // insert record number into 'redirectUrl' (tableNameNum isn't encoded)
      redirectUrl   = redirectUrl.replace(escape('###'), recordNum);         // insert record number into 'redirectUrl' (this is just to catch all cases)
      redirectUrl   = redirectUrl.replace(escape(escape('###')), recordNum); // insert record number into 'redirectUrl' (returnUrl is double encoded)
      self.location = redirectUrl;
      return true;
    }
  });

}

