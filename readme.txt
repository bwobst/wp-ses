=== WP SES ===
Contributors: SylvainDeaure, rkaiser0324
Author URI: https://www.digipowers.com/
Donate link: http://wp-ses.com/donate.html
Tags: email,ses,amazon,webservice,delivrability,newsletter,autoresponder,mail,wp_mail,smtp,service
Requires at least: 3.0.0
Tested up to: 4.9.8
Requires PHP: 7
Stable tag: trunk

Send all outgoing WordPress emails through Amazon Simple Email Service (SES) for maximum email deliverability.

== Description ==

WP SES overrides the local wp_mail() function to send outgoing WordPress emails through Amazon Simple Email Service (SES), which 
ensures high email deliverability, email traffic statistics, and a powerful managed infrastructure.

Features:

* Dashboard to display configuration, sending quota, and statistics
* Override WordPress default Sender Email and Name
* Validation of Amazon API Credentials
* Custom Reply-To or from Headers
* HTML template support, either inline or via an external file
* SES Endpoint selection     
* Attachment support, compatible with [Contact Form 7](https://contactform7.com/)
* Logging, to aid in debugging

Links:

* [The original plugin page](http://wp-ses.com/features.html) lists more features but is not up to date with this version
* [Amazon SES](http://aws.amazon.com/ses/)
* This plugin uses a fork of the [Amazon Simple Email Service PHP class](http://sourceforge.net/projects/php-aws-ses/)

== Installation ==

1. The PHP cURL extension must be enabled.
2. Via the AWS SES console, confirm the sender email and ensure your account is in "Production" mode
3. Install and activate the plugin
4. In Settings->WP SES:
    1. Enter the verified sender email and name to use as the sender for all emails
    2. Enter the AWS keys.  The keys must be associated with an IAM user who has the SES permissions detailed in [this Gist](https://gist.github.com/rkaiser0324/ec59c11558699da1638b5829e3d233fe)
    3. Save changes
    4. Send a test email, optionally with an attachment.  The SendRawEmail SES permission is needed to send attachments, per the Gist above.

You can alternatively set the following configurations via defines in wp-config.php:

`
// AWS Access Key
define('WP_SES_ACCESS_KEY', 'blablablakey');

// AWS Secret Key  
define('WP_SES_SECRET_KEY', 'blablablasecret');

// AWS Endpoint
define('WP_SES_ENDPOINT', 'email.us-east-1.amazonaws.com');  

// From address
define('WP_SES_FROM', 'myaddress@domain.com');

// Return path for bounced emails 
define('WP_SES_RETURNPATH', 'returnaddress@domain.com');

// ReplyTo - This will get the replies from the recipients.  
// Set to an address, or 'headers' for using the 'replyto' from the headers.   
define('WP_SES_REPLYTO', 'headers');

// Hide list of verified emails
define('WP_SES_HIDE_VERIFIED', true);

// Auto activate the plugin for all sites
define('WP_SES_AUTOACTIVATE', true);

// Send every email to a defined recipient, useful for debugging.  
// This overrides any wp_mail filters and displays a warning message in the body of the email.
define('WP_SES_RECIPIENT_EMAIL', 'myaddress@domain.com');
`

== Screenshots ==

1. None

== Changelog ==

Updates after 0.3.58 are tracked in the repository.

= 0.3.58 =
* Tries to always auto-activate in answer to https://wordpress.org/support/topic/the-plugin-get-inactive-after-a-few-minutes
* small fixes

= 0.3.56 =
* fixed sender name format
* fixed regexp for some header recognition
* now supports comma separated emails in to: header

= 0.3.54 =
* bad ses lib include fixed
* Added "force plugin activation" for some use case with IAM credentials

= 0.3.52 =
* Warning if Curl not installed
* Attachments support for use with Contact Form (finally !)
* Notice fixed

= 0.3.50 =
* Notice fixed, setup documentation slightly tweaked

= 0.3.48 =
* Experimental "WP Better Email" Plugin compatibility

= 0.3.46 =
* Maintenance release - fixes some notices and old code.

= 0.3.45 =
* Maintenance release - fixes some notices.

= 0.3.44 =
* Added Amazon SES Endpoint selection. EU users can now select EU region.

= 0.3.42 =
* Added Spanish translation, thanks to Andrew of webhostinghub.com

= 0.3.4 =
* Auto activation via WP_SES_AUTOACTIVATE define, see FAQ.

= 0.3.2 =
* Tweaked header parsing thanks to bhansson

= 0.3.1 =
* Added Reply-To
* Added global WPMU setup (To be fully tested)

= 0.2.9 =
* Updated SES access class
* WP 3.5.1 compatibility
* Stats sorting
* Allow Removal of verified e-mail address
* Added wp_mail filter
* "Forgotten password" link is now ok.
* Various bugfixes

= 0.2.2 =
Reference Language is now English.  
WP SES est fourni avec les textes en Francais.

= 0.2.1 =
Added some functions

* SES Quota display
* SES Statistics
* Can set email return_path
* Full email test form
* Can partially de-activate plugin for intensive testing.

= 0.1.2 =
First public Beta release

* Functionnal version
* Internationnal Version
* fr_FR and en_US locales

= 0.1 =
* Proof of concept

== Upgrade Notice ==

= 0.2.9 =
Pre-release, mainly bugfixes, before another update.

= 0.2.2 =
All default strings are now in english.

= 0.2.1 =
Quota and statistics Integration

= 0.1.2 =
First public Beta release
