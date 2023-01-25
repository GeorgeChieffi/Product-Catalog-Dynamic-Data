
// Dragsort callbacks for Category
// ======================

function markSiblings(row){
  var parentNum = getParentNum(row);

  var notSiblings = getNotChildren(parentNum);
  var siblings = getChildren(parentNum);

  $(notSiblings).each(function(){
    $(this).addClass('ui-state-disabled');
  });

  $(siblings).each(function(){
    $(this).addClass('ui-state-default');
  });
}

//
function getParentNum(row){
  return $(row).parents('tr').find('._categoryParent').val();
}

function getNotChildren(parentNum){
  var children = [];

  $('._categoryParent').each(function(){
    if ($(this).val() != parentNum){
      children.push($(this).parent().parent());
    }
  });

  return children;
}

function getChildren(parentNum){
  var children = [];

  $('._categoryParent').each(function(){
    if ($(this).val() == parentNum){
      child = new Array();
      child['index'] = $(this).parent().parent().index();
      child['row'] = $(this).parent().parent();

      children.push(child);
    }
  });

  return children;
}

function updateCategoryDragSortOrder(row, table){
  var sourceNum = getRecordNum(row);
  var targetNum = null;
  var position = null;
  var sibling = getPreviousSibling(row);

  if (sibling.length){ // We found a sibling.
    targetNum = getRecordNum(sibling);
    position = 'below';
  } else {
    sibling = getNextSibling(row);
    targetNum = getRecordNum(sibling);
    position = 'above';
  }

  redirectWithPost('?', {
    'menu':      $('#menu').val(),
    '_action':   'categoryMove',
    'sourceNum': sourceNum,
    'targetNum': targetNum,
    'position':  position,
    '_CSRFToken': $('[name=_CSRFToken]').val()
  });
  
}
// ====================================

function getPreviousSibling(row){
  return $(row).prev('tr:not(.ui-state-disabled)');
}

function getNextSibling(row){
  return $(row).next('tr:not(.ui-state-disabled)');
}

function getRecordNum(row){
  return $(row).find('[name="selectedRecords[]"]').val();
}

//
function toggleCheckboxes(checkedBool) {

  if (checkedBool) {
    $('.selectRecordCheckbox').each(function() {
        this.checked = "checked";
    });
  }
  else {
    $('.selectRecordCheckbox').each(function() {
        this.checked = "";
    });
  }

}

function toggleAdvancedSearchOptions() {
  
  // toggle global variable set by php
  showAdvancedSearch = !showAdvancedSearch;
  
  // send ajax request to update session value
  $.get('?', {
    menu:                      $('#menu').val(),
    _updateShowAdvancedSearch: 1,
    value:                     showAdvancedSearch
  });
  
  // toggle visibility of dependent elements
  $('.hideShowSecondarySearchFields').each(function(){
    var shouldBeVisisble = this.style.display == 'none';
    var method = shouldBeVisisble ? 'show' : 'hide';
    if ($(this).data('animate')) {
      method = shouldBeVisisble ? 'slideDown' : 'slideUp';
    }
    $(this)[method]();
  });

  return false;
}

