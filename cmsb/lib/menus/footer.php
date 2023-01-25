
          <div id="footer" align="center">
            <small>

    <?php
      if (@$GLOBALS['SETTINGS']['footerHTML']) {
        echo getEvalOutput($GLOBALS['SETTINGS']['footerHTML']) . '<br>';
      }

      $executeSecondsString = sprintf(t("%s seconds"), showExecuteSeconds(true));
      echo applyFilters('execute_seconds', $executeSecondsString);
    ?>

    <?php doAction('admin_footer'); ?>
    <!-- -->

            </small>
          </div>

        </div>

      </div>
      <!-- End #main-content -->
    </div>
    <!-- End #main-container -->

    <?php doAction('admin_footer_final') ?>

  </body>
</html>
<?php
  // list all plugin hooks called on this page
  if (!empty($GLOBALS['CURRENT_USER']['isAdmin'])) { echo pluginsCalled(); }

  // list PHP included files
  if (!empty($GLOBALS['CURRENT_USER']['isAdmin'])) {
    $output = "<!--\n  CMS Menu files included on this page (only visible for admins):\n";
    foreach (get_included_files() as $filepath) {
      if (!preg_match("|[\\\\/]lib[\\\\/]menus[\\\\/]|", $filepath)) { continue; } // only include menus
      $output .= "  $filepath\n"; 
    }
    $output .= "-->\n"; 
    print $output;
  }
  // end: list PHP included files
  
// eof