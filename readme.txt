=== Polldaddy Polls & Ratings ===
Contributors: eoigal, mdawaffe, donncha, johnny5, panosktn
Tags: polls, poll, polldaddy, wppolls, vote, polling, surveys, rate, rating, ratings
Requires at least: 3.3
Tested up to: 4.5.2
Stable tag: 2.0.33

Create and manage Polldaddy polls and ratings from within WordPress.

== Description ==

The Polldaddy Polls and Ratings plugin allows you to create and manage polls and ratings from within your WordPress dashboard. You can create polls, choose from 20 different styles for your polls, and view all results for your polls as they come in. All Polldaddy polls are fully customizable, you can set a close date for your poll, create multiple choice polls, choose whether to display the results or keep them private. You can also create your own custom style for your poll. You can even embed the polls you create on other websites. You can collect unlimited votes and create unlimited polls. The new ratings menu allows you to embed ratings into your posts, pages or comments. The rating editor allows you to fully customize your rating. You can also avail of the 'Top Rated' widget that will allow you to place the widget in your sidebar. This widget will show you the top rated posts, pages and comments today, this week and this month.

The Polldaddy plugin requires PHP 5.

Polldaddy Polls is currently available in the following languages:

* Arabic
* Bosnian
* Bulgarian
* Chinese (Taiwan)
* Croatian
* Czech
* Danish
* Dutch
* Finnish
* French (Canada)
* French (France)
* French (Switzerland)
* Galician
* German
* Greek (Polytonic)
* Hebrew
* Hungarian
* Indonesian
* Irish
* Italian
* Korean
* Lithuanian
* Malay
* Norwegian
* Norwegian (Nynorsk)
* Persian
* Polish
* Portuguese (Brazil)
* Portuguese (Portugal)
* Romanian
* Russian
* Serbian
* Slovak
* Spanish (Puerto Rico)
* Spanish (Spain)
* Swedish
* Uighur

Want to help translate the plugin or keep an existing translation up-to-date? Head on over to the [translation site](http://translate.wordpress.com/projects/polldaddy/plugin).

Some strings are not translated when polls and surveys are embedded. You will have to translate them using a language pack on [http://polldaddy.com/](Polldaddy.com).

== Installation ==

Upload the plugin to your blog (or search for it and install it on your plugins page), activate it, then go to Settings->Polls to configure the plugin. You'll need a Polldaddy API key available from your [Polldaddy account 
page](http://polldaddy.com/account/#apikey) to sync your account and pull in your existing polls and ratings.
Polldaddy.com is now linked to WordPress.com using [WordPress.com Connect](http://en.support.wordpress.com/wpcc-faq/) which means you can use your WordPress.com username and password to login to Polldaddy.com. If you have a WordPress.com account and have never used Polldaddy.com you can login [here](https://polldaddy.com/login/) to access Polldaddy.com. 

You can find further help on our [support page](http://support.polldaddy.com/). If you have any problems please use the [support forum](http://wordpress.org/support/plugin/polldaddy). The plugin also logs activity to a file using the [WP Debug Logger](http://wordpress.org/extend/plugins/wp-debug-logger/) plugin which can be useful in determining the cause of a problem.

== Screenshots ==

1. Manage polls
2. Edit poll
3. View poll on page or in a widget
4. Add ratings
5. Ratings on a post
6. Ratings on comments

== Frequently Asked Questions ==

= Where do I find my Polls & Ratings menus? =

The Polls & Ratings menus can now be found under the Feedbacks top level menu.

= I have multiple authors on my blog?  What happens? =

Each author that wants to create polls will need his or her own Polldaddy.com account.

= But, as an Administrator, can I edit my Authors' polls =

Yes. You'll be able to edit the polls they create from your blog.  (You won't be able to edit any of their non-blog, personal polls they create through Polldaddy.com.)

= Neat! Um... can my Authors edit MY blog polls? =

Nope.  The permissions are the same as for posts.  So Editors and Administrators can edit anyone's polls for that blog.  Authors can only edit their own.

= Where are my ratings? =

Check that footer.php in your theme calls the wp_footer action. The rating javascript is loaded on this action. 

More info [here](http://codex.wordpress.org/Theme_Development#Plugin_API_Hooks)

= My ratings are gone after I reinstalled the plugin. How do I get them back? =

Login to your Polldaddy.com account and [view the ratings](https://polldaddy.com/dashboard/?content=rating) in your dashboard. You should see ratings named "blog name - " comments/posts/pages. You need the rating ID of each of those which is visible when you edit them. It's the number in the URL of your browser that looks like https://polldaddy.com/ratings/1234567/edit/. After you connect the plugin to your Polldaddy account go to Settings->Ratings and make sure the ratings are displayed on your posts/pages/comments as desired. You'll see a link at the bottom of the page saying, "Advanced Settings" that will toggle new configuration settings. One of those settings is "rating ID" which you should replace with the number you got from your Polldaddy account. Now save the changes and the ratings on your site will be updated.

= I cannot access my ratings settings, I am getting a "Sorry! There was an error creating your rating widget. Please contact Polldaddy support to fix this." message. =

You need to select the synchronize ratings account in the WordPress options page at Settings->Polls & Ratings to make sure the ratings API key is valid.

= When I try to use a rating on a page, I get a PHP warning about the post title. =

Your rating uses the filter 'wp_title' by default when retrieving the post title, you may need to remove this in the Polls & Ratings settings to allow ratings to work with your theme.

= Why is a poll loading in the footer of my main page? =

Your theme is getting the post content, without necessarily showing it. If the post has a poll, the poll javascript is loaded in the footer. To fix this, you need to enable the 'Load Shortcodes Inline' setting in the Polls & Ratings settings. This will load the poll shortcode inline and will only load the poll if the content of the post is actually displayed.


== Upgrade Notice ==
Fixed the "top ratings" widget on secure sites

== Changelog ==

= 2.0.33 =
* Do not use Jetpack_Sync if deprecated
* Removed deprecated warnings

= 2.0.32 =
* Fix xss vulnerability when adding Polldaddy links to post content

= 2.0.31 =
* Fixed the "top ratings" widget on secure sites

= 2.0.30 =
* When ratings are displayed at the top of a post separate it with a linebreak, not BR so YT URLs embed properly.
* Fix "parameter missing" when submitting comments when comment ratings are enabled.

= 2.0.29 =
* Whitelist the polldaddy api key blog option so it can be updated by Jetpack.
* Fix label on the poll settings page
* Added a "How to I get my ratings back?" FAQ
* Show ratings in edit-comments.php in WordPress 4.4

= 2.0.28 =
* Don't show "Connect to Polldaddy" notice everywhere. Only on plugins and polls pages

= 2.0.27 =
* Fixed WP_Widget warning.
* Fixed "previous" and "next" links on the poll feedback page.

= 2.0.26 =
* SSL support for poll and survey shortcodes
* Security update of survey shortcode
* Resize the "Add Poll" popup.
* Validate the rating_id before updating it on the ratings settings page.

= 2.0.25 =
* Fixed XSS in ratings shortcode. Props vortfu
* Added forms to allow users to reset and restore their connection settings. Useful to fix rating widget problems.
* The "contact support text is improved. Now it suggests resetting the connection first.

= 2.0.24 =
* Minor security fix: Properly sanitize and escape the rating title filter. Props mazengamal.

= 2.0.23 =
* Added a UI to the ratings settings page to enable or disable the rich snippets support
* Minor bug fixes

= 2.0.22 =
* Some minor updates to Irish, Japanese, Polish and Spanish translations.
* Huge size reduction of language files by stripping unused strings.
* New feedback icon in admin menu
* Improved API documentation and fixed API entry box for new users.
* Improved setup by directing the user to the settings page to enter their API key.
* Fix to rich snippets support. Properly fetch ratings to cache.

= 2.0.21 =
* Fixed CSRF problem in ratings settings page.
* Fixed PHP 5.5.0 warning in class constructor.
* Add rich snippet support for ratings.
* Login to Polldaddy via API Key instead of username and password now.
* Removed "month" and "never" options from poll block expiration dropdown as they're not supported any more.
* Misc bug fixes.

= 2.0.20 =
* Updated settings page: text, layout, Import -> Link.
* On MU sites use blog_public blog option.
* Removed deprecated warnings, props @Till
* 


= 2.0.19 =
* Added filter by category to Top Rated Widget
* Added more retina images for ratings
* Updated edit permissions on poll to allow an editor to edit a poll belonging to a user no longer member of blog
* Fixed minor JS/CSS bugs

= 2.0.18 =
* Update poll editor to allow a user to delete an image from a poll answer
* Fixed bug with new polls not including images when a poll is created
* Fixed bug with missing retina image for polldaddy icon

= 2.0.17 =
* Updated ratings settings to allow blog to show rating in search and archive pages
* Updated how ratings are shown in excerpts which should work better with Jetpack and certain themes

= 2.0.16 =
* Updated menus to only use one Top level menu - Feedback
* Updated Settings->Polls & Ratings menu to break into 2 separate menu items - Settings->Polls / Settings->Ratings
* Updated menus to work with Feedbacks Top level menu item that comes with Jetpack plugin

= 2.0.15 =
* Fix for conflict with jetpack plugin. When both plugins were installed, conflict with older jetpack implementation of shortcode handler and redeclaration of polldaddy plugin function 'polldaddy_link()'
* Updated translations to use latest from glotpress and removed a lot of untranslated text, making plugin a much smaller download.

= 2.0.14 =
* Added support for SSL on the admin dashboard.
* Updated the shortcodes to load more efficiently in the footer.
* Fixed number of minor javascript errors.
* Fixed bug with admin menu and toolbar showing in popup

= 2.0.13 =
* Updated translation files and fixed gettext domain in plugin strings
* Fixed wp_title filter parameter
* Fixed ratings to show on category and archive pages.
* Added better sanitization to stop xss vulnerabilities

= 2.0.12 =
* Fix for CSS bug on admin pages with WordPress 3.3
* Add range of new languages to further localize the plugin
* Updated the shortcodes to be better sanitized to prevent possibility of XSS 

= 2.0.11 =
* Fix for CSS bug on admin pages with WordPress 3.3
* Update Translation files from GlotPress to use 

= 2.0.10 =
* Added option to custom style editor to set direction of text.
* Added option to allow shortcodes to load inline rather than in the footer. Some themes need this.

= 2.0.9 =
* Added support for slider popup polls and variable sized surveys
* Added activity logging
* Added Latvian translation
* Added setting to configure filter used on blog title with ratings
* Fixed bug in preview polls

= 2.0.8 =
* Fixed display of ratings on posts and pages.
* Fixed confirmation dialog when deleting polls
* Changed PollDaddy to Polldaddy

= 2.0.7 =
* Fixed bug in displaying multiple polls in a post
* Fixed bug when using json_encode, it converts utf8 charaters to unicode values in post title but they were not getting escaped properly and thus were not displayed properly in reports or in top rated widget.

= 2.0.6 =
* Tidy up shortcodes - remove keywords from no script tags, inline javascript is now xhtml compatible, load survey and poll javascript files in the footer to assist page load speeds

= 2.0.5 =
* Tested with version 3.2
* Added extra shortcode handler for inline surveys
* Fix Polldaddy icon position on poll pages
* Remove rating javascript code from feeds and ajax

= 2.0.4 =
* Fixed bugs with using new ajax.php in PHP 4
* Fixed issue with conflicts with other plugins using ajax.php
* Fixed bug in poll question media upload
* Fixed bug in adding answers, if you clicked button multiple times, multiple answers were added with same name.

= 2.0.3 =
* Fixed side nav gray theme icon bug introduced by usage of sprite image

= 2.0.2 =
* Added support to the shortcode for alignment. Usage: [polldaddy poll=xxxxxx align=right|left]
* Fixed layout issues in Firefox 4 on embed interface
* Fixed extraneous dividers bug
* Changed nav menu icon to sprite to fix hover flash bug

= 2.0.1 =
* Fixed bug in selecting custom styles in poll editor for webkit browsers

= 2.0 =
* Updated the UI
* Added media embeds in poll editor
* Added poll comments option
* Fixed layout issues when viewing plugin in iframe/popup
* Fixed bug in multiple choices dropdown
* Fixed bug in updating style when updating all polls using that style 

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
* Added Options menu, that will allow users to set poll defaults settings, import another Polldaddy account and there is also a setting to allow each blog user to import their own Polldaddy account.
* Added the Top Rated widget.
* Added survey and rating short codes.
* Added a pot file to allow the plugin to be localized.

= 1.7.7 =
* Added a block repeat vote expiration setting to allow users to set how long to block out repeat voters from repeat voting
* Bug Fix: Fixed notices thrown by ratings when first loaded, empty response from API.

= 1.7.6 =
* Added Polldaddy Ratings, you can now add ratings to your posts, pages and comments
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
* Updated the Polldaddy API Client code
* Removed style picker javascript, now reference static file on Polldaddy
* Bug Fix: Polldaddy Answers link to poll in poll embed code now correct
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
* Added Custom Styles link to Edit poll, under Design. This link will be only present when the user has custom styles created on the Polldaddy.com site.
* Added option to make normal request every login

= 1.2 =
* Bug Fix: SSL request for Polldaddy API key sometimes failed due to host constraints, included option to make a normal http request in this case.
* Bug Fix: Redirect after login now goes to list polls

= 1.1 =
* Bug Fix: Don't call Polldaddy API on every admin page load
* Bug Fix: Correct Image locations
* Bug Fix: CSS Tweaks for upcoming WordPress 2.8
* Make Javascript image selector more robust

= 1.0 =
* New Polldaddy API
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
* Bug fix: Allow reauthentication with Polldaddy email address and Polldaddy password.  This is necessary because the stored Polldaddy User API key is invalidated if the user's details change on Polldaddy.com.
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
