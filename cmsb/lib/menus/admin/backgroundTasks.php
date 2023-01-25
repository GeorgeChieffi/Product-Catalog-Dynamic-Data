<?php

// prepare adminUI() placeholders
$adminUI = [];

// page title
$adminUI['PAGE_TITLE'] = [ t('Admin'), t('Background Tasks') => '?menu=admin&action=bgtasks' ];

// add extra html before form
$adminUI['PRE_FORM_HTML'] = ob_capture('_getPreFormContent');

// form tag and hidden fields
$adminUI['FORM'] = [ 'autocomplete' => 'off' ];
$adminUI['HIDDEN_FIELDS'] = [
  [ 'name' => 'menu',           'value' => 'admin', ],
  [ 'name' => '_defaultAction', 'value' => 'bgtasksSave', ],
  [ 'name' => 'action',         'value' => 'bgtasks', ],
];

// buttons
$adminUI['BUTTONS'] = [];
$adminUI['BUTTONS'][] = [ 'name' => 'action=bgtasksSave', 'label' => t('Save'),   ];
$adminUI['BUTTONS'][] = [ 'name' => 'action=bgtasks', 'label' => t('Cancel'), ];

// main content
$adminUI['CONTENT'] = ob_capture('_getContent');

// compose and output the page
adminUI($adminUI);


//
function _getPreFormContent() {
  global $SETTINGS;

  // Check if the Background tasks ran over 24 hours ago.
  $errorsAndAlerts        = "";

  $secondsSinceLastRun = time() - intval($SETTINGS['bgtasks_lastRun']);
  $hasRunInLastHour    = $secondsSinceLastRun <= (60*60*1);

  if ($SETTINGS['bgtasks_disabled']) { $errorsAndAlerts .= t("Warning: Background tasks are currently disabled."). "<br>\r\n"; }
  elseif (!$hasRunInLastHour)        { $errorsAndAlerts .= t("Warning: Background tasks have not run in the last hour, please follow the instructions below to enable background tasks.") . "<br>\r\n"; }
  // Display errors
  if ($errorsAndAlerts) {
    ?>
    <div class="alert alert-danger">
      <button class="close">×</button>
      <i class="fa fa-exclamation-triangle"></i> &nbsp;
      <span>
        <?php echo $errorsAndAlerts; ?>
      </span>
    </div>
    <?php
  }

}

function _getContent() {
  global $SETTINGS;

  $prettyDate = prettyDate($SETTINGS['bgtasks_lastRun']);
  $dateString = $SETTINGS['bgtasks_lastRun'] ? date("D, M j, Y - g:i:s A", $SETTINGS['bgtasks_lastRun']) : $prettyDate;
  $logCount   = mysql_count('_cron_log');
  $failCount  = mysql_count('_cron_log', array('completed' => '0'));

?>
    <div class="form-horizontal">

      <?php echo adminUI_separator([
          'label' => t('Background Tasks'),
          'href'  => "?menu=admin&action=bgtasks#background-tasks",
          'id'    => "background-tasks",
        ]);
      ?>

      <div class="form-group">
        <div class="col-sm-3 control-label">
          <?php eht('Overview'); ?>
        </div>
        <div class="col-sm-8 control-label">
          <div class="text-left">
            <?php echo t("Background tasks allow programs to run in the background at specific times for tasks such as maintenance, email alerts, etc.\n"."You don't need to enable this feature unless you have a plugin that requires it."); ?>
          </div>
        </div>
      </div>

      <div class="form-group">        
        <div class="col-sm-3 control-label">
          <?php eht('Setup Instructions'); ?>
        </div>
        <div class="col-sm-8 control-label">
          <div class="text-left">

            <script>
              $(function() {
                // selector for background task instructions
                $('#backgroundTaskSelect').change(function() {
                  
                  var $instructions = $('.backgroundTaskInstructions');
                  var selection = '.' + this.value;
                  
                  // hide all instructions
                  $instructions.slideUp();
                  
                  // show selected instruction
                  $instructions.filter(selection).slideDown();
                  
                });
              });
            </script>
            
            
              <?php
                // get hostname without port or www - from lib/init.php:_init_loadSettings()                
                list($hostnameWithoutPort) = explode(':', strtolower(@$_SERVER['HTTP_HOST']));
                $hostnameWithoutPort       = preg_replace('/[^\w\-\.]/', '', $hostnameWithoutPort); // security: HTTP_HOST is user defined - remove non-filename chars to prevent ../ attacks
                $hostnameWithoutPort       = preg_replace('/^www\./i', '', $hostnameWithoutPort);   // v2.50 - usability: don't require www. prefix so www.example.com and example.com both check for settings.example.com.php
              ?>
              
              <?php
                $cronCommand = _getPhpExecutablePath() .' '. absPath($GLOBALS['PROGRAM_DIR'] ."/cron.php") .' '. $hostnameWithoutPort;
              ?>
            
            <p>How to setup background tasks for
              <select id="backgroundTaskSelect" class="form-control" style="display: inline-block; margin: -0.55em 0em">
                <option value="">&lt;select&gt;</option>
                <option value="plesk">Plesk</option>
                <option value="cpanel">cPanel</option>
                <option value="linux">Linux (command line)</option>
                <option value="windows">Windows (task scheduler)</option>
              </select>

            <ul class="backgroundTaskInstructions plesk" style="display: none;">
              <li>Add a <a href="https://<?php echo urlencode($_SERVER['HTTP_HOST']); ?>:8443/smb/scheduler/tasks-list">Scheduled Task</a></li>
              <li>Webspace: <?php echo htmlencode($_SERVER['HTTP_HOST']); ?></li>
              <li>Task type: Run a PHP script</li>
              <li>Script Path: <code><?php echo absPath($GLOBALS['PROGRAM_DIR'] ."/cron.php"); ?></code></li>
              <li>with arguments: <code><?php echo htmlencode($hostnameWithoutPort); ?></code></li>
              <li>Use PHP version: <code><?php echo phpversion() ?></code></li>
              <li>Run: Cron Style: <code>* * * * *</code></li>
              <li>Description: <?php echo htmlencode($_SERVER['HTTP_HOST']); ?> CMS scheduled tasks</li>
              <li>Notify: Every time (notifications should only be sent if there are errors)</li>
            </ul>

            <ul class="backgroundTaskInstructions cpanel" style="display: none;">
              <li>Add a <a href="https://<?php echo urlencode($_SERVER['HTTP_HOST']); ?>:2083/frontend/paper_lantern/cron/index.html">Cron Job</a></li>
              <li>Common Settings: <strong>Once Per Minute(* * * * * )</strong></li>
              <li>Command: <code><?php echo $cronCommand; ?></code></li>
            </ul>
            
            <ul class="backgroundTaskInstructions linux" style="display: none;">
              <li>Run the command <code>crontab -e</code> to open the cron job editor.</li>
              <li>Add the following line and save: <br><code>* * * * * <?php echo htmlencode($cronCommand); ?></code></li>
              </li>
            </ul>
            
            <ol class="backgroundTaskInstructions windows" style="display: none;">
              <li>Open Start &gt; Task Scheduler</li>
              <li>Select Action &gt; Create Task</li>
              <li>
                Under <strong>General</strong> tab:
                <ol>
                  <li>Give the task a Name (e.g. "CMSB Scheduled Tasks")</li>
                  <li>
                    Check "Run whether user is logged on or not"
                    <ul>
                      <li>This requires the user to have "log on as batch job" rights.</li>
                    </ul>
                  </li>
                  <li>Check "Hidden"</li>
                </ol>
              </li>
              <li>
                Under <strong>Triggers</strong> tab:
                  <ol>
                    <li>Click "New..."</li>
                    <li>
                      Check "Repeat task every:"
                      <ul>
                        <li>Select "5 minutes" and a duration of "Indefinitely"</li>
                        <li>Click in the field to manually change "5 minutes" to "1 minute"</li>
                      </ul>
                    </li>
                    <li>Click "OK"</li>
                  </ol>
              </li>
              <li>
                Under <strong>Actions</strong> tab:
                <ol>
                  <li>Click "New..."</li>
                  <li>Enter/browse for PHP executable: <code><?php echo _getPhpExecutablePath(); ?></code></li>
                  <li>Add the CMSB cron command in "Add arguments": <br><code><?php echo absPath($GLOBALS['PROGRAM_DIR'] ."/cron.php") . ' '. $hostnameWithoutPort; ?></code></li>
                  <li>Click "OK"</li>
                </ol>
              </li>
              <li>Click "OK" to finalize the new task.</li>
            </ol>


          </div>
        </div>
      </div>
      <div class="form-group">
        <div class="col-sm-3 control-label">
          <?php eht('Status'); ?>
        </div>
        <div class="col-sm-8 control-label">
          <div class="text-left">
            <?php et('Last Run'); ?>:
            <span style='text-decoration: underline' title='<?php echo $dateString; ?>'><?php echo htmlencode($prettyDate); ?></span>
            - <a href="cron.php"><?php eht("run now >>"); ?></a><br>

            <?php et("Email Alerts: If tasks fail an email alert will be sent to admin (max once an hour)."); ?><br>

            <?php et("Log Summary: "); ?>
            <a href="?menu=_cron_log&amp;completed_match=&amp;showAdvancedSearch=1&amp;_ignoreSavedSearch=1"><?php echo $logCount ?> <?php et("entries"); ?></a>,
            <a href="?menu=_cron_log&amp;completed_match=0&amp;showAdvancedSearch=1&amp;_ignoreSavedSearch=1"><?php echo $failCount ?> <?php et("errors"); ?></a>
            - <a href="#" onclick="return redirectWithPost('?', {menu:'admin', action:'bgtasksLogsClear', '_CSRFToken': $('[name=_CSRFToken]').val()});"><?php et("clear all"); ?></a>
          </div>
        </div>
      </div>
      <div class="form-group">
        <div class="col-sm-3 control-label">
          <?php eht('Recent Activity'); ?>
        </div>
        <div class="col-sm-8 control-label">
          <div class="text-left">
            <div class="table-wrap">
            <div align="center" style="padding-bottom: 5px"><a href="?menu=_cron_log"><?php eht("Background Tasks Log >>"); ?></a></div>
            <table class="data table table-striped table-hover">
              <thead>
                <tr>
                  <th><?php et('Date'); ?></th>
                  <th><?php et('Activity'); ?></th>
                  <th><?php et('Summary'); ?></th>
                  <th><?php et('Completed'); ?></th>
                </tr>
              </thead>
              <tbody>
              <?php
                $recentRecords = mysql_select('_cron_log', "true ORDER BY num DESC LIMIT 5");
                if ($recentRecords):
              ?>
              <?php foreach ($recentRecords as $record): ?>
                <tr class="listRow">
                  <td><?php echo htmlencode($record['createdDate']); ?></td>
                  <td>
                    <a href="?menu=_cron_log&amp;action=edit&amp;num=<?php echo $record['num'] ?>"><?php echo htmlencode($record['activity']); ?></a><br>
                    <?php /* <small><?php echo htmlencode($record['runtime']); ?> seconds</small> */ ?>
                  </td>
                  <td><?php echo htmlencode($record['summary']); ?></td>
                  <td><?php echo $record['completed'] ? t('Yes') : t('No'); ?></td>
                </tr>
              <?php endforeach ?>
              <?php else: ?>
                <tr>
                  <td colspan="4"><?php et('None'); ?></td>
                </tr>
              <?php endif ?>
              </tbody>
              </table>
              </div>
            </div>
        </div>
      </div>
      <div class="form-group">
        <div class="col-sm-3 control-label">
          <?php eht('Scheduled Tasks'); ?>
        </div>
        <div class="col-sm-8 control-label">
          <div class="text-left">
            <div class="table-wrap">
            <table class="data table table-striped table-hover">
              <thead>
                <tr style="text-align: left;">
                  <th><?php et('Function'); ?></th>
                  <th><?php et('Activity'); ?></th>
                  <th><?php et('Last Run'); ?></th>
                  <th><?php et('Frequency'); ?> (<a href="http://en.wikipedia.org/wiki/Cron#CRON_expression" target="_blank">?</a>)</th>
                </tr>
              </thead>
              <tbody>
              <?php
                $cronRecords = getCronList();
                if ($cronRecords):
              ?>
              <?php foreach ($cronRecords as $record): ?>
                <tr class="listRow">
                  <td><?php echo htmlencode($record['function']); ?></td>
                  <td><?php echo htmlencode($record['activity']); ?></td>
                  <td><?php
                      $latestLog = mysql_get('_cron_log', null, ' `function` = "' .mysql_escape($record['function']). '" ORDER BY num DESC');
                      echo prettyDate( $latestLog['createdDate']??false );
                    ?></td>
                  <td><?php echo htmlencode($record['expression']); ?></td>
                </tr>
              <?php endforeach ?>
            <?php else: ?>
              <tr>
                <td colspan="4"><?php et('None'); ?></td>
              </tr>
            <?php endif ?>
              </tbody>
            </table>
            </div>
          </div>
        </div>
      </div>
      
      <div class="form-group">
        <div class="col-sm-3 control-label">
          <label><?php et('Background Task Log Limit');?></label>
        </div>
        <div class="col-sm-8">
            
            <input type="text" name="cronLogLimit" value="<?php echo $SETTINGS['cronLogLimit']; ?>"><br>
            <?php et('Limits the Background Task log to a maximum number of entries. This can be used to reduce disk space usage on sites with many background tasks. Set to 0 for no limit.'); ?>
            
        </div>
      </div>
      
      <div class="form-group <?php $SETTINGS['bgtasks_disabled'] ? print('text-danger') : ''; ?>">
        <div class="col-sm-3 control-label">
          <?php et('Disable Background Tasks');?>
        </div>
        <div class="col-sm-8">
           <div class="checkbox">
            <label >
              <input type="hidden" name="bgtasks_disabled" value="0">
              <input name="bgtasks_disabled" <?php checkedIf($SETTINGS['bgtasks_disabled'], '1'); ?> value="1" type="checkbox">
              <?php et('Temporarily disable background tasks (for debugging or maintenance)'); ?>
            </label>
          </div>
        </div>
      </div>

    </div>
  <?php
}

// eof