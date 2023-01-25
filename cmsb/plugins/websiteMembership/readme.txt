PLUGIN README
websiteMembership - Website membership functions for user sign-up, password
reminder, login, user specific content, and login only content
-------------------------------------------------------------------------------


GETTING STARTED
-------------------------------------------------------------------------------
Please be aware that this is a fairly advanced plugin.  It will take care of
the basics and save you a lot of time implementing website membership
functions, but adding functionality beyond the basics may require more advanced
PHP/MySQL knowledge.

First, go to: Admin > Code Generator > Website Memebership and create each of
the files.

Next, check the following variables at the top of the websiteMembership.php
plugin file and update them if nessicary if you intend to use different filenames
than the defaults.

$GLOBALS['WEBSITE_LOGIN_LOGIN_FORM_URL']  = '/user-login.php';             // url to login form
$GLOBALS['WEBSITE_LOGIN_SIGNUP_URL']      = '/user-signup.php';            // signup url linked to from the login page
$GLOBALS['WEBSITE_LOGIN_REMINDER_URL']    = '/user-password-request.php';  // password reminder url linked to from the login page
$GLOBALS['WEBSITE_LOGIN_RESET_URL']       = '/user-password-reset.php';    // password reminder url linked to from the login page
$GLOBALS['WEBSITE_LOGIN_PROFILE_URL']     = '/user-profile.php';           // url to "edit my profile" page

If you already have pages with the default names or just want to use those
then you can leave these variables as is.


CREATING A SIGN-UP FORM
-------------------------------------------------------------------------------
Follow these steps to create a sign-up form:

- Create a "Signup / Create an account" in the Code Generator and save it with
the recommended filename.  If you want to use a different filename or path be
sure to update $GLOBALS['WEBSITE_LOGIN_SIGNUP_URL']

- Access the page through your web browser and try creating a user.  Once created,
confirm that the new user shows up under "User Accounts" in the CMS.

- To add additional fields copy the existing code and insert your new fieldname
in these places: error checking, add user (mysql insert code), html form fields

- If you want newly created users to have access to the CMS set $setAccessRights
to true and examine and update the code under that line.

- Add any additional design to the page as required, re-test everything
works as expected, and then continue.


CREATING A LOGIN PAGE
-------------------------------------------------------------------------------
Follow these steps to create a login form:

- Create a "Login Page" in the Code Generator and save it with the recommended
filename.  If you want to use a different filename or path be sure to update
$GLOBALS['WEBSITE_LOGIN_LOGIN_FORM_URL']

- Test logging in with an invalid username to ensure error messages display
correctly.

- Test sign-up link goes to sign-up page as expected.

- If you've set a value for $GLOBALS['WEBSITE_LOGIN_POST_LOGIN_URL'], test that
after logging in with a valid username and password you are redirected to that URL.

- Add any additional design to the page as required, re-test everything
works as expected, and then continue.


CREATING A PASSWORD RESET PAGE
-------------------------------------------------------------------------------
Follow these steps to create a password reminder form:

- Create a "Password Request" and "Password Reset" in the Code Generator and save
them with the recommended filenames.  If you want to use a different filenames or
path be sure to update $GLOBALS['WEBSITE_LOGIN_REMINDER_URL'] and
$GLOBALS['WEBSITE_LOGIN_RESET_URL']

- Test with a non-existant username or email to ensure error messages display
correctly.

- Test with a valid username or email to ensure a password reminder email gets
sent.

- TIP: For easier testing of outgoing email set: Admin > General Settings > Outgoing Mail
to "Send & Log" and you will be able to review a log of outgoing emails under:
Admin > Outgoing Email.

- Add any additional design to the page as required, re-test everything
works as expected, and then continue.


CREATING A EDIT PROFILE PAGE
-------------------------------------------------------------------------------
Follow these steps to create a edit profile form:

- Create a "Edit Profile" in the Code Generator and save it with the recommended
filename.  If you want to use a different filename or path be sure to update
$GLOBALS['WEBSITE_LOGIN_PROFILE_URL']

- Create a login page if you haven't already do so and login to the website.

- Access the page through your web browser and try updating user fields.  Once
updated, confirm that the new field values show up under "User Accounts" in the CMS.

- To add additional fields copy the existing code and insert your new fieldname
in these places: error checking, update user (mysql insert code), html form fields

- Add any additional design to the page as required, re-test everything
works as expected, and then continue.

Note: If you don't need the "Delete Account" feature, feel free to remove the
HTML for that.


MAKING PAGES LOGIN ONLY
-------------------------------------------------------------------------------
Follow these steps to require a user to login before they can view a page:

- Make sure the viewer_functions.php are loaded in the header of the page and
that the path is correct for your server.
Example: <?php require_once "cmsb/lib/viewer_functions.php"; ?>

- Add this line directly below viewer_functions.  Example:
<?php require_once "cmsb/lib/viewer_functions.php"; ?>
<?php if (!$CURRENT_USER) { websiteLogin_redirectToLogin(); } ?>

- Test logging off and then accessing the page.  You should be redirected to
the login page.

- Test accessing the page after logging in.  You should be able to view the
page without any issues.


SHOWING DIFFERENT CONTENT BASED ON USER
-------------------------------------------------------------------------------
Follow these steps to display different content based on login status:

- Make sure the viewer_functions.php are loaded in the header of the page and
that the path is correct for your server.
Example: <?php require_once "cmsb/lib/viewer_functions.php"; ?>

- Use this code to display content for logged in users only:
<?php if ($CURRENT_USER): ?>
  Only logged in users will be able to see this.
<?php endif ?>

- Use this code to display content for users who aren't logged in:
<?php if (!$CURRENT_USER): ?>
  Only logged who are _not_ logged in will see this:
<?php endif ?>

- This code would show either the users name or a login message in the header:
<?php if ($CURRENT_USER): ?>
  Welcome, <?php echo $CURRENT_USER['username'] ?>!
  <a href="<?php echo $GLOBALS['WEBSITE_LOGIN_PROFILE_URL'] ?>">Edit Profile</a> |
  <a href="?action=logoff">Logoff</a><br/>
<?php else: ?>
  <a href="<?php echo $GLOBALS['WEBSITE_LOGIN_LOGIN_FORM_URL'] ?>">Login</a>
<?php endif ?>


HOW TO USE EMAIL AS USERNAME
-------------------------------------------------------------------------------
To disable usernames and have users user their emails to login make the following
changes.

In both the signup and profile page, set $useUsernames = false;

After making changes test both forms to make sure they work as expected.


HOW TO ADD A TEXT FIELD
-------------------------------------------------------------------------------
Follow these steps:

- Add a field in the CMS field editor under the User Accounts (For this example
we'll pretend your field is called 'city').

- Edit your php form (signup.php or profile.php) and copy and paste an error
checking line:

  if (!@$_REQUEST['city']) { $errorsAndAlerts .= "You must enter your city!<br/>\n"; }

- Edit your php form and copy and paste a mysql update line:

  $colsToValues['city']         = $_REQUEST['city'];

- Edit your form and copy and paste an input field for the user:

  <tr>
   <td>City</td>
   <td><input type="text" name="City" value="<?php echo htmlencode(@$_REQUEST['city']); ?>" size="50" /></td>
  </tr>

- Test that the form lets you save a value, displays the last saved value, and
that saved values are also displayed correctly in the CMS under: User Accounts.


HOW TO ADD RADIO FIELDS WITH VALUES LOADED FROM THE CMS
-------------------------------------------------------------------------------
Follow these steps:

- Add a field in the CMS field editor under the User Accounts (For this example
we'll pretend your field is called 'interest')

- Edit your php form (signup.php or profile.php) and copy and paste an error
checking line:

  if (!@$_REQUEST['interest']) { $errorsAndAlerts .= "You must select your interest!<br/>\n"; }

- Edit your php form and copy and paste a mysql update line:

  $colsToValues['interest']         = $_REQUEST['interest'];

- Edit your form and copy and paste an input field for the user:

  <tr>
   <td valign="top">Interest</td>
   <td>
     <?php $fieldname = 'interest'; ?>
     <?php $idCounter = 0; ?>
     <?php foreach (getListOptions(accountsTable(), $fieldname) as $value => $label): ?>
       <?php $id = "$fieldname." . ++$idCounter; ?>
       <input type="radio" name="<?php echo $fieldname ?>" id="<?php echo $id ?>"
             value="<?php echo htmlencode($value) ?>" <?php checkedIf(@$_REQUEST[$fieldname], $value) ?> />
       <label for="<?php echo $id ?>"><?php echo htmlencode($value) ?></label><br/>

     <?php endforeach ?>
   </td>
  </tr>

- Test that the form lets you save each possible value, displays the last saved
value, and that saved values are also displayed correctly in the CMS under:
User Accounts.

ADVANCED TOPICS
-------------------------------------------------------------------------------

- You can access the current users login record in the $CURRENT_USER variable on
any page that calls viewer_functions.php.  To see what fields are available use
this debug code: <?php showme($CURRENT_USER); ?>

- This allows you to show content based on the current users record values.  For
example, if you had a user field called 'premium', and some users had that field
checked, you could show different content to them with this code:
<?php if ($CURRENT_USER['premium']): ?>
  Only premium members would see this.
<?php endif ?>



ADDITIONAL TECHNICAL NOTES
-------------------------------------------------------------------------------
- User login sessions are not shared between the CMS and the website.  So you need
to login to each separately.  If you'd like to have a unified login set this value
in the plugin: $GLOBALS['WSM_SEPARATE_LOGIN'] = false;

- User accounts for the CMS and the website are stored in the same database table
which is called "accounts".  If you want to use a separate table for website
accounts, set $GLOBALS['WSM_ACCOUNTS_TABLE'] = "website_accounts";

- Users who are created through the website won't have access to any CMS
section unless you allow it by adding code to the signup form.

- Users who are "disabled" in the CMS will not be able to login to the website.

- Users with a 'lastLoginDate' date field will have that value updated every
minute when logged into the website and accessing pages that include
viewer_functions.php


-------------------------------------------------------------------------------
end of file.
