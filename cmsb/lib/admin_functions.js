$(document).ready(function(){ admin_init(); });

//
function admin_init() {

  // Add/Edit pages: Show warning if user tries to leave page after making changes

  // track if form fields have changed with https://github.com/rubentd/dirrty
  // Show changed elements with this code in the console: $("[data-is-dirrty='true']");
  isFormDirty = false;
  showUnsavedChangesWarning = true;
  if ($.fn.dirrty) {    // page must have library loaded
    $("form.preventLeavingOnChange").dirrty({
      preventLeaving: false,
      ignoreId: ['advancedAction'],
      ignoreClass: ['select2-search__field']
    })
    .on("dirty", function(){ isFormDirty = true; })
    .on("clean", function(){ isFormDirty = false; });
  }

  // show warning when leaving page if fields have changed
  if ($('form.preventLeavingOnChange').length) {
    $(window).on('beforeunload', function() {
      if (!showUnsavedChangesWarning) { return undefined; } 

      // if form fields haven't changed, check if any wysiwyg editors on the page have
      var isTinyMCELoaded = typeof(tinymce) != "undefined" && typeof(tinymce.editors) != "undefined"
      var isTinyMCEDirty  = false;
      if (!isFormDirty && isTinyMCELoaded) {
        $.each(tinymce.editors, function(index, value){
          if (value.isDirty()) { isTinyMCEDirty = true; return false; } // break out of loop
        });
      }

      if (isFormDirty || isTinyMCEDirty) { return "You have unsaved changes"; } // most browsers won't show this.
      else                               { return undefined; }
    });
  }

  // set non-standard attributes
  $('.setAttr-spellcheck-false').attr('spellcheck', false); // v2.15 remove quotes around false for fix for FF11: http://bugs.jquery.com/ticket/6548
  $('.setAttr-wrap-off').attr('wrap', 'off');

  // add behaviour for "alert" Close buttons
  $(".alert > .close").click(function() {
    $(this).parent().fadeTo(400, 0, function () { // Links with the class "close" will close parent
      $(this).slideUp(400);
    });
    return false;
  });

  // add Sidebar Accordian Menu effects (but not if "show expanded menu" is enabled)
  var showExpandedMenu = $('#jquery_showExpandedMenu').length;
  if (!showExpandedMenu) { admin_init_sidebar_accordian_menu(); }

  // Override jquery.ajax function to workaround broken HTTP implementation with some hosts (IPowerWeb as of Dec 2011)
  // Reference Links: http://www.bennadel.com/blog/2009-Using-Self-Executing-Function-Arguments-To-Override-Core-jQuery-Methods.htm
  //              ... http://blog.js-development.com/2011/09/testing-javascript-mocking-jquery-ajax.html
  //              ... http://javascriptweblog.wordpress.com/2011/01/18/javascripts-arguments-object-and-beyond/
  //              ... http://docs.jquery.com/Plugins/Authoring
  (function($, origAjax){ // Define overriding method.
    $.ajax = function() {
      var origSuccessMethod = arguments[0].success;
      var newSuccessMethod  = function(data, textStatus, jqXHR){
          // Detect servers that send the string "0" when no content is sent (eg: IPowerWeb as of Dec 2011)
          // Note: They used to send "Content-Length: 0" and and one byte "0" as the data, but now the Content-Length appears to be set correct.
          // ...   So we'll detect their "Server" content header "Nginx / Varnish" (which is likely related to the problem output) and only modify
          // ...   results from servers that send that and a single "0" as output so as to limit false-positives.
          var isBrokenHttpImplementation = (jqXHR.getResponseHeader('Server') == 'Nginx / Varnish' && data == '0')
                                        || (jqXHR.getResponseHeader('Content-Length') == '0' && data != '');
          if (isBrokenHttpImplementation) { data = ''; } // send no output (as intended)

    // v2.60 - Disallow content of "0" for jquery ajax
    // Notes: Broken web/cache servers return "0" if no content is sent (eg: <?php exit; ?>) - With content-length:1 and no server name to match
    if (data == '0') { data = ''; }
    
          //console.log(jqXHR.getAllResponseHeaders()); // debug: show all server headers
          //console.log("isBrokenHttpImplementation: " + isBrokenHttpImplementation);
          //console.log("Server: " + jqXHR.getResponseHeader('Server'));
          //console.log("data: " + data);

          return origSuccessMethod.call(this, data, textStatus, jqXHR);
      };

      if (origSuccessMethod) { // only override if calling code has a success method set, otherwise code like this will produce an error since origSuccessMethod is undefined: jQuery("#loadtest").load("changelog.txt");
        arguments[0].success = newSuccessMethod;
      }
      return origAjax.apply(this, arguments);
    }
  })(jQuery, $.ajax);
  // End: Override jquery.ajax
  

  // implement collapsible separators
  collapsibleSeparatorToggle();

  
  // hooks for growing/shrinking text boxes. use focusin/focusout to get consistent IE behavior
  // add [data-growsize] for desired row height
  $('.textareaGrow').focusin(function() {
    var $this = $(this);
    
    // finish animations to get correct height
    $this.finish();
    var rowheight  = $this.outerHeight(true) / this.rows; // calculate current row height
    var newheight  = rowheight * $this.data('growsize');  // calculate desired height
    
    if (!$this.hasClass('grown')) {
      
      $this.addClass('grown');
      $this.animate({
        height: newheight
      });
    }
  });
  
  $('.textareaGrow').focusout(function() {
    var $this = $(this);
    
    // finish animations to get correct height
    $this.finish();
    var rowheight  = $this.outerHeight(true) / $this.data('growsize'); // calculate current row height
    var newheight  = rowheight * this.rows;                            // calculate original height
    
    if ($this.hasClass('grown')) {
      
      // add a delay to account for moving click targets
      setTimeout(function() {
        $this.removeClass('grown')
        $this.animate({
          height: newheight
        });
      }.bind(this), 150);
    }
  });
}

function admin_init_sidebar_accordian_menu() {

  // add behaviour for Sidebar Accordian Menu
  $("#main-nav li a.nav-top-item").click(function () { // When a top menu item is clicked...
    $(this).parent().siblings().find("a.nav-top-item").parent().find("ul").slideUp("normal"); // Slide up all sub menus except the one clicked
    $(this).next().slideToggle("normal"); // Slide down the clicked sub menu
    return false;
  });

  // admin menu: dynamically maintain padding below admin menu equal to height of admin menu.
  // We do this for usability, so admin menu is easier to click, isn't flush again bottom of screen, and page height
  // doesn't change on tall pages after admin menu is clicked - requiring user to scroll down to see the admin menu that appeared.
  var paddingDiv       = $('<div id="adminPaddingDiv"></div>').insertAfter("#main-nav"); // add div after left-nav menu
  var adminMenuHeight  = $("#main-nav > li:last-child ul").outerHeight(true);
  var adminMenuLink    = $("#main-nav > li:last-child a.nav-top-item");
  var adminMenuVisible = $("#main-nav > li:last-child ul").is(":visible");
  if (adminMenuVisible) { paddingDiv.hide(); } // hide padding div if admin menu is open
  paddingDiv.height( adminMenuHeight );        // set padding div height to expanded admin menu height
  adminMenuLink.click(function () { paddingDiv.slideToggle("normal"); }); // show padding menu when admin is closed, hide it when admin is open

  // add behaviour for Sidebar Accordion Menu Hover Effect
  $("#main-nav li .nav-top-item").hover(
    function() { $(this).stop().animate({ paddingLeft: "25px" }, 200); },
    function() { $(this).stop().animate({ paddingLeft: "15px" }); }
  );
}

//
function confirmEraseRecord(menu, num, returnUrl) {
  var message = lang_confirm_erase_record;
  var isConfirmed = confirm(message);
  if (isConfirmed) {
    //window.location="?menu=" +menu+ "&action=eraseRecords&selectedRecords[]=" + num + (returnUrl ? ('&returnUrl=' + encodeURIComponent(returnUrl)) : '');
    redirectWithPost('?', {
      'menu':              menu,
      'action':            'eraseRecords',
      'selectedRecords[]': num,
      'returnUrl':         returnUrl,
      '_CSRFToken':        $('[name=_CSRFToken]').val()
    });
  }
}

function htmlspecialchars( str ) {
  if ( typeof( str ) == 'string' ) {
    str = str.replace( /&/g, '&amp;' );
    str = str.replace( /"/g, '&quot;' );
    str = str.replace( /'/g, '&#039;' );
    str = str.replace( /</g, '&lt;' );
    str = str.replace( />/g, '&gt;' );
  }
  return str;
}

// Javascript sprintf() function
// v0.6 from http://www.diveintojavascript.com/projects/javascript-sprintf
// Copyright (c) Alexandru Marasteanu <alexaholic [at) gmail (dot] com>, All rights reserved.
// License: BSD
function sprintf() {
  var i = 0, a, f = arguments[i++], o = [], m, p, c, x, s = '';
  while (f) {
    if (m = /^[^\x25]+/.exec(f)) {
      o.push(m[0]);
    }
    else if (m = /^\x25{2}/.exec(f)) {
      o.push('%');
    }
    else if (m = /^\x25(?:(\d+)\$)?(\+)?(0|'[^$])?(-)?(\d+)?(?:\.(\d+))?([b-fosuxX])/.exec(f)) {
      if (((a = arguments[m[1] || i++]) == null) || (a == undefined)) {
        throw('Too few arguments.');
      }
      if (/[^s]/.test(m[7]) && (typeof(a) != 'number')) {
        throw('Expecting number but found ' + typeof(a));
      }
      switch (m[7]) {
        case 'b': a = a.toString(2); break;
        case 'c': a = String.fromCharCode(a); break;
        case 'd': a = parseInt(a); break;
        case 'e': a = m[6] ? a.toExponential(m[6]) : a.toExponential(); break;
        case 'f': a = m[6] ? parseFloat(a).toFixed(m[6]) : parseFloat(a); break;
        case 'o': a = a.toString(8); break;
        case 's': a = ((a = String(a)) && m[6] ? a.substring(0, m[6]) : a); break;
        case 'u': a = Math.abs(a); break;
        case 'x': a = a.toString(16); break;
        case 'X': a = a.toString(16).toUpperCase(); break;
      }
      a = (/[def]/.test(m[7]) && m[2] && a >= 0 ? '+'+ a : a);
      c = m[3] ? m[3] == '0' ? '0' : m[3].charAt(1) : ' ';
      x = m[5] - String(a).length - s.length;
      p = m[5] ? str_repeat(c, x) : '';
      o.push(s + (m[4] ? a + p : p + a));
    }
    else {
      throw('Huh ?!');
    }
    f = f.substring(m[0].length);
  }
  return o.join('');
}

// required by sprintf()
function str_repeat(i, m) {
  for (var o = []; m > 0; o[--m] = i);
  return o.join('');
}

/*
// Original Source: http://stackoverflow.com/questions/3846271/jquery-submit-post-synchronously-not-ajax
// Usage: 
  redirectWithPost('?', {
    'menu':       'admin',
    'action':     'restore',
    'file':       backupFile,
    '_CSRFToken': $('[name=_CSRFToken]').val()
  });
  
  href="#" onclick="return redirectWithPost('?', {menu:'admin', action:'restore', 'file':backupFile, '_CSRFToken': $('[name=_CSRFToken]').val()});"
  
// Note: Automatically adds _CSRFToken if it exists in form
*/
function redirectWithPost(url, data){
  if (typeof url === 'undefined') { url = '?'; }

  // add _CSRFToken
  data['_CSRFToken'] = $('[name=_CSRFToken]').val();
  
  // 
  $('body').append($('<form/>', {
    id: 'jQueryPostItForm',
    method: 'POST',
    action: url
  }));

  //
  for(var i in data){
    $('#jQueryPostItForm').append($('<input>', {
      type: 'hidden',
      name: i,
      value: data[i]
    }));
  }

  $('#jQueryPostItForm').submit();
  
  return false; // so a href links are cancelled
}


// Update previews of calculated path preview (showing relative paths resolved) 
// Usage:  onkeyup="updateUploadPathPreviews('url', this.value)" onchange="updateUploadPathPreviews('url', this.value)"
// Usage:  onkeyup="updateUploadPathPreviews('dir', this.value)" onchange="updateUploadPathPreviews('dir', this.value)"
function updateUploadPathPreviews(dirOrUrl, inputValue, isCustomField) { // isCustomField is for field specific custom upload paths

  //
  var jSelector;
  if      (dirOrUrl == 'dir') { jSelector = '#uploadDirPreview'; }
  else if (dirOrUrl == 'url') { jSelector = '#uploadUrlPreview'; }
  else                        { return alert("Invalid dirOrUrl value, '" +dirOrUrl+ "'!"); }

  // Show "Loading..." for previews
  $(jSelector).text('loading...');

  // Get preview output
  var requestData = {'menu': 'admin', 'action': 'getUploadPathPreview', 'dirOrUrl': dirOrUrl, 'inputValue': inputValue, 'isCustomField': isCustomField};
  $.get('?', requestData).done(function(responseData) { $(jSelector).text(responseData) });
}

//
function reloadIframe(id, errors) {
  if (errors == undefined) { errors = ''; }
  var el = document.getElementById(id);
  el.contentWindow.location = el.contentWindow.location + '&errors=' + escape(errors);
}

// resize iframe to fit content (up to max)
function resizeIframe(id, duration) {
  if (typeof duration === 'undefined') { duration = 0; }
  
  // We are using setTimeout() to workaround a Firefox bug
  // that doesn't immediately calculate height properly
  // on load.  A small delay of 50 milliseconds resolves
  // the issue.
  setTimeout(function(){  
    var maxHeight     = 800;
    var contentHeight = $('#'+id).contents().find('body').height();
  
    // get new height
    var newHeight = maxHeight;
    if (contentHeight > 0 && contentHeight <= maxHeight) { newHeight = contentHeight + 2; }
  
    // set new height
    $('#'+id).animate({height:newHeight}, duration);

  }, 50);
  
}


//
function collapsibleSeparatorToggle() {
  
  $(".separator-collapsible").click(function(e) {
      // do not trigger show/hide function if the separator title link is clicked
      if($(e.target).is('a')){
          e.preventDefault();
          return;
      }
      _collapsableSeparators_showHide($(this), 300);
    });
  
  // close all separators that are closed by default
  $(".separator-collapsible.separator-collapsed").each(function() {
      _collapsableSeparators_showHide($(this), 0);
    });
}

function _collapsableSeparators_showHide(separatorDiv, duration) {
  
  // switch from up to down icons or vice versa
  var separatorCollapseBtn = separatorDiv.find('i.separator-collapse-btn');
  if (separatorCollapseBtn.hasClass('glyphicon-chevron-up')){
    separatorCollapseBtn.removeClass('glyphicon-chevron-up');
    separatorCollapseBtn.addClass('glyphicon-chevron-down');
  }
  else if (separatorCollapseBtn.hasClass('glyphicon-chevron-down')) {
    separatorCollapseBtn.removeClass('glyphicon-chevron-down');
    separatorCollapseBtn.addClass('glyphicon-chevron-up');
  }
  
  // toggle show/hide
  match   = separatorDiv.next("div");
  while (match.length > 0) {
    if (match.hasClass('separator')) { return; } // stop collapsing on the next header bar separator
    
    match.slideToggle(duration);
    
    // resize iframe height that is initially set to 0 when under a closed-by-default separator
    if (match.find('iframe.uploadIframe').length) {
      resizeIframe(match.find('iframe.uploadIframe').attr('id'), 500);
    }
    
    match = match.next("div"); // get the next field's div
    if(!match) { return; }
  }
}


// eof
