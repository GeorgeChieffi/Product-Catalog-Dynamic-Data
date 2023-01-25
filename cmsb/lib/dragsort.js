// Custom table drag-sorting for CMS
// ... Call initSortable to initalize sorting on any table with .sortable class



/*
  
  (whitespace) beautified original code from jqueryui 1.11.14
  
  _intersectsWithPointer: function(e) {
    var t = "x" === this.options.axis || this._isOverAxis(this.positionAbs.top + this.offset.click.top, e.top, e.height),
        i = "y" === this.options.axis || this._isOverAxis(this.positionAbs.left + this.offset.click.left, e.left, e.width),
        s = t && i,
        n = this._getDragVerticalDirection(),
        a = this._getDragHorizontalDirection();
    return s ? this.floating ? a && "right" === a || "down" === n ? 2 : 1 : n && ("down" === n ? 2 : 1) : !1
  },
  
*/

// patch jqueryui 1.11.4's sortable, based loosely on:
// https://stackoverflow.com/questions/11520532/jquery-sortable-with-containment-parent-and-different-item-heights
function sortableFixOffsetTop(that) {
  if (!that.containment) { return that.offset.click.top; } // default behaviour
  
  // when the drag helper approaches the top or bottom, within 1 helper-height, linearly-interpolate the "offset" toward the very edges
  // in other words:
  // ... when the drag helper is at least 1 helper-height away from the edges, pretend it's in the (vertical) middle of the helper
  // ... when the drag helper is at the very top, pretend it's at the very top of the helper
  // ... when the drag helper is at the very bottom, pretend it's at the very bottom of the helper
  // ... when the drag helper is in the area near the top or the bottom, smoothly linear interpolate where it's pretending to be
  var helperHeight      = that.helperProportions.height;
  var containmentTop    = that.containment[1];
  var containmentBottom = that.containment[3];
  var halfHelperHeight  = helperHeight / 2;
  var relativeTop       = that.positionAbs.top - containmentTop;
  var relativeBottom    = containmentBottom - that.positionAbs.top;
  
  // linear interpolation helper function
  function lerp(y0, y1, x) {
    return y0 + (y1 - y0) * x;
  }
  
  // if we're near the top...
  if (relativeTop < helperHeight) {
    return lerp(0, halfHelperHeight, relativeTop / halfHelperHeight);
  }
  // if we're near the bottom...
  else if (relativeBottom < helperHeight) {
    return lerp(helperHeight, halfHelperHeight, relativeBottom / halfHelperHeight);
  }
  // if we're in the middle...
  else {
    return halfHelperHeight;
  }
}
$.widget("ui.sortable", $.extend({}, $.ui.sortable.prototype, {
  _intersectsWithPointer: function(e) {
    var t = "x" === this.options.axis || this._isOverAxis(this.positionAbs.top + /* CHANGE */ sortableFixOffsetTop(this) /* CHANGE */, e.top, e.height),
        i = "y" === this.options.axis || this._isOverAxis(this.positionAbs.left + this.offset.click.left, e.left, e.width),
        s = t && i,
        n = this._getDragVerticalDirection(),
        a = this._getDragHorizontalDirection();
    return s ? this.floating ? a && "right" === a || "down" === n ? 2 : 1 : n && ("down" === n ? 2 : 1) : !1
  }
}));



// onStartCallback - Callback to call before sorting starts
// onStopCallback - Callback to call when sorting is finished
function initSortable(onStartCallback, onStopCallback) {
  var draggable = $('.dragger').mousedown(function(){
    setSortableItems(this, onStartCallback, onStopCallback);
  });

  $('body').mouseleave(function(event, ui){
    draggable.trigger('mouseup');
  });
}

//
function setSortableItems(row, onStartCallback, onStopCallback){
  if (onStartCallback){
    onStartCallback(row);
  }
  
  // on mousedown (before sortable code runs), temporarily set table height to static
  $('table.sortable').on('mousedown', function() {
    var $table = $(this).closest('table');
    $table.data('pre-sort-height', $table.css('height'));
    $table.height( $table.height() );
  });
  $('table.sortable').on('mousedown', function() {
    var $table = $(this).closest('table');
    $table.css('height', $table.data('pre-sort-height'));
  });
  
  // on mouseup, remove table's static height by setting it to auto
  $('table.sortable').on('mouseup', function() {
    var $table = $(this).closest('table');
    $table.css('height', 'auto');
  });
  
  $('table.sortable').sortable({
    forceHelperSize : true,
    axis        : 'y',
    containment : 'parent',
    items       : "tr:not(.ui-state-disabled)",
    tolerance   : 'pointer',
    helper      : function(event, ui){
      return fixedHelper(event, ui);
    },
    start       : function(event, ui){
      
      //setTimeout(function() { debugger; }, 100);
      
      // fix placeholder height
      ui.placeholder.height(ui.item.height() - 1);
      $(this).sortable('refresh');
      
    },
    stop        : function(event, ui){
      if (onStopCallback){
        onStopCallback(ui.item, this);
      }
    }
  });

  $('.sortable tr').disableSelection();
}

// Keeps the width of the drag helper.
function fixedHelper(event, ui){
  ui.children().each(function(){
    $(this).width($(this).width());
  });

  return ui;
}

// Dragsort callbacks for regular sections
// =======================================

function updateDragSortOrder_forList(row, tableEl){
  // get new order
  var rows     = tableEl.tBodies[0].rows;
  var newOrder = "";
  for (var i=0; i<rows.length; i++) {
      var order = $("._recordNum", rows[i]).val();
      if (order) {
        if (newOrder != "") { newOrder += ","; }
        newOrder += order;
      }
  }

  // Save changes via ajax
  $('body').css('cursor','wait'); // 2.15 - show wait cursor
  $.ajax({
    url: '?',
    type: "POST",
    data: {
//    menu:       $('._tableName').val(), // old: remove in future
      menu:       $(tableEl).data('table'),
      action:     'listDragSort',
      recordNums: newOrder,
      _CSRFToken: $('[name=_CSRFToken]').val()
    },
    error:  function(XMLHttpRequest, textStatus, errorThrown){
      alert("There was an error sending the request! (" +XMLHttpRequest['status']+" "+XMLHttpRequest['statusText'] +")");
    },
    success: function(msg){
      $('body').css('cursor','default'); // 2.15 - show wait cursor
      if (msg) { alert("Error: " + msg); }
    }
  });
}
