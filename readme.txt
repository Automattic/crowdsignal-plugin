=== PollDaddy Polls ===
Contributors: mdawaffe, eoigal
Tags: polls, poll, polldaddy,  wppolls, vote, polling, surveys
Requires at least: 2.6
Tested up to: 2.8.4
Stable tag: 1.7.2

Create and manage PollDaddy polls from within WordPress.

== Description ==

The PollDaddy Polls plugin allows you to create and manage polls from within your WordPress dashboard. You can create polls, choose from 20 different styles for your polls and view all results for your polls as they come in. All PollDaddy polls are fully customizable, you can set a close date for your poll, create multiple choice polls, choose whether to display the results or keep them private. You can even embed the polls you create on other websites. You can collect unlimited votes and create unlimited polls.

== Installation ==

Upload the plugin to your blog, Activate it, then enter your PollDaddy.com email address and password.

More info here - http://support.polldaddy.com/installing-wordpress-org-plugin/

== Frequently Asked Questions ==

= I have multiple authors on my blog?  What happens? =

Each author that wants to create polls will need his or her own PollDaddy.com account.

= But, as an Administrator, can I edit my Authors' polls =

Yes. You'll be able to edit the polls they create from your blog.  (You won't be able to edit any of their non-blog, personal polls they create through PollDaddy.com.)

= Neat! Um... can my Authors edit MY blog polls? =

Nope.  The permissions are the same as for posts.  So Editors and Administrators can edit anyone's polls for that blog.  Authors can only edit their own.

== Change Log ==
= 1.7.2 =
* Bug Fix: Added fix for open/close poll

= 1.7.1 =
* Bug Fix: Answer text in results now displaying the correct text

= 1.7 =
* Added Poll Style Editor
* Updated the PollDaddy API Client code
* Removed style picker javascript, now reference static file on Polldaddy
* Bug Fix: PollDaddy Answers link to poll in poll embed code now correct
* Bug Fix: iframe view of poll editor now display design area
* Bug Fix: Only print API error once

= 1.6 =
* Added Poll Question and Answer fields now accept limited HTML tags
* Added 'Share This' link option to allow voters to share the poll around the interweb

= 1.5 =
* Bug Fix: Other answers in the poll results are now displaying.

= 1.4 =
* Added new poll styles selector
 
= 1.3 =
* Added Close/Open poll to poll actions
* Added Custom Styles link to Edit poll, under Design. This link will be only present when the user has custom styles created on the PollDaddy.com site.
* Added option to make normal request every login

= 1.2 =
* Bug Fix: SSL request for PollDaddy API key sometimes failed due to host constraints, included option to make a normal http request in this case.
* Bug Fix: Redirect after login now goes to list polls

= 1.1 =
* Bug Fix: Don't call PollDaddy API on every admin page load
* Bug Fix: Correct Image locations
* Bug Fix: CSS Tweaks for upcoming WordPress 2.8
* Make Javascript image selector more robust

= 1.0 =
* New PollDaddy API
* Do not store UserCode, retrieve from API
* Bug Fix: Fix API key retrieval.  Improper use of wp_remote_post()

= 0.9 =
* Compatiblity with WordPress 2.7
* Bug Fix: Potential charset issues

= 0.8 =
* Bug fix: prevent some PHP define errors
* Bug fix: send content-length header when using wp_remote_post()

= 0.7 =
* Potential bug fix: Maybe get rid of 'Invalid Poll Author' error... again.

= 0.6 =
* Bug fix: Allow reauthentication with PollDaddy email address and PollDaddy password.  This is necessary because the stored PollDaddy User API key is invalidated if the user's details change on PollDaddy.com.
* Buf fix: Speed up CSS and JS.
* Feature: Link to view Shortcode and JavaScript code for each poll.

= 0.5 =
* A few more helpful error messages.
* Bug fix: Password field should be a password field, not a text field.
* Potential bug fix: Maybe get rid of 'Invalid Poll Author' error.

= 0.4 =
* Bug fix: Shortcode handler was commented out in earlier versions.
* Bug fix: PHP Warning: in_array() [function.in-array]: Wrong datatype for second argument in polldaddy-xml.php on line 78

= 0.3 =
* Bug fix: Send text data escaped in CDATA to prevent XML errors.
* Bug fix: Append to text value, don't overwrite it.  XML parser can call text handler many times per node.
* Bug fix: No more slashes when a poll reloads in the edit form after an error.

= 0.2 =
* Bug fix: Get rid of slashes.
* Bug fix: PHP Fatal Error: call to undefined function

= 0.1 =
* Initial release
