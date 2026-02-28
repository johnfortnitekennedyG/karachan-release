<?php
require_once 'inc/bootstrap.php';
if ($config['debug']) { $parse_start_time = microtime(true); }
defined('MEPHBOARD') or exit;
require_once 'inc/modpages.php';

$ctx = Karachan\build_context($config);
check_login($ctx, true);

$query = isset($_SERVER['QUERY_STRING']) ? rawurldecode($_SERVER['QUERY_STRING']) : '';
$pages = [
	'' => ':?/', // redirect to dashboard
	'/' => 'dashboard', // dashboard
	'/confirm/(.+)' => 'confirm', // confirm action (if javascript didn't work)
	'/logout' => 'secure logout', // logout

	'/users' => 'users', // manage users
	'/users/(\d+)' => 'secure_POST user', // edit user
	'/users/new' => 'secure_POST user_new', // create a new user

	'/log' => 'log', // modlog
	'/log/(\d+)' => 'log', // modlog
	'/log:([^/:]+)' => 'user_log', // modlog
	'/log:([^/:]+)/(\d+)' => 'user_log', // modlog
	'/log:b:([^/]+)' => 'board_log', // modlog
	'/log:b:([^/]+)/(\d+)' => 'board_log', // modlog

	'/pending' => 'pending', // pending media

	'/usernewsboard' => 'secure_POST news', // view news
	'/usernewsboard/(\d+)' => 'secure_POST news', // view news
	'/usernewsboard/delete/(\d+)' => 'secure news_delete', // delete from news

	'/edit/(\%b)' => 'secure_POST edit_board', // edit board details
	'/new-board' => 'secure_POST edit_board', // create a new board

	'/rebuild' => 'secure_POST rebuild', // rebuild static files
	'/reports' => 'reports', // report queue
	'/reports/(\d+)/dismiss(&all|&post)?' => 'secure report_dismiss', // dismiss a report

	'/IP/([\w.:]+)' => 'secure_POST ip', // view ip address
	'/IP/([\w.:]+)/remove_note/(\d+)' => 'secure ip_remove_note', // remove note from ip address

	'/ban' => 'secure_POST ban', // new ban
	'/bans' => 'secure_POST bans', // ban list
	'/bans/(\d+)' => 'secure_POST bans', // ban list
	'/unban/(\d+)' => 'secure_POST unban', // unban
	'/edit_ban/(\d+)' => 'secure_POST edit_ban',
	'/ban-appeals' => 'secure_POST ban_appeals', // view ban appeals

	'/search' => 'search_redirect', // search
	'/search/(posts|security|IP_notes|bans|log)/(.+)/(\d+)' => 'search', // search
	'/search/(posts|security|IP_notes|bans|log)/(.+)' => 'search', // search

	'/(\%b)/ban(delete)?/(\d+)' => 'secure_POST ban_post', // ban poster
	'/(\%b)/move/(\d+)' => 'secure_POST move', // move thread
	'/(\%b)/edit(_raw)?/(\d+)' => 'secure_POST edit_post', // edit post
	'/(\%b)/delete/(\d+)' => 'secure delete', // delete post
	'/(\%b)/deletefile/(\d+)/(\d+)' => 'secure deletefile', // delete file from post
	'/(\%b)/approve/(\d+)' => 'secure approve', // approve post
	'/(\%b)/spoiler/(\d+)' => 'secure spoiler_image', // spoiler files
	'/(\%b)/deletebyip/(\d+)(/global)?' => 'secure deletebyip', // delete all posts by IP address
	'/(\%b)/(un)?lock/(\d+)' => 'secure lock', // lock thread
	'/(\%b)/(un)?sticky/(\d+)' => 'secure sticky', // sticky thread
	'/(\%b)/(un)?cycle/(\d+)' => 'secure cycle', // cycle thread
	'/(\%b)/bump(un)?lock/(\d+)' => 'secure bumplock', // "bumplock" thread

	// This should always be at the end:
	'/(\%b)/' => 'view_board',
	'/(\%b)/' . preg_quote($config['file_index'], '!') => 'view_board',
	'/(\%b)/' . preg_quote($config['file_catalog'], '!') => 'view_catalog',
	'/(\%b)/' . str_replace('%d', '(\d+)', preg_quote($config['file_boardpage'], '!')) => 'view_board',
	'/(\%b)/' . str_replace('%d', '(\d+)', preg_quote($config['file_post50'], '!')) => 'view_thread50',
	'/(\%b)/' . str_replace('%d', '(\d+)', preg_quote($config['file_post'], '!')) => 'view_thread'
];

if (!$kara_user) {
	// build named-group regexes for /<board>/<post>.html and /<board>/<post50>.html
	$board_re = sprintf(substr($config['board_path'], 0, -1), '(?P<board>' . $config['board_regex'] . ')');
	$post_re = '!^/' . $board_re . '/' . str_replace('%d','(?P<id>\d+)', preg_quote($config['file_post'], '!')) . '(?:&.*)?$!u';

	// try thread
	if(preg_match($post_re, $query, $m)){
		$dest = '/' . $m['board'] . '/' . str_replace('%d', $m['id'], $config['file_post']);
		header('Location: ' . $dest, true, $config['redirect_http']);
		exit;
	}
	$pages = [ '!^(.+)?$!' => 'login' ];
} elseif (isset($_GET['status'], $_GET['r'])) {
	header('Location: ' . $_GET['r'], true, (int)$_GET['status']);
	exit;
}

$new_pages = [];
foreach ($pages as $key => $callback) {
	if (is_string($callback) && preg_match('/^secure /', $callback)) {
		$key .= '(/(?P<token>[a-f0-9]{8}))?';
	}
	$key = str_replace('\%b', '?P<board>' . sprintf(substr($config['board_path'], 0, -1), $config['board_regex']), $key);
	$new_pages[(!empty($key) and $key[0] == '!') ? $key : '!^' . $key . '(?:&[^&=]+=[^&]*)*$!u'] = $callback;
}
$pages = $new_pages;

foreach ($pages as $uri => $handler) {
	if (preg_match($uri, $query, $matches)) {
		$matches[0] = $ctx; // Replace the text captured by the full pattern with a reference to the context.

		if (isset($matches['board'])) {
			$board_match = $matches['board'];
			unset($matches['board']);
			$key = array_search($board_match, $matches);
			if (preg_match('/^' . sprintf(substr($config['board_path'], 0, -1), '(' . $config['board_regex'] . ')') . '$/u', $matches[$key], $board_match)) {
				$matches[$key] = $board_match[1];
			}
		}

		if (is_string($handler) && preg_match('/^secure(_POST)? /', $handler, $m)) {
			$secure_post_only = isset($m[1]);
			if (!$secure_post_only || $_SERVER['REQUEST_METHOD'] == 'POST') {
				$token = isset($matches['token']) ? $matches['token'] : (isset($_POST['token']) ? $_POST['token'] : false);

				if ($token === false) {
					if ($secure_post_only)
						error($config['error']['csrf']);
					else {
						mod_confirm($ctx, substr($query, 1));
						exit;
					}
				}

				// CSRF-protected page; validate security token
				$actual_query = preg_replace('!/([a-f0-9]{8})$!', '', $query);
				if ($token != make_secure_link_token(substr($actual_query, 1))) {
					error($config['error']['csrf']);
				}
			}
			$handler = preg_replace('/^secure(_POST)? /', '', $handler);
		}

		if ($config['debug']) {
			$debug['showUserPage'] = [
				'req' => $query,
				'match' => $uri,
				'handler' => $handler,
			];
			$debug['time']['parse_mod_req'] = '~' . round((microtime(true) - $parse_start_time) * 1000, 2) . 'ms';
		}

		// We don't want to call named parameters (PHP 8).
		$matches = array_values($matches);

		if (is_string($handler)) {
			if ($handler[0] == ':') {
				header('Location: ' . substr($handler, 1),  true, $config['redirect_http']);
			} elseif (is_callable("mod_$handler")) {
				call_user_func_array("mod_$handler", $matches);
			} else {
				error("Mod page '$handler' not found!");
			}
		} elseif (is_callable($handler)) {
			call_user_func_array($handler, $matches);
		} else {
			error("Mod page '$handler' not a string, and not callable!");
		}

		exit;
	}
}

error($config['error']['404']);
