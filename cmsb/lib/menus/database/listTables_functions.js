
$(document).ready(function(){ init(); });


//
function init() {
  initSortable(null, updateTableOrder);
}

//
function updateTableOrder(row, table){	
      // get new order
      var rows       = table.tBodies[0].rows;
      var tableNames = '';
      for (var i=0; i<rows.length; i++) {
          var thisName = $("._tableName", rows[i]).val();
          if (thisName) {
            if (tableNames != '') { tableNames += ','; }
            tableNames += thisName;
          }
      }
     
  redirectWithPost('?', {
    'menu':       'database',
    'action':     'listTables',
    'newOrder':       tableNames,
    '_CSRFToken': $('[name=_CSRFToken]').val()
  });

}

//
function confirmEraseTable(tableName) {

  var isConfirmed = confirm("Delete this menu?\n\nWARNING: All data will be lost!\n ");
  if (isConfirmed) {
//    window.location="?menu=database&action=editTable&dropTable=1&tableName=" + tableName;
    redirectWithPost('?', {
      'menu':       'database',
      'action':     'editTable',
      'dropTable':  '1',
      'tableName':  tableName,
      '_CSRFToken': $('[name=_CSRFToken]').val()
    });
    
  }
}

//
function addNewMenu(tablename, fieldname) {
  
  $('#addEditorModal').modal();
  resetAddTableFields();
  
}
