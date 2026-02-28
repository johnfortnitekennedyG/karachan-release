<?php

/*
*  WARNING: This is a project-wide configuration file and is overwritten when upgrading to a newer
*  version of karachan. Please leave this file unchanged, or it will be a lot harder for you to upgrade.
*
*  You can also create per-board configuration files. Once a board is created, locate its directory and
*  create a new file named config.php (eg. b/config.php).
*/

defined('MEPHBOARD') or exit;

/*
* =======================
*  General/misc settings
* =======================
*/

// Global announcement -- the very simple version.
// This used to be wrongly named $config['blotter'] (still exists as an alias).
// $config['global_message'] = 'This is an important announcement!';
$config['blotter'] = &$config['global_message'];

// Shows some extra information at the bottom of pages. Good for development/debugging.
$config['debug'] = false;
// For development purposes. Displays (and "dies" on) all errors and warnings. Turn on with the above.
$config['verbose_errors'] = false;
// Warn about deprecations? See karachan-devel/karachan#363 and https://www.youtube.com/watch?v=9crnlHLVdno
$config['deprecation_errors'] = false;
// Skip cache in twig. this is already enabled with debug
$config['twig_auto_reload'] = false;

// EXPLAIN all SQL queries (when in debug mode).
$config['debug_explain'] = false;

// Directory where temporary files will be created.
$config['tmp'] = sys_get_temp_dir();

// The HTTP status code to use when redirecting. http://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html
// Can be either 303 "See Other" or 302 "Found". (303 is more correct but both should work.)
// There is really no reason for you to ever need to change this.
$config['redirect_http'] = 303;

// Log System
$config['log_system'] = [
	/*
	* Log all error messages and unauthorized login attempts.
	* Can be "error_log" (default), "file", or "stderr".
	*/
	'type' => 'error_log',
	// The application name used by the logging system. Defaults to "mephboard" for backwards compatibility.
	'name' => 'mephboard',
	/*
	* Only relevant if "log_system" is set to `file`. Sets the file that karachan will log to. Defaults to
	* '/var/log/karachan.log'.
	*/
	'file_path' => '/var/log/karachan.log',
];

// Use `host` via shell_exec() to lookup hostnames, avoiding query timeouts. May not work on your system.
// Requires safe_mode to be disabled.
$config['dns_system'] = false;

// Check validity of the reverse DNS of IP addresses. Highly recommended.
$config['fcrdns'] = true;

// When executing most command-line tools, add this to the environment path (seperated by :).
$config['shell_path'] = '/usr/local/bin';

// Automatically execute some maintenance tasks when some pages are opened, which may result in higher latencies.
$config['auto_maintenance'] = true;

// [Pearl]
$config['discord_webhook_moderation'] = '';
$config['discord_webhook_posts'] = '';

// The scheme and domain. This is used to get the site's absolute URL (eg. for image identification links).
$config['domain'] = '';

/*
* ====================
*  Database settings
* ====================
*/

// Database driver (http://www.php.net/manual/en/pdo.drivers.php)
// Only MySQL is supported by karachan at the moment, sorry.
$config['db']['type'] = 'mysql';
$config['db']['server'] = 'db';
$config['db']['user'] = 'elias';
$config['db']['password'] = 'stinkybutt';
$config['db']['database'] = 'karachan';
$config['db']['prefix'] = ''; // leave empty unless you want table prefixes
$config['db']['persistent'] = false;
$config['db']['dsn'] = ''; // not needed unless custom port or options
$config['db']['timeout'] = 30;

/*
* ====================
*  Cache, lock and queue settings
* ====================
*/

/*
* On top of the static file caching system, you can enable the additional caching system which is
* designed to minimize request processing can significantly increase speed when posting or using
* the moderator interface.
*/

// Uses a PHP array. MUST NOT be used in multiprocess environments.
$config['cache']['enabled'] = 'php';
// The recommended in-memory method of caching. Requires the extension. Due to how APCu works, this should be disabled when you run tools from the cli.
// $config['cache']['enabled'] = 'apcu';
// The Memcache server. Requires the memcached extension, with a final D.
// $config['cache']['enabled'] = 'memcached';
// The Redis server. Requires the extension.
// $config['cache']['enabled'] = 'redis';
// Use the local cache folder. Slower than native but available out of the box and compatible with multiprocess
// environments. You can mount a ram-based filesystem in the cache directory to improve performance.
// $config['cache']['enabled'] = 'fs';
// Technically available, offers a no-op fake cache. Don't use this outside of testing or debugging.
// $config['cache']['enabled'] = 'none';

// Timeout for cached objects such as posts and HTML.
$config['cache']['redis'] = array(
	'host' => 'localhost',
	'port' => 6379,
	'password' => '',
	'database' => 1
);

// Cache timeout for cached objects
$config['cache']['timeout'] = 60 * 60 * 48; // 48 hours

// Optional prefix for multiple karachan instances
$config['cache']['prefix'] = '';

// Memcached servers (not used)
$config['cache']['memcached'] = [
	['localhost', 11211]
];

// EXPERIMENTAL: Should we cache configs? Warning: this changes board behaviour, i'd say, a lot.
// If you have any lambdas/includes present in your config, you should move them to instance-functions.php
// (this file will be explicitly loaded during cache hit, but not during cache miss).
$config['cache_config'] = false;

/*
* ====================
*  Cookie settings
* ====================
*/

// Used for communicating with Javascript; telling it when posts were successful.
$config['cookies']['js'] = 'serv';

// Cookies path. Should be '/' or '/board/', depending on your installation.
$config['cookies']['path'] = '/';

// Where to set the 'path' parameter to $config['cookies']['path'] when creating cookies. Recommended.
$config['cookies']['jail'] = true;

// How long should the cookies last (in seconds). Defines how long should moderators should remain logged
// in (0 = browser session).
$config['cookies']['expire'] = 60 * 60 * 24 * 7; // 1 week.

// Make this something long and random for security.
$config['cookies']['salt'] = 'karachan1BCA32FF##usercookie!!';

// Whether or not you can access the mod cookie in JavaScript. Most users should not need to change this.
$config['cookies']['httponly'] = true;

// Do not allow logins via unsecure connections.
// 0 = off. Allow logins on unencrypted HTTP connections. Should only be used in testing environments.
// 1 = on, trust HTTP headers. Allow logins on (at least reportedly partial) HTTPS connections. Use this only if you
// use a proxy, CDN or load balancer via an unencrypted connection. Be sure to filter 'HTTP_X_FORWARDED_PROTO' in
// the remote server, since an attacker could inject the header from the client.
// 2 = on, do not trust HTTP headers. Secure default, allow logins only on HTTPS connections.
$config['cookies']['secure_login_only'] = 0;

// Used to salt secure tripcodes ("##trip") and poster IDs (if enabled).
$config['secure_trip_salt'] = 'karachan##tripcode@#!';

/*
* ====================
*  Flood/spam settings
* ====================
*/

/*
* To further prevent spam and abuse, you can use DNS blacklists (DNSBL). A DNSBL is a list of IP
* addresses published through the Internet Domain Name Service (DNS) either as a zone file that can be
* used by DNS server software, or as a live DNS zone that can be queried in real-time.
*
* Read more: https://github.com/karachan-devel/karachan/wiki/dnsbl
*/

// Prevents most Tor exit nodes from making posts. Recommended, as a lot of abuse comes from Tor because
// of the strong anonymity associated with it.
// Example: $config['dnsbl'][] = 'another.blacklist.net';
// $config['dnsbl'][] = array('tor.dnsbl.sectoor.de', 1); //sectoor.de site is dead. the number stands for (an) ip adress(es) I guess.

// Replacement for sectoor.de
$config['dnsbl'][] = array('rbl.efnetrbl.org', 4);

// http://www.sorbs.net/using.shtml
// $config['dnsbl'][] = array('dnsbl.sorbs.net', array(2, 3, 4, 5, 6, 7, 8, 9));

// http://www.projecthoneypot.org/httpbl.php
// $config['dnsbl'][] = array('<your access key>.%.dnsbl.httpbl.org', function($ip) {
//	$octets = explode('.', $ip);
//
//	// days since last activity
//	if ($octets[1] > 14)
//		return false;
//
//	// "threat score" (http://www.projecthoneypot.org/threat_info.php)
//	if ($octets[2] < 5)
//		return false;
//
//	return true;
// }, 'dnsbl.httpbl.org'); // hide our access key

// Skip checking certain IP addresses against blacklists (for troubleshooting or whatever)
$config['dnsbl_exceptions'][] = '127.0.0.1';

// To prevent bump attacks; returns the thread to last position after the last post is deleted.
$config['anti_bump_flood'] = false;

// Enable simple anti-spam measure. Requires the end-user to answer a question before making a post.
// Works very well against uncustomized spam. Answers are case-insensitive.
$config['security_question'] = array (
	'question' => '(15 XOR 7) - 2?',
	'answer' => '6'
);

// Ability to lock a board for normal users and still allow mods to post.  Could also be useful for making an archive board
$config['board_locked'] = false;

/*
* Custom filters detect certain posts and reject/ban accordingly. They are made up of a condition and an
* action (for when ALL conditions are met). As every single post has to be put through each filter,
* having hundreds probably isn't ideal as it could slow things down.
*
* By default, the custom filters array is populated with basic flood prevention conditions. This
* includes forcing users to wait at least 5 seconds between posts. To disable (or amend) these flood
* prevention settings, you will need to empty the $config['filters'] array first. You can do so by
* adding "$config['filters'] = array();" to inc/instance-config.php. Basic flood prevention used to be
* controlled solely by config variables such as $config['flood_time'] and $config['flood_time_ip'], and
* it still is, as long as you leave the relevant $config['filters'] intact. These old config variables
* still exist for backwards-compatability and general convenience.
*
* Read more: https://github.com/karachan-devel/karachan/wiki/flood_filters
*/

// Minimum time between between each post by the same IP address.
$config['flood_time'] = 10;
// Minimum time between between each post with the exact same content AND same IP address.
$config['flood_time_ip'] = 240;
// Same as above but by a different IP address. (Same content, not necessarily same IP address.)
$config['flood_time_same'] = 60;

// Minimum time between posts by the same IP address (all boards).
$config['filters'][] = array(
	'condition' => array(
		'flood-match' => array('ip'), // Only match IP address
		'flood-time' => &$config['flood_time']
	),
	'action' => 'reject',
	'message' => &$config['error']['flood']
);

// Minimum time between posts by the same IP address with the same text.
$config['filters'][] = array(
	'condition' => array(
		'flood-match' => array('ip', 'body'), // Match IP address and post body
		'flood-time' => &$config['flood_time_ip'],
		'!body' => '/^$/', // Post body is NOT empty
	),
	'action' => 'reject',
	'message' => &$config['error']['flood']
);

// Minimum time between posts with the same text. (Same content, but not always the same IP address.)
$config['filters'][] = array(
	'condition' => array(
		'flood-match' => array('body'), // Match only post body
		'flood-time' => &$config['flood_time_same']
	),
	'action' => 'reject',
	'message' => &$config['error']['flood']
);

//max threads per hour
$config['filters'][] = array(
	'condition' => array(
		'custom' => 'check_thread_limit'
	),
	'action' => 'reject',
	'message' => &$config['error']['too_many_threads']
);

// Example: Use the "flood-count" condition to only match if the user has made at least two posts with
// the same content and IP address in the past 2 minutes.
// $config['filters'][] = array(
// 	'condition' => array(
// 		'flood-match' => array('ip', 'body'), // Match IP address and post body
// 		'flood-time' => 60 * 2, // 2 minutes
// 		'flood-count' => 2 // At least two recent posts
// 	),
// 	'!body' => '/^$/',
// 	'action' => 'reject',
// 	'message' => &$config['error']['flood']
// );

// Example: Blocking an imaginary known spammer, who keeps posting a reply with the name "surgeon",
// ending his posts with "regards, the surgeon" or similar.
// $config['filters'][] = array(
// 	'condition' => array(
// 		'name' => '/^surgeon$/',
// 		'body' => '/regards,\s+(the )?surgeon$/i',
// 		'OP' => false
// 	),
// 	'action' => 'reject',
// 	'message' => 'Go away, spammer.'
// );

// Example: Same as above, but issuing a 3-hour ban instead of just reject the post and
// add an IP note with the message body
$config['filters'][] = array(
	'condition' => array(
		'name' => '/^(skibidi|daisy|klaask)$/i',
		'body' => '/\b(skibidi|daisy|klaask)\b/i',
		'OP' => false
	),
	'action' => 'ban',
	'add_note' => true,
	'expires' => 60 * 60 * 72, // 3 hours
	'reason' => 'Undesirable user.'
);

// Example: PHP 5.3+ (anonymous functions)
// There is also a "custom" condition, making the possibilities of this feature pretty much endless.
// This is a bad example, because there is already a "name" condition built-in.
// $config['filters'][] = array(
// 	'condition' => array(
// 		'body' => '/h$/i',
// 		'OP' => false,
// 		'custom' => function($post) {
// 			if($post['name'] == 'Anonymous')
// 				return true;
// 			else
// 				return false;
// 		}
// 	),
// 	'action' => 'reject'
// );


// Filter flood prevention conditions ("flood-match") depend on a table which contains a cache of recent
// posts across all boards. This table is automatically purged of older posts, determining the maximum
// "age" by looking at each filter. However, when determining the maximum age, karachan does not look
// outside the current board. This means that if you have a special flood condition for a specific board
// (contained in a board configuration file) which has a flood-time greater than any of those in the
// global configuration, you need to set the following variable to the maximum flood-time condition value.
// Set to -1 to disable.
// $config['flood_cache'] = 60 * 60 * 24; // 24 hours
$config['flood_cache'] = -1;

/*
* ====================
*  Post settings
* ====================
*/

// Do you need a body for your reply posts?
$config['force_body'] = false;
// Do you need a body for new threads?
$config['force_body_op'] = true;
// Require an image for threads?
$config['force_image_op'] = true;

/// Strip combining characters from Unicode strings (eg. "Zalgo").  This will impact some non-English languages.
$config['strip_combining_chars'] = true;
// Maximum number of combining characters in a row allowed in Unicode strings so that they can still be used in moderation.
// Requires $config['strip_combining_chars'] = true;
$config['max_combining_chars'] = 3;

// Maximum numbers of threads that can be created every hour on a board.
$config['max_threads_per_hour'] = 4;
// Maximum post body length.
$config['max_body'] = 1800;
// Maximum number of lines allowed in a post.
$config['maximum_lines'] = 100;
// Maximum number of post body lines to show on the index page.
$config['body_truncate'] = 15;
// Maximum number of characters to show on the index page.
$config['body_truncate_char'] = 2500;

// Typically spambots try to post many links. Refuse a post with X links?
$config['max_links'] = 5;
// Maximum number of cites per post (prevents abuse, as more citations mean more database queries).
$config['max_cites'] = 10;
// Maximum number of cross-board links/citations per post.
$config['max_cross'] = $config['max_cites'];

// Track post citations (>>XX). Rebuilds posts after a cited post is deleted, removing broken links.
// Puts a little more load on the database.
$config['track_cites'] = true;

// Maximum filename length (will be truncated).
$config['max_filename_len'] = 255;
// Maximum filename length to display (the rest can be viewed upon mouseover).
$config['max_filename_display'] = 30;

// Allow users to delete their own posts?
$config['allow_delete'] = false;
// How long after posting should you have to wait before being able to delete that post? (In seconds.)
$config['delete_time'] = 10;
// How long should a user be able to delete their post for? (In seconds. Set to 0 to disable.)
$config['max_delete_time'] = 0;
// Reply limit (stops bumping thread when this is reached).
$config['reply_limit'] = 250;

// Image hard limit (stops allowing new image replies when this is reached if not zero).
$config['image_hard_limit'] = 0;
// Reply hard limit (stops allowing new replies when this is reached if not zero).
$config['reply_hard_limit'] = 0;

// Automatically convert things like "..." to Unicode characters ("…").
$config['auto_unicode'] = true;
// Whether to turn URLs into functional links.
$config['markup_urls'] = false;

// Optional URL prefix for links (eg. "http://anonym.to/?").
$config['link_prefix'] = '';
$config['url_ads'] = &$config['link_prefix'];	 // leave alias

//Disable tripcodes. This will make it so all new posts will act as if no tripcode exists.
$config['disable_tripcodes'] = false;

// With the following, you can disable certain superfluous fields or enable "forced anonymous".
// When true, all names will be randomized.
$config['field_disable_name'] = false;
// When true, there will be no pamped field.
$config['field_disable_pamped'] = false;
// When true, there will be no subject field.
$config['field_disable_subject'] = false;
// When true, there will be no subject field for replies.
$config['field_disable_reply_subject'] = false;
// When true, a blank password will be used for files (not usable for deletion).
$config['field_disable_password'] = false;
// Attach country flags to posts.
$config['country_flags'] = false;

/*
* ====================
*  Ban settings
* ====================
*/

// Require users to see the ban page at least once for a ban even if it has since expired.
$config['require_ban_view'] = true;

// Show the post the user was banned for on the "You are banned" page.
$config['ban_show_post'] = false;

// Optional HTML to append to "You are banned" pages.
$config['ban_page_extra'] = '';

// Pre-configured ban reasons that pre-fill the ban form when clicked.
$config['premade_ban_reasons'] = array(
	array(
		'reason' => 'Low-quality posting',
		'length' => '1d'
	),
	array(
		'reason' => 'Off-topic',
		'length' => '1d'
	),
	array(
		'reason' => 'Ban evasion',
		'length' => '7d'
	),
	array(
		'reason' => 'Undesirable poster',
		'length' => ''
	),
	array(
		'reason' => 'Illegal content',
		'length' => ''
	)
);

// How often (minimum) to purge the ban list of expired bans (which have been seen).
$config['purge_bans'] = 60 * 60 * 12; // 12 hours

// Allow users to appeal bans through karachan.
$config['ban_appeals'] = false;

// Do not allow users to appeal bans that are shorter than this length (in seconds).
$config['ban_appeals_min_length'] = 60 * 60 * 6; // 6 hours

// How many ban appeals can be made for a single ban?
$config['ban_appeals_max'] = 1;

// Maximum character length of appeal.
$config['ban_appeal_max_chars'] = 250;

/*
* ====================
*  Markup settings
* ====================
*/

$config['markup'] = [
	[ "/'''(.+?)'''/", "<strong>\$1</strong>" ],
	[ "/''(.+?)''/", "<em>\$1</em>" ],
	[ "/\*\*(.+?)\*\*/", "<span class=\"spoiler\">\$1</span>" ],
	[ "/^[ |\t]*==(.+?)==[ |\t]*$/m", "<span class=\"heading\">\$1</span>" ],
];

// Code markup. This should be set to a regular expression, using tags you want to use. Examples:
// "/\[code\](.*?)\[\/code\]/is"
// "/```([a-z0-9-]{0,20})\n(.*?)\n?```\n?/s"
$config['markup_code'] = '/\[code(?:=([a-z0-9#+\-]+))?\](.*?)\[\/code\]/is';

// Repair markup with HTML Tidy. This may be slower, but it solves nesting mistakes. karachan, at the
// time of writing this, can not prevent out-of-order markup tags (eg. "**''test**'') without help from
// HTML Tidy.
$config['markup_repair_tidy'] = false;

// Use 'bare' config option of tidy::repairString.
// This option replaces some punctuation marks with their ASCII counterparts.
// Dashes are replaced with (single) hyphens, for example.
$config['markup_repair_tidy_bare'] = true;

// Always regenerate markup. This isn't recommended and should only be used for debugging; by default,
// karachan only parses post markup when it needs to, and keeps post-markup HTML in the database. This
// will significantly impact performance when enabled.
$config['always_regenerate_markup'] = false;

/*
* ====================
*  Image settings
* ====================
*/
// Maximum number of images allowed. Increasing this number enabled multi image.
// If you make it more than 1, make sure to enable the below script for the post form to change.
$config['max_images'] = 1;

// For resizing, maximum thumbnail dimensions.
$config['thumb_width'] = 255;
$config['thumb_height'] = 255;

// Allowed image file extensions.
$config['allowed_ext'] = [
	'jpg',
	'jpeg',
	'webp',
	'gif',
	'png'
];

// Allowed additional file extensions (not images; downloadable files).
$config['allowed_ext_files'] = [
	'mp4',
	'webm',
	'ogg',
	'mp3',
	'wad',
	'spc',
	'mwl'
];

// Thumbnail to use for the non-image file uploads.
$config['file_icons'] = [
	'default' => 'assets/file.png',
	'wad' => 'assets/dewm.png',
	'spc' => 'assets/sesm.png',
	'zip' => 'assets/zip.png',
	'mwl' => 'assets/mwl.png'
];

// Location of thumbnail to use for spoiler images.
$config['spoiler_image'] = 'assets/spoiler.png';
// Location of thumbnail to use for deleted images.
$config['image_deleted'] = 'assets/deleted.png';
// Location of thumbnail to use for pending images.
$config['image_pending'] = 'assets/pending.png';
// Location of thumbnail to use for any file.
$config['image_banana'] = 'assets/banana.png';

// Maximum image upload size in bytes.
$config['max_filesize'] = 10 * 1024 * 1024; // 10MB
// Maximum image dimensions.
$config['max_image_size'] = 2048;

// Use Tesseract OCR to retrieve text from images, so you can use it as a spamfilter.
$config['tesseract_ocr'] = false;

// Number of posts in a "View Last X Posts" page
$config['noko50_count'] = 50;
// Number of posts a thread needs before it gets a "View Last X Posts" page.
// Set to an arbitrarily large value to disable.
$config['noko50_min'] = 100;
/*
* ====================
*  Board settings
* ====================
*/

// Maximum amount of threads to display per page.
$config['threads_per_page'] = 15;
// Maximum number of pages. Content past the last page is automatically purged.
$config['max_pages'] = 5;
// Replies to show per thread on the board index page.
$config['threads_preview'] = 5;
// Same as above, but for stickied threads.
$config['threads_preview_sticky'] = 1;

// How to display the URI of boards. Usually '/%s/' (/b/, /mu/, etc). This doesn't change the URL. Find
//  $config['board_path'] if you wish to change the URL.
$config['board_abbreviation'] = '/%s/';

// Number of reports you can create at once.
$config['report_limit'] = 3;

// Approval level of bullshit. Image approval is left on my default. Use this if you want to approve post bodies too.
$config['must_approve_post_body'] = true;

// Maximum number of characters per report.
$config['report_max_length'] = 30;

/*
* ====================
*  Display settings
* ====================
*/

// Timezone to use for displaying dates/times.
$config['timezone'] = 'America/Los_Angeles';
// The format string passed to DateTime::format() for displaying dates. ISO 8601-like by default.
// https://www.php.net/manual/en/datetime.format.php
$config['post_date'] = 'm/d/y (D) H:i:s';
// Same as above, but used for "you are banned' pages.
$config['ban_date'] = 'l j F, Y';

// Homepage
$config['homepage_maxnews'] = 10;
$config['homepage_maxposts'] = 40;

// The names on the post buttons. (On most imageboards, these are both just "Post").
$config['button_newtopic'] = 'New Topic';
$config['button_reply'] = 'New Reply';

// Assign each poster in a thread a unique ID, shown by "ID: xxxxx" before the post number.
$config['poster_ids'] = false;
// Number of characters in the poster ID (maximum is 40).
$config['poster_id_length'] = 5;

// Show thread subject in page title.
$config['thread_subject_in_title'] = false;

// Custom stylesheets available for the user to choose. See the "assets/stylesheets/" folder for a list of available stylesheets (or create your own).
$config['stylesheets'] = [
	// Default; there is no additional/custom stylesheet for this.
	'Default' => '',
	'Dark' => 'dark.css'
];

// Private boards
$config['private_boards'] = array('diap', 'nigger', 'smw');

// Automatically remove unnecessary whitespace when compiling HTML files from templates.
$config['minify_html'] = true;

// define your word -> image mapping
$config['emotes'] = [
	'nails' => '<img src="/assets/emotes/nails.png">',
	'nigger' => '<img src="/assets/emotes/hamster.png">',
	'niggers' => '<img src="/assets/emotes/hamster.png">',
	'cmonbro' => '<img src="/assets/emotes/cmonbro.png">',
	'holyshit' => '<img src="/assets/emotes/holyshit.png">',
	'deadtime' => '<img src="/assets/emotes/deadtime.png">',
	'reddit' => '<img src="/assets/emotes/reddit.png">',
	'cmoneyes' => '<img src="/assets/emotes/cmoneyes.png">',
	'purplena' => '<img src="/assets/emotes/purplena.png">'
];

/*
	* Advertisement HTML to appear at the top and bottom of board pages.
	*/

// $config['ad'] = array(
//	'top' => '',
//	'bottom' => '',
// );

/*
* ====================
*  Javascript
* ====================
*/

// Additional Javascript files to include on board index and thread pages. See inc/scripts/ for available scripts.
$config['additional_javascript'] = [
	'inc/scripts/jquery.min.js',
	'inc/scripts/jquery-ui.custom.min.js',
	'inc/scripts/ajax.js',
	'inc/scripts/options.js',
	'inc/scripts/settings.js',
	'inc/scripts/show-own-posts.js',
	'inc/scripts/multi-image.js',
	'inc/scripts/options/general.js',
	'inc/scripts/auto-reload.js',
	'inc/scripts/file-selector.js',
	'inc/scripts/local-time.js',
	'inc/scripts/quick-reply.js',
	'inc/scripts/upload-selection.js',
	'inc/scripts/hide-images.js',
	'inc/scripts/hide-threads.js',
	'inc/scripts/inline-expanding.js',
	'inc/scripts/show-op.js',
	'inc/scripts/catalog.js',
	'inc/scripts/comment-toolbar.js',
	'inc/scripts/expand.js',
	'inc/scripts/highlight.min.js',
	'inc/scripts/pearl.min.js'
];

// Minify assets using https://github.com/matthiasmullie/minify
$config['minify_assets'] = true;

// Version number for main.js (or $config['url_javascript']).
// You can use this to bypass the user's browsers and CDN caches.
$config['resource_version'] = 42;

/*
* ====================
*  Video embedding
* ====================
*/

// Enable embedding (see below).
$config['enable_embedding'] = false;

// Custom embedding (YouTube, vimeo, etc.)
// It's very important that you match the entire input (with ^ and $) or things will not work correctly.
// Be careful when creating a new embed, because depending on the URL you end up exposing yourself to an XSS.
$config['embedding'] = array(
	array(
		'/^https?:\/\/(\w+\.)?(youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9\-_]{10,11})?$/i',
		'<iframe style="float: left; margin: 10px 20px;" width="%%tb_width%%" height="%%tb_height%%" frameborder="0" id="ytplayer" src="https://www.youtube.com/embed/$3"></iframe>'
	)
);

// Embedding width and height.
$config['embed_width'] = 640;
$config['embed_height'] = 360;

/*
* ====================
*  Error messages
* ====================
*/

$config['error'] = [
	// General error messages
	'bot' 					=> 'Invalid data sent!',
	'toolong'				=> 'The %s field was too long.',
	'toolong_body'			=> 'The body was too long.',
	'tooshort_body'			=> 'The body was too short or empty.',
	'toomanylines'			=> 'Your post contains too many lines!',
	'noimage'				=> 'You must upload an image.',
	'toomanyimages' 		=> 'You have attempted to upload too many images!',
	'nomove'				=> 'The server failed to handle your upload.',
	'noboard'				=> 'Invalid board!',
	'nonexistant'			=> 'Thread specified does not exist',
	'nopost'				=> 'Post specified does not exist.',
	'locked'				=> 'Thread locked. You may not reply at this time.',
	'reply_hard_limit'		=> 'Thread has reached its maximum reply limit.',
	'image_hard_limit'		=> 'Thread has reached its maximum image limit.',
	'nopost'				=> 'You didn\'t make a post.',
	'flood'					=> 'Flood detected; Post discarded.',
	'too_many_threads'		=> 'The hourly thread limit has been reached. Please post in an existing thread.',
	'spam'					=> 'Your request looks automated; Post discarded.',
	'security_question'		=> 'You must answer the question to make a new thread. See the last field.',
	'dnsbl'					=> 'Your IP address is listed in %s.',
	'toomanylinks'			=> 'Too many links; flood detected.',
	'toomanycites'			=> 'Too many cites; post discarded.',
	'toomanycross'			=> 'Too many cross-board links; post discarded.',
	'nodelete'				=> 'You didn\'t select anything to delete.',
	'noreport'				=> 'You didn\'t select anything to report.',
	'toolongreport'			=> 'The reason was too long.',
	'toomanyreports'		=> 'You can\'t report that many posts at once.',
	'noban'					=> 'That ban doesn\'t exist or is not for you.',
	'tooshortban'			=> 'You cannot appeal a ban of this length.',
	'toolongappeal'			=> 'The appeal was too long.',
	'toomanyappeals'		=> 'You cannot appeal this ban again.',
	'pendingappeal'			=> 'There is already a pending appeal for this ban.',
	'invalidpassword'		=> 'Wrong password…',
	'invalidimg'			=> 'Invalid image.',
	'phpfileserror'			=> 'Upload failure (file #%index%): PHP Error code %code%.',
	'unknownext'			=> 'Unknown file extension.',
	'filesize'				=> 'Maximum file size: %maxsz% bytes<br>Your file\'s size: %filesz% bytes',
	'maxsize'				=> 'The file was too big.',
	'delete_too_soon'		=> 'You\'ll have to wait another %s before deleting that.',
	'delete_too_late'		=> 'You cannot delete a post this old.',
	'invalid_embed'			=> 'Couldn\'t make sense of the URL of the video you tried to embed.',
	'flag_undefined'		=> 'The flag %s is undefined, your PHP version is too old!',
	'flag_wrongtype'		=> 'defined_flags_accumulate(): The flag %s is of the wrong type!',
	'remote_io_error'		=> 'IO error while interacting with a remote service.',
	'local_io_error'		=> 'IO error while interacting with a local resource or service.',

	// Moderator errors
	'toomanyunban'	=> 'You are only allowed to unban %s users at a time. You tried to unban %u users.',
	'invalid'		=> 'Invalid username and/or password.',
	'insecure'		=> 'Login on insecure connections is disabled.',
	'notamod'		=> 'You are not a mod…',
	'invalidafter'	=> 'Invalid username and/or password. Your user may have been deleted or changed.',
	'malformed'		=> 'Invalid/malformed cookies.',
	'missedafield'	=> 'Your browser didn\'t submit an input when it should have.',
	'required'		=> 'The %s field is required.',
	'invalidfield'	=> 'The %s field was invalid.',
	'boardexists'	=> 'There is already a /%s/ board.',
	'noaccess'		=> 'You don\'t have permission to do that.',
	'invalidpost'	=> 'That post doesn\'t exist…',
	'404'			=> 'Page not found.',
	'modexists'		=> 'That mod <a href="?/users/%d">already exists</a>!',
	'csrf'			=> 'Invalid security token! Please go back and try again.',
	'pendingreg'	=> 'Your account has now been submitted for approval.',
	'nregistration'	=> 'Registrations are disabled.'
];

/*
* =========================
*  Directory/file settings
* =========================
*/

// Location of primary files.
$config['file_index'] = 'index.html';
$config['file_boardpage'] = 'page%d.html';
$config['file_catalog'] = 'catalog.html';
$config['file_account'] = 'account.php';
$config['file_script'] = 'main.js';
$config['file_stylesheet'] = 'style.css';
$config['file_post'] = '%d.html';
$config['file_post50'] = '%d+short.html';

// Template locations
$config['file_board_index'] = 'index.html';
$config['file_static_page'] = 'page.html';
$config['file_error'] = 'error.html';
$config['file_mod_login'] = 'login.html';
$config['file_banned'] = 'banned.html';
$config['file_thread'] = 'thread.html';
$config['file_post_reply'] = 'post_reply.html';
$config['file_post_thread'] = 'post_thread.html';

// Mod page file settings
$config['file_mod_dashboard'] = 'mod/dashboard.html';
$config['file_mod_confim'] = 'mod/confirm.html';
$config['file_mod_board'] = 'mod/board.html';
$config['file_mod_news'] = 'mod/news.html';
$config['file_mod_log'] = 'mod/log.html';

$config['file_mod_pending'] = 'mod/pending.html';

$config['file_mod_view_ip'] = 'mod/view_ip.html';
$config['file_mod_ban_form'] = 'mod/ban_form.html';
$config['file_mod_ban_list'] = 'mod/ban_list.html';
$config['file_mod_ban_appeals'] = 'mod/ban_appeals.html';

$config['file_mod_search_results'] = 'mod/search_results.html';

$config['file_mod_move'] = 'mod/move.html';
$config['file_mod_edit_post_form'] = 'mod/edit_post_form.html';

$config['file_mod_user'] = 'mod/user.html';
$config['file_mod_users'] = 'mod/users.html';

$config['file_mod_inbox'] = 'mod/inbox.html';

$config['file_mod_rebuilt'] = 'mod/rebuilt.html';
$config['file_mod_rebuild'] = 'mod/rebuild.html';
$config['file_mod_report'] = 'mod/report.html';
$config['file_mod_reports'] = 'mod/reports.html';

// Board directory, followed by a forward-slash (/).
$config['board_path'] = '%s/';

// Try not to build pages when we shouldn't have to.
$config['try_smarter'] = true;

/*
* ====================
*  Mod settings
* ====================
*/

// Mod stuff.
$config['mod'] = [
	// Limit how many bans can be removed via the ban list. Set to false (or zero) for no limit.
	'unban_limit' => false,
	// The page that is first shown when a moderator logs in. Defaults to the dashboard (?/).
	'default' => '/',
	// Do DNS lookups on IP addresses to get their hostname for the moderator IP pages (?/IP/x.x.x.x).
	'dns_lookup' => true,
	// How many recent posts, per board, to show in ?/IP/x.x.x.x.
	'ip_recentposts' => 5,
	// Number of posts to display on the reports page.
	'recent_reports' => 10,
	// Number of actions to show per page in the moderation log.
	'modlog_page' => 350,
	// Number of bans to show per page in the ban list.
	'banlist_page'=> 350,
	// Number of news entries to display per page.
	'news_page' => 40,
	// Number of results to display per page.
	'search_page' => 200,

	// Check public ban message by default.
	'check_ban_message' => false,
	// Default public ban message. In public ban messages, %length% is replaced with "for x days" or
	// "permanently" (with %LENGTH% being the uppercase equivalent).
	'default_ban_message' => 'USER WAS BANNED FOR THIS POST',
	// $config['mod']['default_ban_message'] = 'USER WAS BANNED %LENGTH% FOR THIS POST';
	// HTML to append to post bodies for public bans messages (where "%s" is the message).
	'ban_message' => '<span class="public_ban">(%s)</span>',

	// Message snippet length (anything else will be cut off)
	'snippet_length' => 75,

	// Edit raw HTML in posts by default.
	'raw_html_default' => false,

	// Automatically dismiss all reports regarding a thread when it is locked.
	'dismiss_reports_on_lock' => true,

	'link_delete' => '[D]',
	'link_ban' => '[B]',
	'link_bandelete' => '[B&amp;D]',
	'link_deletefile' => '[F]',
	'link_spoilerimage' => '[Spoiler]',
	'link_approvecontent' => '[Approve]',
	'link_deletebyip' => '[D+]',
	'link_deletebyip_global' => '[D++]',
	'link_sticky' => '[Sticky]',
	'link_desticky' => '[-Sticky]',
	'link_lock' => '[Lock]',
	'link_unlock' => '[-Lock]',
	'link_bumplock' => '[Sage]',
	'link_bumpunlock' => '[-Sage]',
	'link_editpost' => '[Edit]',
	'link_move' => '[Move]',
	'link_cycle' => '[Cycle]',
	'link_uncycle' => '[-Cycle]'
];

// Signups
$config['allow_signups'] = true;

// Permissions
$config['permissions'] = [
	'show_ip'             => 1 << 0,  // view ip addresses
	'delete'              => 1 << 1,  // delete a post
	'ban'                 => 1 << 2,  // ban a user for a post
	'bandelete'           => 1 << 3,  // ban and delete (one click)
	'unban'               => 1 << 4,  // remove bans
	'approvecontent'      => 1 << 5,  // approve content
	'spoilerimage'        => 1 << 6,  // spoiler image
	'deletefile'          => 1 << 7,  // delete file (keep post)
	'deletebyip'          => 1 << 8,  // delete all posts by ip
	'deletebyip_global'   => 1 << 9,  // delete all posts by ip globally
	'sticky'              => 1 << 10, // sticky a thread
	'cycle'               => 1 << 11, // cycle a thread
	'lock'                => 1 << 12, // lock a thread
	'postinlocked'        => 1 << 13, // post in locked thread/board
	'bumplock'            => 1 << 14, // prevent bumping
	'view_bumplock'       => 1 << 15, // view if bumplocked
	'editpost'            => 1 << 16, // edit posts
	'move'                => 1 << 17, // move threads
	'bypass_field_disable'=> 1 << 18, // bypass forced anon
	'bypass_filters'      => 1 << 19, // bypass flood check
	'rawhtml'             => 1 << 20, // raw html posting
	'reports'             => 1 << 21, // view report queue
	'report_dismiss'      => 1 << 22, // dismiss a report
	'report_dismiss_ip'   => 1 << 23, // dismiss reports by ip
	'report_dismiss_post' => 1 << 24, // dismiss reports for post
	'view_banlist'        => 1 << 25, // view ban list
	'view_banstaff'       => 1 << 26, // see staff who banned
	'view_notes'          => 1 << 27, // view ip notes
	'remove_notes'        => 1 << 28, // remove notes
	'newboard'            => 1 << 29, // create board
	'manageboards'        => 1 << 30, // manage boards
	'deleteboard'         => 1 << 31, // delete board
	'capcode'             => 1 << 32, // are we allowed to use admin capcodes?
	'view_pearlsecurity'  => 1 << 33, // view pearl security data
	'editusers'           => 1 << 34, // edit/manage users
	'change_password'     => 1 << 35, // change own password
	'deleteusers'         => 1 << 36, // delete users
	'createusers'         => 1 << 37, // create users
	'modlog'              => 1 << 38, // view moderation log
	'show_ip_modlog'      => 1 << 39, // see mod ips in log
	'modlog_ip'           => 1 << 40, // see modlog on ip page
	'rebuild'             => 1 << 41, // rebuild all
	'search'              => 1 << 42, // search
	'search_posts'        => 1 << 43, // search posts
	'tripcode'            => 1 << 44, // are we allowed to use tripcodes? (different from cap)
	'userboards'          => 1 << 45, // making userboards
	'news'                => 1 << 46, // post news
	'bypassrestrictions'  => 1 << 47, // bypass posting restrictions
	'news_delete'         => 1 << 48, // delete news
	'view_ban_appeals'    => 1 << 49, // view ban appeals
	'ban_appeals'         => 1 << 50, // accept/deny appeals
	'accountapproved'     => 1 << 62  // wheter this account is approved or not.
];

// Allow OP to remove arbitrary posts in his thread
$config['user_moderation'] = false;

// Pearl's integrity scripts
$config['pearl_security_store'] = true;
$config['require_pearl_extra_checks'] = true;

/*
* ====================
*  Other/uncategorized
* ====================
*/

// Link imageboard to your Google Analytics account to track users and provide traffic insights.
// $config['google_analytics'] = 'UA-xxxxxxx-yy';
// Keep the Google Analytics cookies to one domain -- ga._setDomainName()
// $config['google_analytics_domain'] = 'www.example.org';

// If you use Varnish, Squid, or any similar caching reverse-proxy in front of karachan, you can
// configure karachan to PURGE files when they're written to.
// $config['purge'] = array(
// 	array('127.0.0.1', 80)
// 	array('127.0.0.1', 80, 'example.org')
// );

// Connection timeout for $config['purge'], in seconds.
$config['purge_timeout'] = 3;

// Create gzipped static files along with ungzipped.
// This is useful with nginx with gzip_static on.
$config['gzip_static'] = false;

// Regex for board URIs. Don't add "`" character or any Unicode that MySQL can't handle. 58 characters
// is the absolute maximum, because MySQL cannot handle table names greater than 64 characters.
$config['board_regex'] = '[0-9a-zA-Z$_\x{0080}-\x{FFFF}]{1,58}';

// Password hashing function
//
// $5$ <- SHA256
// $6$ <- SHA512
//
// 25000 rounds make for ~0.05s on my 2015 Core i3 computer.
//
// https://secure.php.net/manual/en/function.crypt.php
$config['password_crypt'] = '$6$rounds=25000$';

// Password hashing method version
// If set to 0, it won't upgrade hashes using old password encryption schema, only create new.
// You can set it to a higher value, to further migrate to other password hashing function.
$config['password_crypt_version'] = 1;

// Secret passphrase for IP cloaking
// Disabled if empty.
$config['ipcrypt_key'] = 'nigger!';

// IP cloak prefix
$config['ipcrypt_prefix'] = 'USR';
