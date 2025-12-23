=== Elementor Forms Spam Blocker ===
Contributors: yourname
Tags: elementor, forms, spam, blocker, filter, keywords
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Detect and block spam submissions in Elementor Forms based on a customizable keyword blocklist.

== Description ==

Elementor Forms Spam Blocker helps you protect your website from unwanted form submissions by detecting spam keywords in form fields like subject and message.

**Features:**

* **Keyword-based spam detection** - Block submissions containing specific keywords
* **Case-insensitive matching** - Keywords are matched regardless of case
* **Exact word matching** - Only complete words are matched, not partial matches
* **Two detection modes:**
  * **Reject mode** - Show an error and prevent the submission
  * **Silent mode** - Accept the submission but don't send any emails
* **Customizable fields** - Choose which form fields to scan for spam
* **Easy-to-use admin interface** - Manage everything from WordPress Settings

**Default Blocked Keywords:**

* backlink
* link building
* buy links
* seo services
* guest post
* guest posting
* link exchange
* paid links
* dofollow links

== Installation ==

1. Upload the `elementor-forms-spam-blocker` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Settings → Forms Spam Blocker to configure the plugin

== Frequently Asked Questions ==

= Does this require Elementor Pro? =

Yes, this plugin requires Elementor Pro with the Forms widget to be installed and activated.

= How does keyword matching work? =

Keywords are matched as complete words only, case-insensitive. For example, the keyword "backlink" will match "I offer backlink services" but will NOT match "backlinks" or "backlink-building".

= What's the difference between Reject and Silent mode? =

* **Reject mode**: Shows an error message to the user and prevents the form from being submitted entirely.
* **Silent mode**: Accepts the submission (shows success to the user) but silently prevents all email notifications from being sent. The submission is still saved in Elementor's submission logs.

= Can I add my own keywords? =

Yes! Go to Settings → Forms Spam Blocker and add or remove keywords as needed.

== Changelog ==

= 1.0.0 =
* Initial release

