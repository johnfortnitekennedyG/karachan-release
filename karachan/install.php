<?php
if (is_dir('ot/')) { die('KC is already installed!'); }
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set up some basic directories that karachan uses.
if (!is_dir('stnk/')) { mkdir('stnk/', 0755, true); }
if (!is_dir('krth/')) { mkdir('krth/', 0755, true); }
if (!is_dir('tmp/')) { mkdir('tmp/', 0755, true); }
if (!is_dir('tmp/cache/')) { mkdir('tmp/cache/', 0755, true); }
if (!is_dir('tmp/tesseract/')) { mkdir('tmp/tesseract/', 0755, true); }
if (!is_dir('tmp/twig_cache/')) { mkdir('tmp/twig_cache/', 0755, true); }

// Installation/upgrade file
define('VERSION', '5.2.1');
require_once 'inc/bootstrap.php';
loadConfig();
$page = array(
	'config' => $config,
	'title' => 'Install',
	'body' => '',
	'nojavascript' => true
);

// this breaks the display of licenses if enabled
$config['minify_html'] = false;
session_start();

if(!isset($_GET['done'])) {
	// The HTTPS check doesn't work properly when in those arrays, so let's run it here and pass along the result during the actual check.
	$httpsvalue = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
	$page['title'] = 'Pre-installation test';
	if(!defined('PHP_VERSION_ID')) {
		$version = explode('.', PHP_VERSION);
		define('PHP_VERSION_ID', ($version[0] * 10000 + $version[1] * 100 + $version[2]));
	}
	$tests = array(
		array(
			'category' => 'PHP',
			'name' => 'PHP &ge; 8.0',
			'result' => PHP_VERSION_ID >= 80000,
			'required' => true,
			'message' => 'karachan requires PHP 8.0 or better.',
		),
		array(
			'category' => 'PHP',
			'name' => '64-bit PHP',
			'result' => PHP_INT_SIZE === 8,
			'required' => true,
			'message' => 'karachan requires 64-bit PHP.',
		),
		array(
			'category' => 'PHP',
			'name' => 'mbstring extension installed',
			'result' => extension_loaded('mbstring'),
			'required' => true,
			'message' => 'You must install the PHP <a href="http://www.php.net/manual/en/mbstring.installation.php">mbstring</a> extension.',
		),
		array(
			'category' => 'Database',
			'name' => 'PDO extension installed',
			'result' => extension_loaded('pdo'),
			'required' => true,
			'message' => 'You must install the PHP <a href="http://www.php.net/manual/en/intro.pdo.php">PDO</a> extension.',
		),
		array(
			'category' => 'Database',
			'name' => 'MySQL PDO driver installed',
			'result' => extension_loaded('pdo') && in_array('mysql', PDO::getAvailableDrivers()),
			'required' => true,
			'message' => 'The required <a href="http://www.php.net/manual/en/ref.pdo-mysql.php">PDO MySQL driver</a> is not installed.',
		),
		array(
			'category' => 'Image processing',
			'name' => 'Imagick (PHP extension)',
			'result' => class_exists('Imagick'),
			'required' => true, // or false if optional
			'message' => 'Imagick PHP extension is not installed or enabled.'
		),
		array(
			'category' => 'File permissions',
			'name' => getcwd(),
			'result' => is_writable('.'),
			'required' => true,
			'message' => 'karachan does not have permission to create directories (boards) here. You will need to <code>chmod</code> (or operating system equivalent) appropriately.'
		),
		array(
			'category' => 'File permissions',
			'name' => getcwd() . '/tmp/twig_cache',
			'result' => is_dir('tmp/twig_cache/') && is_writable('tmp/twig_cache/'),
			'required' => true,
			'message' => 'You must give karachan permission to create (and write to) the <code>tmp/twig_cache</code> directory or performance will be drastically reduced.'
		),
		array(
			'category' => 'File permissions',
			'name' => getcwd() . '/tmp/cache',
			'result' => is_dir('tmp/cache/') && is_writable('tmp/cache/'),
			'required' => true,
			'message' => 'You must give karachan permission to write to the <code>tmp/cache</code> directory.'
		),
		array(
			'category' => 'File permissions',
			'name' => getcwd() . '/stnk,' . getcwd() . '/krth',
			'result' => is_dir('stnk/') && is_writable('stnk/') && is_dir('krth/') && is_writable('krth/'),
			'required' => true,
			'message' => 'You must give karachan permission to write to the <code>stnk</code> and <code>krth</code> directory. This is used for site files.'
		),
		array(
			'category' => 'Misc',
			'name' => 'HTTPS being used',
			'result' => $httpsvalue,
			'required' => false,
			'message' => 'You are not currently using https for karachan, or at least for your backend server. If this intentional, add "$config[\'cookies\'][\'secure_login_only\'] = 0;" (or 1 if using a proxy) on a new line under "Additional configuration" on the next page.'
		),
		array(
			'category' => 'Misc',
			'name' => 'Caching available (APCu, Memcached or Redis)',
			'result' => extension_loaded('apcu') || extension_loaded('memcached') || extension_loaded('redis'),
			'required' => false,
			'message' => 'You will not be able to enable the additional caching system, designed to minimize SQL queries and significantly improve performance. <a href="https://www.php.net/manual/en/book.apcu.php">APCu</a> is the recommended method of caching, but <a href="http://www.php.net/manual/en/intro.memcached.php">Memcached</a> and <a href="http://pecl.php.net/package/redis">Redis</a> are also supported.'
		)
	);
	die(Element('page.html', array(
		'body' => Element('installer/check-requirements.html', array(
			'tests' => $tests,
			'config' => $config,
		)),
		'title' => 'Checking environment',
		'config' => $config,
	)));
}

//Installation complete, build everything.
$sql = @file_get_contents('install.sql') or error("Couldn't load install.sql.");
sql_open();

// This code is probably horrible, but what I'm trying to do is find all of the SQL queires and put them in an array.
preg_match_all("/(^|\n)((SET|CREATE|INSERT).+)\n\n/msU", $sql, $queries);
$queries = $queries[2];

$sql_errors = '';
$sql_err_count = 0;
foreach ($queries as $sq_query) {
	$sq_query = preg_replace('/^([\w\s]*)`([0-9a-zA-Z$_\x{0080}-\x{FFFF}]+)`/u', '$1``$2``', $sq_query);
	if (!query($sq_query)) {
		$sql_err_count++;
		$error = db_error();
		$sql_errors .= "<li>$sql_err_count<ul><li>$sq_query</li><li>$error</li></ul></li>";
	}
}

// perform the installation
$page['title'] = 'Installation complete';
$page['body'] = '<div class="ban"><h2>Generation logs</h2>Should be ready to use.<br><ul>';

// add basic pages/moderator
$page['body'] .= add_new_user_quick('Kara', '08uCGvS1bF1tE45v6bPKNH', 0x7FFFFFFFFFFFFFFF);
$page['body'] .= add_new_board_quick('ot', 'Off Topic / General', '[18+/NSFW] off topic board');

// now we build the logs
$logs = karachan_rebuild_pages(true, true, true, true, false);
foreach($logs as $log) {
	$page['body'] .= "<li>" . $log . "</li>";
}
$page['body'] .= "</ul></div>";

// Admin panel notice
$page['body'] .= '<div class="ban"><h2>Next Steps</h2>' .
					'<p>You can now log in to the admin panel using the default credentials.</p>' .
					'<p><strong>Important:</strong> For security, please change the administrator password immediately after logging in.</p>' .
					'<p style="text-align:center"><a href="/account.php"><button>Go to Admin Panel</button></a></p></div>';


if (!empty($sql_errors)) {
	$page['body'] .= '<div class="ban"><h2>SQL errors</h2><p>SQL errors were encountered when trying to install the database. This may be the result of using a database which is already occupied; if so, you can probably ignore this.</p><p>The errors encountered were:</p><ul>' . $sql_errors . '</ul>' .
						'<p style="text-align:center;color:#d00"><strong>Warning:</strong> Ignoring errors is not recommended and may cause installation issues.</p>' .
						'<p style="text-align:center"><a href="?step=5"><button>Next</button></a></p></div>';
}
echo Element('page.html', $page);