=== PollDaddy ===
Contributors: mdawaffe
Tags: poll, polls, polldaddy, WordPress.com
Requires at least: 2.6
Tested up to: 2.6.3
Stable tag: 0.8

Create and manage PollDaddy polls from within WordPress.

== Description ==

The PollDaddy plugin allows you to create and manage your [PollDaddy.com](http://polldaddy.com/) polls from within your WordPress blog's administration area.

== Installation ==

Upload the plugin to your blog, Activate it, then enter your PollDaddy.com email address and password.

== Frequently Asked Questions ==

= I have multiple authors on my blog?  What happens? =

Each author that wants to create polls will need his or her own PollDaddy.com account.

= But, as an Administrator, can I edit my Authors' polls =

Yes. You'll be able to edit the polls they create from your blog.  (You won't be able to edit any of their non-blog, personal polls they create through PollDaddy.com.)

= Neat! Um... can my Authors edit MY blog polls? =

Nope.  The permissions are the same as for posts.  So Editors and Administrators can edit anyone's polls for that blog.  Authors can only edit their own.

== Change Log ==

= 0.9 =
* Compatiblity with WordPress 2.7

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
