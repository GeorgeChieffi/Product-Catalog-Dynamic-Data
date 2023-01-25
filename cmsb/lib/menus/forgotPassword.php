<?php

// prepare adminUI() placeholders
$adminUI = [];

// page title
$adminUI['PAGE_TITLE'] = [ t('Forgot your password?') ];

// form tag and hidden fields
$adminUI['FORM'] = [ 'autocomplete' => 'off' ];
$adminUI['HIDDEN_FIELDS'] = [
  [ 'name' => 'menu',   'value' => 'forgotPassword', ],
  [ 'name' => 'action', 'value' => 'submit',         ],
];

// main content
$adminUI['CONTENT'] = ob_capture(function() { ?>
  <div class="center">
    <?php if (@$GLOBALS['sentEmail'] && !alert()): ?>

      <p>
        <?php et("Thanks, we've emailed you instructions on how to reset your password.") ?><br>
      </p>

      <p>
        <?php printf(t("If you don't receive an email within a few minutes check your spam filter for messages from %s"), $GLOBALS['SETTINGS']['adminEmail']) ?><br>
      </p>

    <?php else: ?>

      <p style="margin-bottom: 20px;">
        <?php et("Enter your username (or email address) to reset your password.") ?><br>
        <?php et("We'll send you an email with instructions and a reset link.") ?><br>
      </p>

      <p>
        <span class="label"><?php et('Lookup') ?></span>
        <input class="text-input" type="text" name="usernameOrEmail" id="usernameOrEmail" value="<?php echo htmlencode(@$_REQUEST['usernameOrEmail']) ?>">
        <?php echo adminUI_button(['label' => t('Send'), 'name' => 'send', 'value' => '1', 'tabindex' => '4']); ?>

      </p>
      <script type="text/javascript">document.getElementById('usernameOrEmail').focus();</script>

    <?php endif ?>

    <p style="float: left; margin-top: 20px">
      <a href="?"><?php et('&lt;&lt; Back to Login Page') ?></a>
    </p>
  </div>
<?php });

// compose and output the page
adminUI($adminUI);
