

Sends Gravity Forms submissions to a RESTful API and stores them locally.

== Description ==

This plugin creates an add-on for Gravity Forms that:

* Automatically sends all form submission data to a configurable API endpoint
* Stores submission data and API responses in a JSON file
* Provides an admin interface to view recent submissions
* Integrates seamlessly with Gravity Forms using WordPress hooks

== Installation ==

1. Make sure Gravity Forms is installed and activated
2. Upload the plugin folder to the `/wp-content/plugins/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Configure the API endpoint in the Settings page

== Frequently Asked Questions ==

= Where is the submission data stored? =

Form submissions are stored in a JSON file located at: `/wp-content/uploads/gf_submissions.json`

= Can I change the API endpoint? =

Yes, you can change the API endpoint in the plugin settings page.

== Changelog ==

= 1.0.0 =
* Initial release