<?php

// prepare adminUI() placeholders
$adminUI = [];

// page title
$adminUI['PAGE_TITLE'] = [ t('Reset your password') ];

// form tag and hidden fields
$adminUI['FORM'] = [ 'autocomplete' => 'off' ];
$adminUI['HIDDEN_FIELDS'] = [
  [ 'name' => 'menu',       'value' => 'resetPassword', ],
  [ 'name' => 'submitForm', 'value' => '1', ],
  [ 'name' => 'userNum',    'value' => @$_REQUEST['userNum'], ],
  [ 'name' => 'resetCode',  'value' => @$_REQUEST['resetCode'], ],
  [ 'name' => 'username',   'value' => $GLOBALS['user']['username'], ],
];

// main content
$adminUI['CONTENT'] = ob_capture(function() { ?>

  <div class="form-horizontal">
    
    <?php if (@$_REQUEST['submitForm'] && !alert()): ?>
      <p>
      <?php et("Thanks, we've updated your password!") ?><br><br>
      <a href="?"><?php et('&lt;&lt; Back to Login Page') ?></a>
      </p>
    <?php else: ?>
      <div class='form-group'>
        <div class="col-sm-6 control-label">
          <span class="label"><?php et('Username') ?></span>
        </div>
        <div class="col-sm-6">
          <input class="text-input" type="text" readonly="readonly" value="<?php echo htmlencode( $GLOBALS['user']['username'] ); ?>">
        </div>
      </div>
      
      <div class='form-group'>
        <div class="col-sm-6 control-label">
          <span class="label"><?php et('New Password') ?></span>
        </div>
        <div class="col-sm-6">
          <input class="text-input" type="password" name="password"  value="<?php echo htmlencode(@$_REQUEST['password']) ?>" <?php disableAutocomplete(); ?>>
        </div>
      </div>
      
      <div class='form-group'>
        <div class="col-sm-6 control-label">
          <span class="label"><?php et('New Password (again)') ?></span>
        </div>
        <div class="col-sm-6">
          <input class="text-input" type="password" name="password:again"  value="<?php echo htmlencode(@$_REQUEST['password:again']) ?>" <?php disableAutocomplete(); ?>>
        </div>
      </div>
      
      <div class='form-group'>
        <div class="col-sm-12 visible-xs">
          <?php echo adminUI_button(['label' => t('Update'), 'name' => 'send', 'value' => '1']); ?>
        </div>
        
        <div class="col-sm-12 center hidden-xs">
          <?php echo adminUI_button(['label' => t('Update'), 'name' => 'send', 'value' => '1']); ?>
        </div>
      </div>

      <p style="float: left; margin-top: 20px">
        <a href="?"><?php et('&lt;&lt; Back to Login Page') ?></a>
      </p>

    <?php endif ?>

  </div>
<?php });

// compose and output the page
adminUI($adminUI);


// eof