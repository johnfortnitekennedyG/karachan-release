<?php
$microtime_start = microtime(true);

// the user is not currently logged in
$kara_user = false;

register_shutdown_function('fatal_error_handler');
mb_internal_encoding('UTF-8');
loadConfig();

function loadConfig() {
	global $board, $config, $debug, $microtime_start, $events;
	$error = function_exists('error') ? 'error' : 'basic_error_function_because_the_other_isnt_loaded_yet';
	$boardsuffix = isset($board['uri']) ? $board['uri'] : '';
	if(file_exists('tmp/cache/cache_config.php')) { require_once('tmp/cache/cache_config.php'); }
	if(isset($config['cache_config']) && $config['cache_config'] && $config = Cache::get('config_' . $boardsuffix))  {
		$events = Cache::get('events_' . $boardsuffix );
	} else {
		$config = array();
		$arrays = array(
			'db',
			'cache',
			'lock',
			'queue',
			'cookies',
			'error',
			'dir',
			'mod',
			'spam',
			'filters',
			'dnsbl',
			'dnsbl_exceptions',
			'remote',
			'allowed_ext',
			'allowed_ext_files',
			'file_icons',
			'footer',
			'stylesheets',
			'additional_javascript',
			'markup'
		);

		foreach($arrays as $key) { $config[$key] = array(); }

		// Configuration files
		require 'inc/config.php';
		if(isset($board['dir']) && file_exists($board['dir'] . '/config.php')) { require $board['dir'] . '/config.php'; }
		if(!isset($config['global_message'])) { $config['global_message'] = false; }
	}

	// Effectful config processing below:
	date_default_timezone_set($config['timezone']);
	if($config['verbose_errors']) {
		set_error_handler('verbose_error_handler');
		error_reporting($config['deprecation_errors'] ? E_ALL : E_ALL & ~E_DEPRECATED);
		ini_set('display_errors', true);
		ini_set('html_errors', false);
	} else {
		ini_set('display_errors', false);
	}

	if($config['cache']['enabled']) { require_once 'inc/cache.php'; }

	if($config['cache_config'] && !isset ($config['cache_config_loaded'])) {
		file_put_contents('tmp/cache/cache_config.php', '<?php '.
			'$config = array();'.
			'$config[\'cache\'] = '.var_export($config['cache'], true).';'.
			'$config[\'cache_config\'] = true;'.
			'$config[\'debug\'] = '.var_export($config['debug'], true).';'.
			'require_once(\'inc/cache.php\');'
		);

		$config['cache_config_loaded'] = true;

		Cache::set('config_'.$boardsuffix, $config);
		Cache::set('events_'.$boardsuffix, $events);
	}

	if($config['debug']) {
		if(!isset($debug)) {
			$debug = array(
				'sql' => array(),
				'exec' => array(),
				'purge' => array(),
				'cached' => array(),
				'write' => array(),
				'time' => array(
					'db_queries' => 0,
					'exec' => 0,
				),
				'start' => $microtime_start,
				'start_debug' => microtime(true)
			);
			$debug['start'] = $microtime_start;
		}
	}
}

function basic_error_function_because_the_other_isnt_loaded_yet($message, $priority = true) {
	die('<!DOCTYPE html><html><head><title>Error</title>' .
		'<body><h2>Error</h2>' . $message . '<hr/>' .
		'<p class="c">This alternative error page is being displayed because the other couldn\'t be found or hasn\'t loaded yet.</p></body></html>');
}

function fatal_error_handler() {
	if($error = error_get_last()) {
		if($error['type'] == E_ERROR) {
			if(function_exists('error')) {
				error('Caught fatal error: ' . $error['message'] . ' in <strong>' . $error['file'] . '</strong> on line ' . $error['line'], LOG_ERR);
			} else {
				basic_error_function_because_the_other_isnt_loaded_yet('Caught fatal error: ' . $error['message'] . ' in ' . $error['file'] . ' on line ' . $error['line'], LOG_ERR);
			}
		}
	}
}

function verbose_error_handler($errno, $errstr, $errfile, $errline) {
	global $config;

	if(error_reporting() == 0)
		return false; // Looks like this warning was suppressed by the @ operator.
	if($errno == E_DEPRECATED && !$config['deprecation_errors'])
		return false;

	error(utf8tohtml($errstr), true, array(
		'file' => $errfile . ':' . $errline,
		'errno' => $errno,
		'error' => $errstr,
		'backtrace' => array_slice(debug_backtrace(), 1)
	));
}

function purge($uri) {
	global $config, $debug;

	// Fix for Unicode
	$uri = rawurlencode($uri);

	$noescape = "/!~*()+:";
	$noescape = preg_split('//', $noescape);
	$noescape_url = array_map("rawurlencode", $noescape);
	$uri = str_replace($noescape_url, $noescape, $uri);

	if($config['debug']) {
		$debug['purge'][] = $uri;
	}

	foreach($config['purge'] as &$purge) {
		$host = &$purge[0];
		$port = &$purge[1];
		$http_host = isset($purge[2]) ? $purge[2] : (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost');
		$request = "PURGE {$uri} HTTP/1.1\r\nHost: {$http_host}\r\nUser-Agent: Mephboard\r\nConnection: Close\r\n\r\n";
		if($fp = fsockopen($host, $port, $errno, $errstr, $config['purge_timeout'])) {
			fwrite($fp, $request);
			fclose($fp);
		} else {
			// Cannot connect?
			error('Could not PURGE for ' . $host);
		}
	}
}

function file_write($path, $data, $simple = false, $skip_purge = false) {
	global $config, $debug;

	if(!$fp = fopen($path, $simple ? 'w' : 'c')) { error('Unable to open file for writing: ' . $path); }
	if(!$simple && !flock($fp, LOCK_EX)) { error('Unable to lock file: ' . $path); } // File locking
	if(!$simple && !ftruncate($fp, 0)) { error('Unable to truncate file: ' . $path); } // Truncate file
	if(($bytes = fwrite($fp, $data)) === false) { error('Unable to write to file: ' . $path); } // Write data
	if(!$simple) { flock($fp, LOCK_UN); } // Unlock
	if(!fclose($fp)) { error('Unable to close file: ' . $path); } // Close

	/**
	 * Create gzipped file.
	 *
	 * When writing into a file foo.bar and the size is larger or equal to 1
	 * KiB, this also produces the gzipped version foo.bar.gz
	 *
	 * This is useful with nginx with gzip_static on.
	 */
	if($config['gzip_static']) {
		$gzpath = "$path.gz";

		if($bytes & ~0x3ff) {  // if($bytes >= 1024)
			if(file_put_contents($gzpath, gzencode($data), $simple ? 0 : LOCK_EX) === false)
				error("Unable to write to file: $gzpath");
			//if(!touch($gzpath, filemtime($path), fileatime($path)))
			//	error("Unable to touch file: $gzpath");
		}
		else {
			@unlink($gzpath);
		}
	}

	if(!$skip_purge && isset($config['purge'])) {
		// Purge cache
		if(basename($path) == $config['file_index']) {
			// Index file (/index.html); purge "/" as well
			$uri = dirname($path);
			// root
			if($uri == '.')
				$uri = '';
			else
				$uri .= '/';
			purge($uri);
		}
		purge($path);
	}

	if($config['debug']) {
		$debug['write'][] = $path . ': ' . $bytes . ' bytes';
	}
}

function file_unlink($path) {
	global $config, $debug;

	if($config['debug']) {
		if(!isset($debug['unlink']))
			$debug['unlink'] = array();
		$debug['unlink'][] = $path;
	}

	if(file_exists($path)) {
		$ret = @unlink($path);
	} else {
		$ret = true;
	}

	if($config['gzip_static']) {
		$gzpath = "$path.gz";

		if(file_exists($gzpath)) {
			@unlink($gzpath);
		}
	}

	if(isset($config['purge']) && $path[0] != '/' && isset($_SERVER['HTTP_HOST'])) {
		// Purge cache
		if(basename($path) == $config['file_index']) {
			// Index file (/index.html); purge "/" as well
			$uri = dirname($path);
			// root
			if($uri == '.')
				$uri = '';
			else
				$uri .= '/';
			purge($uri);
		}
		purge($path);
	}
	return $ret;
}

function rrmdir($dir) {
	if(is_dir($dir)) {
		$objects = scandir($dir);
		foreach($objects as $object) {
			if($object != "." && $object != "..") {
				if(filetype($dir."/".$object) == "dir") { rrmdir($dir."/".$object); }
				else { file_unlink($dir."/".$object); }
			}
		}
		reset($objects);
		rmdir($dir);
	}
}

function shell_exec_error($command, $suppress_stdout = false) {
	global $config, $debug;

	if($config['debug'])
		$start = microtime(true);

	$return = trim(shell_exec('PATH="' . escapeshellcmd($config['shell_path']) . ':$PATH";' .
		$command . ' 2>&1 ' . ($suppress_stdout ? '> /dev/null ' : '') . '&& echo "TB_SUCCESS"'));
	$return = preg_replace('/TB_SUCCESS$/', '', $return);

	if($config['debug']) {
		$time = microtime(true) - $start;
		$debug['exec'][] = array(
			'command' => $command,
			'time' => '~' . round($time * 1000, 2) . 'ms',
			'response' => $return ? $return : null
		);
		$debug['time']['exec'] += $time;
	}

	return $return === 'TB_SUCCESS' ? false : $return;
}