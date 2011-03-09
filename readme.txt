=== PollDaddy Polls & Ratings ===
Contributors: mdawaffe, eoigal
Tags: polls, poll, polldaddy,  wppolls, vote, polling, surveys, rate, rating, ratings
Requires at least: 2.6
Tested up to: 3.1
Stable tag: 1.8.10

Create and manage PollDaddy polls and ratings from within WordPress.

== Description ==

The PollDaddy Polls and Ratings plugin allows you to create and manage polls and ratings from within your WordPress dashboard. You can create polls, choose from 20 different styles for your polls, and view all results for your polls as they come in. All PollDaddy polls are fully customizable, you can set a close date for your poll, create multiple choice polls, choose whether to display the results or keep them private. You can also create your own custom style for your poll. You can even embed the polls you create on other websites. You can collect unlimited votes and create unlimited polls. The new ratings menu allows you to embed ratings into your posts, pages or comments. The rating editor allows you to fully customize you rating. You can also avail of the the 'Top Rated' widget that will allow you to place the widget in your sidebar. This widget will show you the top rated posts, pages and comments today, this week and this month.

PollDaddy Polls is localizable and currently available in:

* English
* Arabic (thanks to <a href="http://www.Ghorab.ws" target="_blank">Ghorab.ws</a>)
* French
* Spanish
* Czech
* Danish
* Khmer
* Tegulu
* Polish (thanks to <a href="http://mkopec.eu" target="_blank">Maciej Kopeć</a>)
* Turkish (thanks to Gürol Barın)

A messages.pot file is included in the plugin - please do send us any language files!

== Installation ==

Upload the plugin to your blog, Activate it, then enter your PollDaddy.com email address and password.

More info here - http://support.polldaddy.com/installing-wordpress-org-plugin/

== Screenshots ==

1. Manage polls
2. Edit poll
3. View poll on page or in a widget
4. Add ratings
5. Ratings on a post
6. Ratings on comments

== Frequently Asked Questions ==

= I have multiple authors on my blog?  What happens? =

Each author that wants to create polls will need his or her own PollDaddy.com account.

= But, as an Administrator, can I edit my Authors' polls =

Yes. You'll be able to edit the polls they create from your blog.  (You won't be able to edit any of their non-blog, personal polls they create through PollDaddy.com.)

= Neat! Um... can my Authors edit MY blog polls? =

Nope.  The permissions are the same as for posts.  So Editors and Administrators can edit anyone's polls for that blog.  Authors can only edit their own.

= Where are my ratings? =

Check your theme's footer.php calls wp_footer. The rating javascript is loaded on this action. 

More info here - http://codex.wordpress.org/Theme_Development#Plugin_API_Hooks

= I cannot access my ratings settings, I am getting a "Sorry! There was an error creating your rating widget. Please contact PollDaddy support to fix this." message. =

You need to select the synchronize ratings account in the Options menu to make sure the ratings API key is valid.

== Change Log ==
= 1.8.10 =
* Updated shortcodes to use latest Polldaddy code
* Fixed minor bug in rating results
* Fixed minor bug in poll editor to allow use of 0 as poll answer
* Added extra check to edit permissions on whether user is a blog member
* Added Turkish and Polish language packs

= 1.8.9 =
* Added option to rating settings to disable ratings results popup
* Fixed bug in choosing rating text color

= 1.8.8 =
* Updated style editor to catch some missing strings so they can be now be localised
* Added string maps to javascript files to allow them to be localised
* Added extra label to ratings settings, vote, so now the label votes has a singular expression for localisation.                              
* Added option to style editor to update all polls that use this style, so any update to style will automatically be reflected in the poll.
* Bug Fix: Embed options are now in readonly text inputs, resolves issue of pre tags being pasted along with embed code/URL in the HTML editor.

= 1.8.7 =
* Added delete option to rating reports to allow you to reset ratings results for posts/pages/comments
* Tidied poll and rating reports tables to use WordPress standard tables

= 1.8.6 =
* Added new Embed options, added short URL to community site and Facebook directory

= 1.8.5 =
* Added option to allow ratings to be excluded from posts and pages

= 1.8.4 =
* Bug Fix: Fix bug in ratings template tag that prevented it from working without being 1st enabled
* Added Arabic language.

= 1.8.3 =
* Bug Fix: Use of WP_Widget caused fatal error in installations pre 2.8
* Added Localisation to Top Rated Widget.

= 1.8.2 =
* Bug Fix: Rating were showing up on front page when posts ratings were enabled
* Added fields to options menu to set the rating id for posts/pages/comments

= 1.8.1 =
* Added a template tag to allow themes to place the rating wherever they want by echoing the function polldaddy_get_rating_html()
* Added shortcodes to text widget, so now all Polldaddy shortcodes will work in the text widget.
* Added an option to synchronize the ratings account API key, useful if key in blog database is out of date or invalidated.

= 1.8.0 =
* Added option to Rating settings to allow ratings on the front page.
* Added more phrases to the pot file.
* Added stylesheet for blos that have a right to left language.
* Bug Fix: Fixed javascript bug, clash with prototype in P2 theme, use of $ function.

= 1.7.9 =
* Bug Fix: Fixed typo in API request URL.

= 1.7.8 =
* Added Options menu, that will allow users to set poll defaults settings, import another PollDaddy account and there is also a setting to allow each blog user to import their own PollDaddy account.
* Added the Top Rated widget.
* Added survey and rating short codes.
* Added a pot file to allow the plugin to be localized.

= 1.7.7 =
* Added a block repeat vote expiration setting to allow users to set how long to block out repeat voters from repeat voting
* Bug Fix: Fixed notices thrown by ratings when first loaded, empty response from API.

= 1.7.6 =
* Added PollDaddy Ratings, you can now add ratings to your posts, pages and comments
* Bug Fix: Sub-menu now highlights the correct option

= 1.7.5 =
* Bug Fix: Added fix for php warning when custom styles array empty
* Bug Fix: Added fix for cookie&ip option not getting set due to API change

= 1.7.4 =
* Bug Fix: Added fix for missing styles array (used when javascript is disabled)
* Bug Fix: Added fix for Internet Explorer 8 and jQuery fadeIn, fadeOut methods
* Bug Fix: Fixed some php warnings

= 1.7.3 =
* Added poll option to allow you to set limit on number of answers for multiple choice polls.

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
