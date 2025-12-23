# Elementor Forms Spam Blocker

A WordPress plugin that detects and blocks spam submissions in Elementor Pro Forms based on a customizable keyword blocklist.

![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple)
![License](https://img.shields.io/badge/License-GPLv2-green)

## Features

- **Keyword-based spam detection** — Block form submissions containing specific spam keywords
- **Case-insensitive matching** — Keywords are matched regardless of case
- **Exact word matching** — Only complete words are matched (e.g., "backlink" won't match "backlinks")
- **Two detection modes:**
  - **Reject mode** — Show an error message and prevent submission entirely
  - **Silent mode** — Accept the submission but silently block all email notifications
- **Customizable fields** — Choose which form fields to scan for spam keywords
- **Visual spam indicators** — Blocked submissions are clearly marked in the admin panel
- **Easy-to-use admin interface** — Manage everything from WordPress Settings

## Screenshots

### Settings Page
Configure detection mode, fields to scan, and manage your keyword blocklist.

<img width="2834" height="1280" alt="screencapture-null100-wp-admin-admin-php-2025-12-24-00_08_12" src="https://github.com/user-attachments/assets/5e30778b-d83b-485f-93a1-c7e79b2ff228" />


### Blocked Submission (Silent Mode)
Submissions blocked in silent mode show a clear spam status indicator.

<img width="2026" height="2943" alt="screencapture-null100-wp-admin-options-general-php-2025-12-24-00_07_12" src="https://github.com/user-attachments/assets/652e62dc-b06c-443c-abab-75423f470d31" />


## Requirements

- WordPress 5.0+
- PHP 7.4+
- Elementor Pro with Forms widget

## Installation

1. Download the latest release ZIP file
2. Go to WordPress Admin → Plugins → Add New → Upload Plugin
3. Upload the ZIP file and activate
4. Go to Settings → Forms Spam Blocker to configure

## Configuration

### Detection Mode

- **Reject**: Shows an error message to the user and completely prevents the form submission (no record saved, no email sent)
- **Silent**: Accepts the submission (saves the record, shows success to user) but silently blocks all email notifications

### Fields to Scan

Enter the field IDs from your Elementor form, separated by commas. You can find field IDs in:
Elementor → Edit Form → Click on field → Advanced tab → ID field

Example: `subject, message`

### Blocked Keywords

Add keywords that should trigger spam detection. Keywords are matched as exact words (case-insensitive).

**Default keywords included:**
- backlink, link building, link-building
- buy links, seo services
- guest post, guest posting
- link exchange, paid links, dofollow links

### Rejection Message

Customize the error message shown when a submission is rejected (only applies to Reject mode).

Default: *"Your message could not be sent. Please try again later."*

## How It Works

1. When a form is submitted, the plugin scans the specified fields for blocked keywords
2. **Reject mode**: If a keyword is found, the form submission is blocked immediately with an error message
3. **Silent mode**: If a keyword is found, the submission is saved but:
   - All email notifications are blocked
   - A "⚠️ Spam Status" field is added to the submission
   - The Actions Log shows "⚠️ Blocked by Spam Blocker (email not sent)"

## Changelog

### 1.8.0
- Fixed silent mode email blocking
- Added spam status field to blocked submissions
- Added spam indicator in Actions Log
- Improved field scanning (checks multiple identifiers)
- Added extensive debug logging

### 1.0.0
- Initial release

## License

This plugin is licensed under the GPL v2 or later.

## Support

If you encounter any issues or have feature requests, please open an issue on GitHub.

## Credits

Developed for use with [Elementor Pro](https://elementor.com/) Forms widget.

