<?php

// prepare adminUI() placeholders
$adminUI = [];

// page title
$adminUI['PAGE_TITLE'] = [ t('Login') ];

// form tag and hidden fields
$adminUI['FORM'] = [ 'action' => parse_url(thisPageUrl(), PHP_URL_PATH) ];
$adminUI['HIDDEN_FIELDS'] = [
  [ 'name' => 'action',      'value' => 'loginSubmit',                                                                  ],
  [ 'name' => 'redirectUrl', 'value' => @$_REQUEST['redirectUrl'] ? $_REQUEST['redirectUrl'] : thisPageUrl(null, true), ],
];

// main content
$adminUI['CONTENT'] = ob_capture(function() { ?>
  <div class="center">
    <p>
      <span class="label"><?php et('Username') ?></span>
      <input class="text-input" type="text" name="username" id="username" value="<?php echo htmlencode(@$_REQUEST['username']) ?>" tabindex="1" autocorrect="off" autocapitalize="off" <?php disableAutocomplete(); ?>>
    </p>
    <script type="text/javascript">document.getElementById('username').focus();</script>

    <p>
      <span class="label"><?php et('Password') ?></span>
      <input class="text-input" type="password" name="password" value="<?php echo htmlencode(@$_REQUEST['password']) ?>" tabindex="2" <?php disableAutocomplete(); ?>>
    </p>

    <p>
      <?php echo adminUI_button(['label' => t('Login'), 'name' => 'login', 'value' => '1', 'tabindex' => '4']); ?>
    </p>

    <p>
      <a href="?menu=forgotPassword"><?php et('Forgot your password?'); ?></a>
    </p>
  </div>
<?php });
$adminUI['CONTENT'] = applyFilters('login_content', $adminUI['CONTENT']);

// compose and output the page
adminUI($adminUI);
