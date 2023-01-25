
function viewCancel() {
  if ($('#returnUrl').val()) { self.location = $('#returnUrl').val(); }
  else                       { self.location = '?menu=' + $('#menu').val(); }
}

