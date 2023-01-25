<?php
class UploadField extends Field {

function __construct($fieldSchema) {
  parent::__construct($fieldSchema);
}

//
function getTableRow($record, $value, $formType) {
  global $preSaveTempId, $SETTINGS, $menu;

  $prefixText  = @$this->fieldPrefix;
  $description = @$this->description;
  if ($prefixText) { $prefixText .= "<br>"; }

  // create uploadList url
  $uploadList = "?menu=" . urlencode($menu)
              . "&amp;action=uploadList"
              . "&amp;fieldName=" . urlencode($this->name)
              . "&amp;num=" . urlencode(@$_REQUEST['num']??'')
              . "&amp;preSaveTempId=" . urlencode($preSaveTempId??'')
              . "&amp;formType=" . urlencode($formType);

  // error checking
  $errors = '';
  $uploadDir = @$this->useCustomUploadDir ? $this->customUploadDir : $SETTINGS['uploadDir'];
  if     (!file_exists($uploadDir)) { mkdir_recursive($uploadDir); }  // create upload dir (if not possible, dir not exists error will show below)
  if     (!file_exists($uploadDir)) { $errors .= "Upload directory '" .htmlencode($uploadDir). "' doesn't exist!.<br>\n"; }
  elseif (!is_writable($uploadDir)) { $errors .= "Upload directory '" .htmlencode($uploadDir). "' isn't writable!.<br>\n"; }

  // display errors
  if ($errors) { $html = <<<__HTML__
    <div class="form-group">
      <div class="col-sm-2 control-label">
        {$this->label}
      </div>
      <div class="col-sm-10">
        <div id='alert'><span>$errors</span></div>
      </div>
    </div>
__HTML__;
  return $html;
  }

  // display field
  $html  = '';
  $html .= "<div class='form-group'>\n";
  $html .= "  <div class='col-sm-2 control-label'>{$this->label}</div>\n";
  $html .= "  <div class='col-sm-10'>\n";
  
  $html .= "    <p class='help-block'>$prefixText</p>\n";
  $html .= "    <iframe id='{$this->name}_iframe' src='$uploadList' height='100' width='100%' frameborder='0' class='uploadIframe'></iframe><br>\n";

  $html .= "<br>";
    
  $html .= "    <p class='help-block'>$description</p>\n";
  $html .= "  </div>\n";
  $html .= "</div>\n";

  return $html;

}

} // end of class


?>
