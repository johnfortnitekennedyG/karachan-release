<?php

use Karachan\Context;
use Karachan\Functions\Format;
use Karachan\Data\Driver\CacheDriver;

function mod_confirm(Context $ctx, $request) {
	$config = $ctx->get('config');
	showUserPage('Confirm action', $config['file_mod_confim'], ['request' => $request, 'token' => make_secure_link_token($request) ]);
}

function mod_logout(Context $ctx) {
	$config = $ctx->get('config');
	destroyCookies();
	header('Location: ?/', true, $config['redirect_http']);
}

function mod_dashboard(Context $ctx) {
	global $kara_user;
	$config = $ctx->get('config');
	$args = [];
	$args['boards'] = getAllBoardByURIs();
	$query = query('SELECT COUNT(*) FROM ``reports``') or error(db_error($query));
	$args['reports'] = $query->fetchColumn();
	$query = query('SELECT COUNT(*) FROM ``ban_appeals``') or error(db_error($query));
	$args['appeals'] = $query->fetchColumn();
	$args['logout_token'] = make_secure_link_token('logout');
	$args['personalboard'] = findUserboard($kara_user['id']);
	showUserPage('Dashboard', $config['file_mod_dashboard'], $args);
}

function mod_search_redirect(Context $ctx) {
	$config = $ctx->get('config');

	if(!hasPerm('search')) { error($config['error']['noaccess']); }

	if(isset($_POST['query'], $_POST['type']) && in_array($_POST['type'], array('posts', 'security', 'IP_notes', 'bans', 'log'))) {
		$query = $_POST['query'];
		$query = urlencode($query);
		$query = str_replace('_', '%5F', $query);
		$query = str_replace('+', '_', $query);

		if($query === '') {
			header('Location: ?/', true, $config['redirect_http']);
			return;
		}

		header('Location: ?/search/' . $_POST['type'] . '/' . $query, true, $config['redirect_http']);
	} else {
		header('Location: ?/', true, $config['redirect_http']);
	}
}

function mod_search(Context $ctx, $type, $search_query_escaped, $page_no = 1) {
	global $pdo, $config;

	if(!hasPerm('search')) { error($config['error']['noaccess']); }

	// Unescape query
	$query = str_replace('_', ' ', $search_query_escaped);
	$query = urldecode($query);
	$search_query = $query;

	// Form a series of LIKE clauses for the query.
	// This gets a little complicated.

	// Escape "escape" character
	$query = str_replace('!', '!!', $query);

	// Escape SQL wildcard
	$query = str_replace('%', '!%', $query);

	// Use asterisk as wildcard instead
	$query = str_replace('*', '%', $query);

	$query = str_replace('`', '!`', $query);

	// Array of phrases to match
	$match = [];

	// Exact phrases ("like this")
	if(preg_match_all('/"(.+?)"/', $query, $exact_phrases)) {
		$exact_phrases = $exact_phrases[1];
		foreach($exact_phrases as $phrase) {
			$query = str_replace("\"{$phrase}\"", '', $query);
			$match[] = $pdo->quote($phrase);
		}
	}

	// Non-exact phrases (ie. plain keywords)
	$keywords = explode(' ', $query);
	foreach($keywords as $word) {
		if(empty($word))
			continue;
		$match[] = $pdo->quote($word);
	}

	// Which `field` to search?
	if($type == 'posts') { $sql_field = array('body_nomarkup', 'files', 'subject', 'filehash', 'ip', 'name', 'trip'); }
	if($type == 'IP_notes') { $sql_field = 'body'; }
	if($type == 'bans') { $sql_field = 'reason'; }
	if($type == 'log') { $sql_field = 'text'; }
	if($type == 'security') { $sql_field = 'pearlsecurity'; $type = 'posts'; }

	// Build the "LIKE 'this' AND LIKE 'that'" etc. part of the SQL query
	$sql_like = '';
	foreach($match as $phrase) {
		if(!empty($sql_like))
			$sql_like .= ' AND ';
		$phrase = preg_replace('/^\'(.+)\'$/', '\'%$1%\'', $phrase);
		if(is_array($sql_field)) {
			foreach($sql_field as $field) {
				$sql_like .= '`' . $field . '` LIKE ' . $phrase . ' ESCAPE \'!\' OR';
			}
			$sql_like = preg_replace('/ OR$/', '', $sql_like);
		} else {
			$sql_like .= '`' . $sql_field . '` LIKE ' . $phrase . ' ESCAPE \'!\'';
		}
	}

	// Compile SQL query

	if($type == 'posts') {
		$query = '';
		$boards = getAllBoardByURIs();
		if(empty($boards))
			error('There are no boards to search!');

		foreach($boards as $board) {
			openBoard($board['uri']);
			if(!hasPerm('search_posts'))
				continue;

			if(!empty($query))
				$query .= ' UNION ALL ';
			$query .= sprintf("SELECT *, '%s' AS `board` FROM ``posts_%s`` WHERE %s", $board['uri'], $board['uri'], $sql_like);
		}

		// You weren't allowed to search any boards
		if(empty($query))
				error($config['error']['noaccess']);

		$query .= ' ORDER BY `sticky` DESC, `id` DESC';
	}

	if($type == 'IP_notes') {
		$query = 'SELECT * FROM ``ip_notes`` LEFT JOIN ``karausers`` ON `mod` = ``karausers``.`id` WHERE ' . $sql_like . ' ORDER BY `time` DESC';
		$sql_table = 'ip_notes';
		if(!hasPerm('view_notes') || !hasPerm('show_ip'))
			error($config['error']['noaccess']);
	}

	if($type == 'bans') {
		$query = 'SELECT ``bans``.*, `username` FROM ``bans`` LEFT JOIN ``karausers`` ON `creator` = ``karausers``.`id` WHERE ' . $sql_like . ' ORDER BY (`expires` IS NOT NULL AND `expires` < UNIX_TIMESTAMP()), `created` DESC';
		$sql_table = 'bans';
		if(!hasPerm('view_banlist'))
			error($config['error']['noaccess']);
	}

	if($type == 'log') {
		$query = 'SELECT `username`, `mod`, `ip`, `board`, `time`, `text` FROM ``modlogs`` LEFT JOIN ``karausers`` ON `mod` = ``karausers``.`id` WHERE ' . $sql_like . ' ORDER BY `time` DESC';
		$sql_table = 'modlogs';
		if(!hasPerm('modlog'))
			error($config['error']['noaccess']);
	}

	// Execute SQL query (with pages)
	$q = query($query . ' LIMIT ' . (($page_no - 1) * $config['mod']['search_page']) . ', ' . $config['mod']['search_page']) or error(db_error());
	$results = $q->fetchAll(PDO::FETCH_ASSOC);

	// Get total result count
	if($type == 'posts') {
		$q = query("SELECT COUNT(*) FROM ($query) AS `tmp_table`") or error(db_error());
		$result_count = $q->fetchColumn();
	} else {
		$q = query('SELECT COUNT(*) FROM `' . $sql_table . '` WHERE ' . $sql_like) or error(db_error());
		$result_count = $q->fetchColumn();
	}

	if($type == 'bans') {
		foreach($results as &$ban) {
			$ban['mask'] = Bans::range_to_string(array($ban['ipstart'], $ban['ipend']));
			if(filter_var($ban['mask'], FILTER_VALIDATE_IP) !== false)
				$ban['single_addr'] = true;
		}
	}

	if($type == 'posts') {
		foreach($results as &$post) {
			$post['snippet'] = message_snippet($post['body']);
		}
	}

	// $results now contains the search results
	showUserPage(
		'Search results',
		$config['file_mod_search_results'],
		[
			'search_type' => $type,
			'search_query' => $search_query,
			'search_query_escaped' => $search_query_escaped,
			'result_count' => $result_count,
			'results' => $results
		]
	);
}

function mod_edit_board(Context $ctx, $boardName = null) {
	global $board, $kara_user;
	$config = $ctx->get('config');
	$cache = $ctx->get(CacheDriver::class);

	// Check if we can open the board first.
	if($boardName) { openBoard($boardName); } else { $board = null; }

	// Handle creating new boards.
	if(isset($_POST['uri'], $_POST['title'], $_POST['subtitle'])) {
		$bid = 0;
		if(!hasPerm('newboard')) {
			if(!hasPerm('userboards')) { error($config['error']['noaccess']); } else {
				$bid = $kara_user['id'];
				$userboard = findUserboard($bid);
				if($userboard) { error(sprintf($config['error']['boardexists'], $userboard['uri'])); }
			}
		}
		if($_POST['uri'] == '') { error(sprintf($config['error']['required'], 'URI')); }
		if($_POST['title'] == '') { error(sprintf($config['error']['required'], 'title')); }
		if(!preg_match('/^' . $config['board_regex'] . '$/u', $_POST['uri'])) { error(sprintf($config['error']['invalidfield'], 'URI')); }
		add_new_board_quick($_POST['uri'], $_POST['title'], $_POST['subtitle'], $bid);
		header('Location: ?/' . $board['uri'] . '/' . $config['file_index'], true, $config['redirect_http']);
		return;
	}

	// Handle editing boards.
	if(isset($_POST['title'], $_POST['subtitle'])) {
		if(!hasPerm('manageboards')) {
			if($board['owner'] != $kara_user['id']) { error($config['error']['noaccess']); }
		}
		if(isset($_POST['delete'])) {
			delete_board_quick($boardName);
		} else {
			$query = prepare('UPDATE ``boards`` SET `title` = :title, `subtitle` = :subtitle WHERE `uri` = :uri');
			$query->bindValue(':uri', $board['uri']);
			$query->bindValue(':title', $_POST['title']);
			$query->bindValue(':subtitle', $_POST['subtitle']);
			$query->execute() or error(db_error($query));
			modLog('Edited board information for ' . sprintf($config['board_abbreviation'], $board['uri']), false);
		}
		header('Location: ?/', true, $config['redirect_http']);
		return;
	}

	// Editing or creating?
	if($board) {
		showUserPage(
			sprintf('%s: ' . $config['board_abbreviation'], 'Edit board', $board['uri']),
			$config['file_mod_board'], ['board' => $board, 'token' => make_secure_link_token('edit/' . $board['uri'])]
		);
	} else {
		showUserPage('New board', $config['file_mod_board'], ['new' => true, 'token' => make_secure_link_token('new-board')]);
	}
}

function mod_news(Context $ctx, $page_no = 1) {
	global $pdo;
	$config = $ctx->get('config');

	if($page_no < 1) { error($config['error']['404']); }

	if(isset($_POST['subject'], $_POST['body'])) {
		if(!hasPerm('news')) { error($config['error']['noaccess']); }
		$_POST['body'] = escape_markup_modifiers($_POST['body']);
		markup($_POST['body']);
		$query = prepare('INSERT INTO ``news`` VALUES (NULL, :name, :time, :subject, :body)');
		$query->bindValue(':name', $_POST['name']);
		$query->bindvalue(':time', time());
		$query->bindValue(':subject', $_POST['subject']);
		$query->bindValue(':body', $_POST['body']);
		$query->execute() or error(db_error($query));
		modLog('Posted a news entry');
		generateKCIndex();
		header('Location: ?/usernewsboard#' . $pdo->lastInsertId(), true, $config['redirect_http']);
	}

	$query = prepare("SELECT * FROM ``news`` ORDER BY `id` DESC LIMIT :offset, :limit");
	$query->bindValue(':limit', $config['mod']['news_page'], PDO::PARAM_INT);
	$query->bindValue(':offset', ($page_no - 1) * $config['mod']['news_page'], PDO::PARAM_INT);
	$query->execute() or error(db_error($query));
	$news = $query->fetchAll(PDO::FETCH_ASSOC);

	if(empty($news) && $page_no > 1) { error($config['error']['404']); }
	foreach($news as &$entry) {
		$entry['delete_token'] = make_secure_link_token('usernewsboard/delete/' . $entry['id']);
	}

	$query = prepare("SELECT COUNT(*) FROM ``news``");
	$query->execute() or error(db_error($query));
	$count = $query->fetchColumn();

	showUserPage(
		'News',
		$config['file_mod_news'],
		[
			'news' => $news,
			'count' => $count,
			'token' => make_secure_link_token('usernewsboard')
		]
	);
}

function mod_news_delete(Context $ctx, $id) {
	$config = $ctx->get('config');

	if(!hasPerm('news_delete')) { error($config['error']['noaccess']); }

	$query = prepare('DELETE FROM ``news`` WHERE `id` = :id');
	$query->bindValue(':id', $id);
	$query->execute() or error(db_error($query));

	modLog('Deleted a news entry');
	header('Location: ?/usernewsboard', true, $config['redirect_http']);
}

function mod_log(Context $ctx, $page_no = 1) {
	$config = $ctx->get('config');

	if($page_no < 1) { error($config['error']['404']); }
	if(!hasPerm('modlog')) { error($config['error']['noaccess']); }

	$query = prepare("SELECT `username`, `mod`, `ip`, `board`, `time`, `text` FROM ``modlogs`` LEFT JOIN ``karausers`` ON `mod` = ``karausers``.`id` ORDER BY `time` DESC LIMIT :offset, :limit");
	$query->bindValue(':limit', $config['mod']['modlog_page'], PDO::PARAM_INT);
	$query->bindValue(':offset', ($page_no - 1) * $config['mod']['modlog_page'], PDO::PARAM_INT);
	$query->execute() or error(db_error($query));
	$logs = $query->fetchAll(PDO::FETCH_ASSOC);

	if(empty($logs) && $page_no > 1) { error($config['error']['404']); }

	$query = prepare("SELECT COUNT(*) FROM ``modlogs``");
	$query->execute() or error(db_error($query));
	$count = $query->fetchColumn();

	showUserPage('Moderation log', $config['file_mod_log'], [ 'logs' => $logs, 'count' => $count ]);
}

function mod_user_log(Context $ctx, $username, $page_no = 1) {
	$config = $ctx->get('config');

	if($page_no < 1) { error($config['error']['404']); }
	if(!hasPerm('modlog')) { error($config['error']['noaccess']); }

	$query = prepare("SELECT `username`, `mod`, `ip`, `board`, `time`, `text` FROM ``modlogs`` LEFT JOIN ``karausers`` ON `mod` = ``karausers``.`id` WHERE `username` = :username ORDER BY `time` DESC LIMIT :offset, :limit");
	$query->bindValue(':username', $username);
	$query->bindValue(':limit', $config['mod']['modlog_page'], PDO::PARAM_INT);
	$query->bindValue(':offset', ($page_no - 1) * $config['mod']['modlog_page'], PDO::PARAM_INT);
	$query->execute() or error(db_error($query));
	$logs = $query->fetchAll(PDO::FETCH_ASSOC);

	if(empty($logs) && $page_no > 1) { error($config['error']['404']); }

	$query = prepare("SELECT COUNT(*) FROM ``modlogs`` LEFT JOIN ``karausers`` ON `mod` = ``karausers``.`id` WHERE `username` = :username");
	$query->bindValue(':username', $username);
	$query->execute() or error(db_error($query));
	$count = $query->fetchColumn();

	showUserPage('Moderation log', $config['file_mod_log'], [ 'logs' => $logs, 'count' => $count, 'username' => $username ]);
}

function mod_board_log(Context $ctx, $board, $page_no = 1, $hide_names = false, $public = false) {
	$config = $ctx->get('config');

	if($page_no < 1) { error($config['error']['404']); }

	if(!hasPerm('mod_board_log') && !$public) { error($config['error']['noaccess']); }

	$query = prepare("SELECT `username`, `mod`, `ip`, `board`, `time`, `text` FROM ``modlogs`` LEFT JOIN ``karausers`` ON `mod` = ``karausers``.`id` WHERE `board` = :board ORDER BY `time` DESC LIMIT :offset, :limit");
	$query->bindValue(':board', $board);
	$query->bindValue(':limit', $config['mod']['modlog_page'], PDO::PARAM_INT);
	$query->bindValue(':offset', ($page_no - 1) * $config['mod']['modlog_page'], PDO::PARAM_INT);
	$query->execute() or error(db_error($query));
	$logs = $query->fetchAll(PDO::FETCH_ASSOC);

	if(empty($logs) && $page_no > 1) { error($config['error']['404']); }
	if(!hasPerm('show_ip')) {
		// Supports ipv4 only!
		foreach($logs as $i => &$log) {
			$log['text'] = preg_replace_callback('/(?:<a href="\?\/IP\/\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}">)?(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})(?:<\/a>)?/', function($matches) {
				return "xxxx";//less_ip($matches[1]);
			}, $log['text']);
		}
	}

	$query = prepare("SELECT COUNT(*) FROM ``modlogs`` LEFT JOIN ``karausers`` ON `mod` = ``karausers``.`id` WHERE `board` = :board");
	$query->bindValue(':board', $board);
	$query->execute() or error(db_error($query));
	$count = $query->fetchColumn();

	showUserPage(
		'Board log',
		$config['file_mod_log'],
		[
			'logs' => $logs,
			'count' => $count,
			'board' => $board,
			'hide_names' => $hide_names,
			'public' => $public
		]
	);
}

function mod_view_catalog(Context $ctx, $boardName) {
	global $kara_user;
	$config = $ctx->get('config');
	if(!openBoard($boardName)) { error($config['error']['noboard']); }
	echo generateBoardCatalog();
}

function mod_view_board(Context $ctx, $boardName, $page_no = 1) {
	global $kara_user;
	$config = $ctx->get('config');
	if(!openBoard($boardName)) { error($config['error']['noboard']); }
	if(!$page = index($page_no, $kara_user)) { error($config['error']['404']); }
	$page['pages'] = getPages(true);
	$page['pages'][$page_no - 1]['selected'] = true;
	$page['btn'] = getPageButtons($page['pages'], true);
	$page['mod'] = true;
	$page['karadiap'] = $kara_user;
	$page['config'] = $config;
	echo Element($config['file_board_index'], $page);
}

function mod_view_thread(Context $ctx, $boardName, $thread) {
	global $kara_user;
	$config = $ctx->get('config');
	if(!openBoard($boardName)) { error($config['error']['noboard']); }
	echo buildThreadUniversal(round($thread), $kara_user, false);
}

function mod_view_thread50(Context $ctx, $boardName, $thread) {
	global $kara_user;
	$config = $ctx->get('config');
	if(!openBoard($boardName)) { error($config['error']['noboard']); }
	echo buildThreadUniversal(round($thread), $kara_user, true);
}

function mod_ip_remove_note(Context $ctx, $cloaked_ip, $id) {
	$ip = uncloak_ip($cloaked_ip);
	$config = $ctx->get('config');
	if(!hasPerm('remove_notes')) { error($config['error']['noaccess']); }
	if(filter_var($ip, FILTER_VALIDATE_IP) === false) { error("Invalid IP address."); }
	$query = prepare('DELETE FROM ``ip_notes`` WHERE `ip` = :ip AND `id` = :id');
	$query->bindValue(':ip', $ip);
	$query->bindValue(':id', $id);
	$query->execute() or error(db_error($query));
	modLog("Removed a note for <a href=\"?/IP/{$cloaked_ip}\">{$cloaked_ip}</a>");
	header('Location: ?/IP/' . $cloaked_ip . '#notes', true, $config['redirect_http']);
}

function mod_ip(Context $ctx, $cip) {
	global $kara_user;
	$ip = uncloak_ip($cip);
	$config = $ctx->get('config');
	if(filter_var($ip, FILTER_VALIDATE_IP) === false) { error("Invalid IP address."); }

	if(isset($_POST['ban_id'], $_POST['unban'])) {
		if(!hasPerm('unban')) { error($config['error']['noaccess']); }
		Bans::delete($_POST['ban_id'], true);
		header('Location: ?/IP/' . $cip . '#bans', true, $config['redirect_http']);
		return;
	}

	if(isset($_POST['ban_id'], $_POST['edit_ban'])) {
		if(!hasPerm('edit_ban')) { error($config['error']['noaccess']); }
		header('Location: ?/edit_ban/' . $_POST['ban_id'], true, $config['redirect_http']);
		return;
	}

	if(isset($_POST['note'])) {
		if(!hasPerm('create_notes')) { error($config['error']['noaccess']); }
		$_POST['note'] = escape_markup_modifiers($_POST['note']);
		markup($_POST['note']);
		$query = prepare('INSERT INTO ``ip_notes`` VALUES (NULL, :ip, :mod, :time, :body)');
		$query->bindValue(':ip', $ip);
		$query->bindValue(':mod', $kara_user['id']);
		$query->bindValue(':time', time());
		$query->bindValue(':body', $_POST['note']);
		$query->execute() or error(db_error($query));

		modLog("Added a note for <a href=\"?/IP/{$cip}\">{$cip}</a>");
		header('Location: ?/IP/' . $cip . '#notes', true, $config['redirect_http']);
		return;
	}


	$args = [];
	$args['ip'] = $ip;
	$args['posts'] = [];

	if($config['mod']['dns_lookup'] && empty($config['ipcrypt_key'])) { $args['hostname'] = rDNS($ip); }

	$boards = getAllBoardByURIs();
	foreach($boards as $board) {
		openBoard($board['uri']);
		if(!hasPerm('show_ip')) { continue; }
		$query = prepare(sprintf('SELECT * FROM ``posts_%s`` WHERE `ip` = :ip ORDER BY `sticky` DESC, `id` DESC LIMIT :limit', $board['uri']));
		$query->bindValue(':ip', $ip);
		$query->bindValue(':limit', $config['mod']['ip_recentposts'], PDO::PARAM_INT);
		$query->execute() or error(db_error($query));

		while($post = $query->fetch(PDO::FETCH_ASSOC)) {
			if(!$post['thread']) {
				$po = new Thread($post, '?/', $kara_user, false);
			} else {
				$po = new Post($post, '?/', $kara_user);
			}

			if(!isset($args['posts'][$board['uri']])) { $args['posts'][$board['uri']] = array('board' => $board, 'posts' => []); }
			$args['posts'][$board['uri']]['posts'][] = $po->build(true);
		}
	}

	$args['boards'] = $boards;
	$args['token'] = make_secure_link_token('ban');

	if(hasPerm('view_ban')) {
		$args['bans'] = Bans::find($ip, true, null, $config['auto_maintenance']);
	}

	if(hasPerm('view_notes')) {
		$query = prepare("SELECT ``ip_notes``.*, `username` FROM ``ip_notes`` LEFT JOIN ``karausers`` ON `mod` = ``karausers``.`id` WHERE `ip` = :ip ORDER BY `time` DESC");
		$query->bindValue(':ip', $ip);
		$query->execute() or error(db_error($query));
		$args['notes'] = $query->fetchAll(PDO::FETCH_ASSOC);
	}

	if(hasPerm('modlog_ip')) {
		$query = prepare("SELECT `username`, `mod`, `ip`, `board`, `time`, `text` FROM ``modlogs`` LEFT JOIN ``karausers`` ON `mod` = ``karausers``.`id` WHERE `text` LIKE :search ORDER BY `time` DESC LIMIT 50");
		$query->bindValue(':search', '%' . $cip . '%');
		$query->execute() or error(db_error($query));
		$args['logs'] = $query->fetchAll(PDO::FETCH_ASSOC);
	} else {
		$args['logs'] = [];
	}

	$args['security_token'] = make_secure_link_token('IP/' . $cip);

	showUserPage(sprintf('%s: %s', 'IP', htmlspecialchars($cip)), $config['file_mod_view_ip'], $args, $args['hostname']);
}

function mod_edit_ban(Context $ctx, $ban_id) {
	$config = $ctx->get('config');

	if(!hasPerm('edit_ban')) { error($config['error']['noaccess']); }

	$args['bans'] = Bans::find(null, true, $ban_id, $config['auto_maintenance']);
	$args['ban_id'] = $ban_id;

	if(!$args['bans']) { error($config['error']['404']); }

	if(isset($_POST['new_ban'])) {

		$new_ban['mask'] = $args['bans'][0]['mask'];
		$new_ban['post'] = isset($args['bans'][0]['post']) ? $args['bans'][0]['post'] : false;

		if(isset($_POST['reason'])) { $new_ban['reason'] = $_POST['reason']; }
		else { $new_ban['reason'] = $args['bans'][0]['reason']; }

		if(isset($_POST['length']) && !empty($_POST['length'])) { $new_ban['length'] = $_POST['length']; }
		else { $new_ban['length'] = false; }

		Bans::new_ban($new_ban['mask'], $new_ban['reason'], $new_ban['length'], false, $new_ban['post']);
		Bans::delete($ban_id);

		header('Location: ?/', true, $config['redirect_http']);
	}

	$args['token'] = make_secure_link_token('edit_ban/' . $ban_id);

	showUserPage('Edit ban', 'mod/edit_ban.html', $args);
}

function mod_ban(Context $ctx) {
	$config = $ctx->get('config');
	if(!hasPerm('ban')) { error($config['error']['noaccess']); }
	if(!isset($_POST['ip'], $_POST['reason'], $_POST['length'])) {
		showUserPage('New ban', $config['file_mod_ban_form'], [ 'token' => make_secure_link_token('ban') ]);
		return;
	}
	Bans::new_ban($_POST['ip'], $_POST['reason'], $_POST['length']);
	if(isset($_POST['redirect'])) { header('Location: ' . $_POST['redirect'], true, $config['redirect_http']); }
	else { header('Location: ?/', true, $config['redirect_http']); }
}

function mod_unban(Context $ctx, $ban_id) {
	$config = $ctx->get('config');
	if(!hasPerm('unban')) { error($config['error']['noaccess']); }
	Bans::delete($ban_id, true);
	header('Location: ?/bans', true, $config['redirect_http']);
	return;
}

function mod_bans(Context $ctx, $page_no = 1) {
	$config = $ctx->get('config');
	if(!hasPerm('view_banlist')) { error($config['error']['noaccess']); }
	if($page_no < 1) { error($config['error']['404']); }

	// paging
	$offset = ($page_no - 1) * $config['mod']['banlist_page'];

	// total count
	$q_total = prepare('SELECT COUNT(*) AS c FROM `bans`');
	$q_total->execute() or error(db_error($q_total));
	$total = (int)$q_total->fetch(PDO::FETCH_ASSOC)['c'];
	$pages = max(1, (int)ceil($total / $config['mod']['banlist_page']));

	// helpers for formatting (keep it local-time, change to gmdate if you want UTC)
	$fmtIp = function($bin){
		if($bin===null || $bin==='') { return null; }
		$s = @inet_ntop($bin); // works for IPv4(4 bytes) + IPv6(16 bytes)
		return $s===false ? bin2hex($bin) : $s; // fallback just in case, LOL
	};
	$fmtTs = function($ts){
		if(!$ts) { return null; }
		return date('Y-m-d H:i:s', (int)$ts);
	};

	// pull rows for this page (newest first)
	$q = prepare('
		SELECT `id`,`ipstart`,`ipend`,`created`,`expires`,`creator`,`reason`,`seen`
		FROM `bans`
		ORDER BY `id` DESC
		LIMIT :limit OFFSET :offset
	');
	$q->bindValue(':limit', $config['mod']['banlist_page'], PDO::PARAM_INT);
	$q->bindValue(':offset', $offset, PDO::PARAM_INT);
	$q->execute() or error(db_error($q));
	$rows = $q->fetchAll(PDO::FETCH_ASSOC);

	// map to view-model for twig
	$bans = [];
	foreach($rows as $r) {
		$ipstart = $fmtIp($r['ipstart']);
		$ipend = $fmtIp($r['ipend']);
		$bans[] = [
			'id' => (int)$r['id'],
			'ipstart' => $ipstart,                                   // string or null
			'ipend' => $ipend,                                       // string or null
			'created_ts' => (int)$r['created'],                      // raw ts
			'created' => $fmtTs($r['created']),                      // pretty
			'expires_ts' => $r['expires']!==null ? (int)$r['expires'] : null,
			'expires' => $r['expires']!==null ? $fmtTs($r['expires']) : 'never',
			'creator' => (int)$r['creator'],
			'reason' => (string)$r['reason'],
			'seen' => (int)$r['seen'] ? 'yes' : 'no',
			// handy booleans for styling if u want
			'active' => ($r['expires']===null || (int)$r['expires'] > time()),
		];
	}

	// render
	showUserPage(
		'Ban list',
		$config['file_mod_ban_list'],
		[
			'bans' => $bans,           // array of assoc rows ready for table
			'page' => $page,
			'pages' => $pages,
			'total' => $total,
			'per_page' => $per_page,
		]
	);
}

function mod_ban_appeals(Context $ctx) {
	global $board;
	$config = $ctx->get('config');
	if(!hasPerm('view_ban_appeals')) { error($config['error']['noaccess']); }

	if(isset($_POST['appeal_id']) && (isset($_POST['unban']) || isset($_POST['deny']))) {
		if(!hasPerm('ban_appeals'))
			error($config['error']['noaccess']);

		$query = query("SELECT *, ``ban_appeals``.`id` AS `id` FROM ``ban_appeals``
			LEFT JOIN ``bans`` ON `ban_id` = ``bans``.`id`
			WHERE ``ban_appeals``.`id` = " . (int)$_POST['appeal_id']) or error(db_error());
		if(!$ban = $query->fetch(PDO::FETCH_ASSOC)) {
			error('Ban appeal not found!');
		}

		$ban['mask'] = cloak_mask(Bans::range_to_string(array($ban['ipstart'], $ban['ipend'])));

		if(isset($_POST['unban'])) {
			modLog('Accepted ban appeal #' . $ban['id'] . ' for ' . $ban['mask']);
			Bans::delete($ban['ban_id'], true);
			query("DELETE FROM ``ban_appeals`` WHERE `id` = " . $ban['id']) or error(db_error());
		} else {
			modLog('Denied ban appeal #' . $ban['id'] . ' for ' . $ban['mask']);
			query("UPDATE ``ban_appeals`` SET `denied` = 1 WHERE `id` = " . $ban['id']) or error(db_error());
		}

		header('Location: ?/ban-appeals', true, $config['redirect_http']);
		return;
	}

	$query = query("SELECT *, ``ban_appeals``.`id` AS `id` FROM ``ban_appeals``
		LEFT JOIN ``bans`` ON `ban_id` = ``bans``.`id`
		LEFT JOIN ``karausers`` ON ``bans``.`creator` = ``karausers``.`id`
		WHERE `denied` != 1 ORDER BY `time`") or error(db_error());
	$ban_appeals = $query->fetchAll(PDO::FETCH_ASSOC);
	foreach($ban_appeals as &$ban) {
		if($ban['post'])
			$ban['post'] = json_decode($ban['post'], true);
		$ban['mask'] = Bans::range_to_string(array($ban['ipstart'], $ban['ipend']));

		if($ban['post'] && isset($ban['post']['board'], $ban['post']['id'])) {
			if(openBoard($ban['post']['board'])) {
				$query = query(sprintf("SELECT `num_files`, `files` FROM ``posts_%s`` WHERE `id` = " .
					(int)$ban['post']['id'], $board['uri']));
				if($_post = $query->fetch(PDO::FETCH_ASSOC)) {
					$_post['files'] = $_post['files'] ? json_decode($_post['files']) : [];
					$ban['post'] = array_merge($ban['post'], $_post);
				} else {
					$ban['post']['files'] = array([]);
					$ban['post']['files'][0]['file'] = 'deleted';
					$ban['post']['num_files'] = 1;
				}
			} else {
				$ban['post']['files'] = array([]);
				$ban['post']['files'][0]['file'] = 'deleted';
				$ban['post']['num_files'] = 1;
			}

			if($ban['post']['thread']) {
				$ban['post'] = new Post($ban['post']);
			} else {
				$ban['post'] = new Thread($ban['post'], null, false, false);
			}
		}
	}

	showUserPage('Ban appeals', $config['file_mod_ban_appeals'], ['ban_appeals' => $ban_appeals, 'token' => make_secure_link_token('ban-appeals')]);
}

function mod_move(Context $ctx, $originBoard, $postID) {
	global $board, $config, $pdo;

	// we found the target board.
	if(isset($_POST['board'])) {
		$targetBoard = $_POST['board'];
		if(!openBoard($originBoard)) { error($config['error']['noboard']); }
		if(!hasPerm('move')) { error($config['error']['noaccess']); }
		if($targetBoard === $originBoard) { error('Target and source board are the same.'); }

		// get the original post
		$query = prepare(sprintf('SELECT * FROM ``posts_%s`` WHERE `id` = :id AND `thread` IS NULL', $originBoard));
		$query->bindValue(':id', $postID);
		$query->execute() or error(db_error($query));
		if(!$post = $query->fetch(PDO::FETCH_ASSOC)) { error($config['error']['404']); }
		$post['op'] = true;
		$post['has_file'] = $post['files'] ? true : false;
		if($post['has_file']) { $post['files'] = json_decode($post['files']); }

		// get the post's replies (if it has any)
		$query = prepare(sprintf('SELECT * FROM ``posts_%s`` WHERE `thread` = :id ORDER BY `id`', $originBoard));
		$query->bindValue(':id', $postID, PDO::PARAM_INT);
		$query->execute() or error(db_error($query));
		$replies = $query->fetchAll(PDO::FETCH_ASSOC);

		// create the new thread on the target board. everything should be set up and done by now
		if(!openBoard($targetBoard)) { error($config['error']['noboard']); }
		$newID = post($post); $op = $post; $op['id'] = $newID;

		// go back to the original board to fetch replies. we have to convert cites and craps so get $newIDs
		$newIDs = array($postID => $newID);
		foreach($replies as &$post) {
			$query = prepare('SELECT `target` FROM ``cites`` WHERE `target_board` = :board AND `board` = :board AND `post` = :post');
			$query->bindValue(':board', $originBoard);
			$query->bindValue(':post', $post['id'], PDO::PARAM_INT);
			$query->execute() or error(db_error($query));

			// correct >>X links
			while($cite = $query->fetch(PDO::FETCH_ASSOC)) {
				if(isset($newIDs[$cite['target']])) {
					$post['body_nomarkup'] = preg_replace('/(>>(>\/' . preg_quote($originBoard, '/') . '\/)?)' . preg_quote($cite['target'], '/') . '/', '>>' . $newIDs[$cite['target']], $post['body_nomarkup']);
					$post['body'] = $post['body_nomarkup'];
				}
			}

			$post['has_file'] = $post['files'] ? true : false;
			if($post['has_file']) { $post['files'] = json_decode($post['files']); }
			$post['op'] = false;
			$post['body'] = $post['body_nomarkup'];
			$post['tracked_cites'] = markup($post['body'], true);
			$post['thread'] = $newID;

			// insert reply
			$newIDs[$post['id']] = $newPostID = post($post);
			if(!empty($post['tracked_cites'])) {
				$insert_rows = [];
				foreach($post['tracked_cites'] as $cite) { $insert_rows[] = '(' . $pdo->quote($board['uri']) . ', ' . $newPostID . ', ' . $pdo->quote($cite[0]) . ', ' . (int)$cite[1] . ')'; }
				query('INSERT INTO ``cites`` VALUES ' . implode(', ', $insert_rows)) or error(db_error());
			}
		}
		modLog("Moved thread #{$postID} to " . sprintf($config['board_abbreviation'], $targetBoard) . " (#{$newID})", $originBoard);

		// build new thread
		autocleanThreadsMaxpages();
		buildThreadFilesFunction($newID, true);
		$newboard = $board;
		openBoard($originBoard);
		deletePost($postID, true, true, false);
		buildBoardIndexAndPages(true);
		header('Location: ?/' . sprintf($config['board_path'], $newboard['uri']) . getThreadFileLink($op), true, $config['redirect_http']);
	} else {
		$boards = getAllBoardByURIs();
		if(count($boards) <= 1) { error('Impossible to move thread; there is only one board.'); }
		$security_token = make_secure_link_token($originBoard . '/move/' . $postID);
		showUserPage('Move thread', $config['file_mod_move'], ['post' => $postID, 'board' => $originBoard, 'boards' => $boards, 'token' => $security_token]);
	}
}

function mod_ban_post(Context $ctx, $board, $delete, $post, $token = false) {
	$config = $ctx->get('config');

	if(!hasPerm('ban')) { error($config['error']['noaccess']); }
	if(!openBoard($board)) { error($config['error']['noboard']); }

	$security_token = make_secure_link_token($board . '/ban/' . $post);
	$query = prepare(sprintf('SELECT ' . ($config['ban_show_post'] ? '*' : '`ip`, `thread`') . ' FROM ``posts_%s`` WHERE `id` = :id', $board));
	$query->bindValue(':id', $post);
	$query->execute() or error(db_error($query));
	if(!$_post = $query->fetch(PDO::FETCH_ASSOC)) { error($config['error']['404']); }

	$thread = $_post['thread'];
	$ip = $_post['ip'];

	if(isset($_POST['new_ban'], $_POST['reason'], $_POST['length'])) {
		if(isset($_POST['ip'])) { $ip = $_POST['ip']; }

		Bans::new_ban($ip, $_POST['reason'], $_POST['length'], false, $config['ban_show_post'] ? $_post : false);

		if(isset($_POST['public_message'], $_POST['message'])) {
			// public ban message
			$length_english = Bans::parse_time($_POST['length']) ? 'for ' . Format\until(Bans::parse_time($_POST['length'])) : 'permanently';
			$_POST['message'] = preg_replace('/[\r\n]/', '', $_POST['message']);
			$_POST['message'] = str_replace('%length%', $length_english, $_POST['message']);
			$_POST['message'] = str_replace('%LENGTH%', strtoupper($length_english), $_POST['message']);
			$query = prepare(sprintf('UPDATE ``posts_%s`` SET `body_nomarkup` = CONCAT(`body_nomarkup`, :body_nomarkup) WHERE `id` = :id', $board));
			$query->bindValue(':id', $post);
			$query->bindValue(':body_nomarkup', sprintf("\n<mephboard ban message>%s</mephboard>", utf8tohtml($_POST['message'])));
			$query->execute() or error(db_error($query));
			rebuildPost($post);

			modLog("Attached a public ban message to post #{$post}: " . utf8tohtml($_POST['message']));
			buildThreadFilesFunction($thread ? $thread : $post, true);
		} elseif (isset($_POST['delete']) && (int) $_POST['delete']) {
			// Delete post
			deletePost($post);
			modLog("Deleted post #{$post}");
			// Rebuild board
			buildBoardIndexAndPages(true);
		}

		header('Location: ?/' . sprintf($config['board_path'], $board) . $config['file_index'], true, $config['redirect_http']);
	}

	$args = array(
		'ip' => $ip,
		'hide_ip' => !hasPerm('show_ip'),
		'post' => $post,
		'board' => $board,
		'delete' => (bool)$delete,
		'reasons' => $config['premade_ban_reasons'],
		'token' => $security_token
	);

	showUserPage('New ban', $config['file_mod_ban_form'], $args);
}

function mod_edit_post(Context $ctx, $board, $edit_raw_html, $postID) {
	$config = $ctx->get('config');

	if(!openBoard($board)) { error($config['error']['noboard']); }
	if(!hasPerm('editpost')) { error($config['error']['noaccess']); }

	if($edit_raw_html && !hasPerm('rawhtml')) { error($config['error']['noaccess']); }

	$security_token = make_secure_link_token($board . '/edit' . ($edit_raw_html ? '_raw' : '') . '/' . $postID);

	$query = prepare(sprintf('SELECT * FROM ``posts_%s`` WHERE `id` = :id', $board));
	$query->bindValue(':id', $postID);
	$query->execute() or error(db_error($query));

	if(!$post = $query->fetch(PDO::FETCH_ASSOC))
		error($config['error']['404']);

	if(isset($_POST['name'], $_POST['subject'], $_POST['body'])) {
		// Remove any modifiers they may have put in
		$_POST['body'] = remove_modifiers($_POST['body']);

		// Add back modifiers in the original post
		$modifiers = extract_modifiers($post['body_nomarkup']);
		foreach($modifiers as $key => $value) {
			$_POST['body'] .= "<mephboard $key>$value</mephboard>";
		}

		if($edit_raw_html)
			$query = prepare(sprintf('UPDATE ``posts_%s`` SET `name` = :name, `subject` = :subject, `body` = :body, `body_nomarkup` = :body_nomarkup WHERE `id` = :id', $board));
		else
			$query = prepare(sprintf('UPDATE ``posts_%s`` SET `name` = :name, `subject` = :subject, `body_nomarkup` = :body WHERE `id` = :id', $board));
		$query->bindValue(':id', $postID);
		$query->bindValue(':name', $_POST['name']);
		$query->bindValue(':subject', $_POST['subject']);
		$query->bindValue(':body', $_POST['body']);
		if($edit_raw_html) {
			$body_nomarkup = $_POST['body'] . "\n<mephboard raw html>1</mephboard>";
			$query->bindValue(':body_nomarkup', $body_nomarkup);
		}
		$query->execute() or error(db_error($query));

		if($edit_raw_html) {
			modLog("Edited raw HTML of post #{$postID}");
		} else {
			modLog("Edited post #{$postID}");
			rebuildPost($postID);
		}

		buildBoardIndexAndPages(true);
		header('Location: ?/' . sprintf($config['board_path'], $board) . getThreadFileLink($post) . '#' . $postID, true, $config['redirect_http']);
	} else {
		// Remove modifiers
		$post['body_nomarkup'] = remove_modifiers($post['body_nomarkup']);

		$post['body_nomarkup'] = utf8tohtml($post['body_nomarkup']);
		$post['body'] = utf8tohtml($post['body']);
		if($config['minify_html']) {
			$post['body_nomarkup'] = str_replace("\n", '&#010;', $post['body_nomarkup']);
			$post['body'] = str_replace("\n", '&#010;', $post['body']);
			$post['body_nomarkup'] = str_replace("\r", '', $post['body_nomarkup']);
			$post['body'] = str_replace("\r", '', $post['body']);
			$post['body_nomarkup'] = str_replace("\t", '&#09;', $post['body_nomarkup']);
			$post['body'] = str_replace("\t", '&#09;', $post['body']);
		}

		showUserPage('Edit post', $config['file_mod_edit_post_form'], ['token' => $security_token, 'board' => $board, 'raw' => $edit_raw_html, 'post' => $post]);
	}
}

function mod_quickpostmanip($board, $id, $config, $message, $delete) {
	// Get post
	$query = prepare(sprintf('SELECT * FROM ``posts_%s`` WHERE `id` = :id', $board));
	$query->bindValue(':id', $id);
	$query->execute() or error(db_error($query));
	if(!$post = $query->fetch(PDO::FETCH_ASSOC)) { error($config['error']['404']); }

	// Manipulate post
	if($delete) { deletePost($id); }
	modLog($message);

	// Rebuild thread/board.
	if(!$delete) { buildThreadFilesFunction($post['thread'] ? $post['thread'] : $id, true); }

	// Redirect
	header('Location: ?/' . sprintf($config['board_path'], $board) . $config['file_index'], true, $config['redirect_http']);
}

function mod_delete(Context $ctx, $tg_board, $post) {
	global $kara_user, $board;

	$config = $ctx->get('config');
	if(!openBoard($tg_board)) { error($config['error']['noboard']); }

	// Needed for userboard self modding.
	if($board['owner'] != $kara_user['id']) {
		if(!hasPerm('delete')) { error($config['error']['noaccess']); }
	}

	// Delete post
	mod_quickpostmanip($tg_board, $post, $config, "Deleted post #{$post}", true);
}

function mod_deletefile(Context $ctx, $board, $post, $file) {
	$config = $ctx->get('config');
	if(!hasPerm('deletefile')) { error($config['error']['noaccess']); }
	if(!openBoard($board)) { error($config['error']['noboard']); }

	// Delete file
	deleteFile($post, TRUE, $file);
	mod_quickpostmanip($board, $post, $config, "Deleted file from post #{$post}", false);
}

function mod_spoiler_image(Context $ctx, $board, $post) {
	$config = $ctx->get('config');
	if(!hasPerm('spoilerimage')) { error($config['error']['noaccess']); }
	if(!openBoard($board)) { error($config['error']['noboard']); }

	// Make thumbnail spoiler
	$query = prepare(sprintf("UPDATE ``posts_%s`` SET `imagespoilered` = imagespoilered ^ 1 WHERE `id` = :id", $board));
	$query->bindValue(':id', $post, PDO::PARAM_INT);
	$query->execute() or error(db_error($query));
	mod_quickpostmanip($board, $post, $config, "Toggled spoilers on file from post #{$post}", false);
}

function mod_approve(Context $ctx, $board, $post) {
	$config = $ctx->get('config');
	if(!hasPerm('approvecontent')) { error($config['error']['noaccess']); }
	if(!openBoard($board)) { error($config['error']['noboard']); }

	// Approve file
	$query = prepare(sprintf("UPDATE ``posts_%s`` SET `pendingapproval` = 0 WHERE `id` = :id", $board));
	$query->bindValue(':id', $post, PDO::PARAM_INT);
	$query->execute() or error(db_error($query));
	mod_quickpostmanip($board, $post, $config, "Approved post #{$post}", false);
}

function mod_togglemanipthread($perm, $field, $board, $post, $config, $message_yes, $message_no, $toggle) {
	if(!hasPerm($perm)) { error($config['error']['noaccess']); }
	if(!openBoard($board)) { error($config['error']['noboard']); }

	// Perform an action
	$query = prepare(sprintf('UPDATE ``posts_%s`` SET `' . $field . '` = :' . $field . ' WHERE `id` = :id AND `thread` IS NULL', $board));
	$query->bindValue(':id', $post);
	$query->bindValue(':' . $field, $toggle ? 0 : 1);
	$query->execute() or error(db_error($query));
	if($query->rowCount()) {
		mod_quickpostmanip($board, $post, $config, ($toggle ? $message_yes : $message_no) . " thread #{$post}", false);
	} else {
		error($config['error']['nonexistant']);
	}
}

function mod_lock(Context $ctx, $board, $unlock, $post) { mod_togglemanipthread('lock', 'locked', $board, $post, $ctx->get('config'), 'Unlocked', 'Locked', $unlock); }
function mod_sticky(Context $ctx, $board, $unsticky, $post) { mod_togglemanipthread('sticky', 'sticky', $board, $post, $ctx->get('config'), 'Unstickied', 'Stickied', $unsticky); }
function mod_cycle(Context $ctx, $board, $uncycle, $post) { mod_togglemanipthread('cycle', 'cycle', $board, $post, $ctx->get('config'), 'Uncycled', 'Cycled', $uncycle); }
function mod_bumplock(Context $ctx, $board, $unbumplock, $post) { mod_togglemanipthread('bumplock', 'pamped', $board, $post, $ctx->get('config'), 'Unbumplocked', 'Bumplocked', $unbumplock); }

function mod_pending(Context $ctx) {
	$config = $ctx->get('config');
	if(!hasPerm('approvecontent')) { error($config['error']['noaccess']); }
	$count = 0;
	$body = '';
	$boards = getAllBoardByURIs();

	// Manually build an SQL query
	foreach($boards as $board) {
		$table = sprintf("posts_%s", $board['uri']);
		$query = prepare(sprintf("SELECT * FROM `%s` WHERE `pendingapproval` > 0", $table));
		$query->execute() or error(db_error($query));
		while($post = $query->fetch(PDO::FETCH_ASSOC)) {
			$body .= '<a href="/' . $config['file_account'] . '?/' . $board['uri'] . '/' . getThreadFileLink($post) . (!$post['op'] ? '#' . $id : '') . '">Pending post in /' . $board['uri'] . '/</a><br>';
		}
	}

	showUserPage('Pending media queue', $config['file_mod_pending'], ['pendingfiles' => $body]);
}

function mod_deletebyip(Context $ctx, $boardName, $post, $global = false) {
	global $board;
	$config = $ctx->get('config');

	$global = (bool)$global;

	if(!openBoard($boardName)) { error($config['error']['noboard']); }
	if(!$global && !hasPerm('deletebyip')) { error($config['error']['noaccess']); }

	if($global && !hasPerm('deletebyip_global')) { error($config['error']['noaccess']); }

	// Find IP address
	$query = prepare(sprintf('SELECT `ip` FROM ``posts_%s`` WHERE `id` = :id', $boardName));
	$query->bindValue(':id', $post);
	$query->execute() or error(db_error($query));
	if(!$ip = $query->fetchColumn())
		error($config['error']['invalidpost']);

	$boards = $global ? getAllBoardByURIs() : array(array('uri' => $boardName));

	$query = '';
	foreach($boards as $_board) {
		$query .= sprintf("SELECT `thread`, `id`, '%s' AS `board` FROM ``posts_%s`` WHERE `ip` = :ip UNION ALL ", $_board['uri'], $_board['uri']);
	}
	$query = preg_replace('/UNION ALL $/', '', $query);

	$query = prepare($query);
	$query->bindValue(':ip', $ip);
	$query->execute() or error(db_error($query));

	if($query->rowCount() < 1) { error($config['error']['invalidpost']); }

	$threads_to_rebuild = [];
	$threads_deleted = [];
	while($post = $query->fetch(PDO::FETCH_ASSOC)) {
		openBoard($post['board']);
		deletePost($post['id'], false, false);
		buildBoardIndexAndPages(false);
		if($post['thread']) { $threads_to_rebuild[$post['board']][$post['thread']] = true; }
		else { $threads_deleted[$post['board']][$post['id']] = true; } 
	}
	foreach($threads_to_rebuild as $_board => $_threads) {
		openBoard($_board);
		foreach($_threads as $_thread => $_dummy) {
			if($_dummy && !isset($threads_deleted[$_board][$_thread])) { buildThreadFilesFunction($_thread); }
		}
		buildBoardIndexAndPages(false);
	}
	generateKCIndex();
	if($global) { $board = false; }

	// Record the action
	$cip = cloak_ip($ip);
	modLog("Deleted all posts by IP address: <a href=\"?/IP/$cip\">$cip</a>");

	// Redirect
	header('Location: ?/' . sprintf($config['board_path'], $boardName) . $config['file_index'], true, $config['redirect_http']);
}

function mod_user(Context $ctx, $uid) {
	global $kara_user;
	$config = $ctx->get('config');

	if(!hasPerm('editusers') && !(hasPerm('change_password') && $uid == $kara_user['id'])) { error($config['error']['noaccess']); }

	$query = prepare('SELECT * FROM ``karausers`` WHERE `id` = :id');
	$query->bindValue(':id', $uid);
	$query->execute() or error(db_error($query));
	if(!$user = $query->fetch(PDO::FETCH_ASSOC)) { error($config['error']['404']); }

	$active_perms = [];
	foreach($config['permissions'] as $name => $flag) {
		if($user['permissions'] & $flag) {
			$active_perms[$name] = true;
		}
	}

	if(hasPerm('editusers') && isset($_POST['username'])) {
		if(isset($_POST['delete'])) {
			if(!hasPerm('deleteusers')) { error($config['error']['noaccess']); }
			$query = prepare('DELETE FROM ``karausers`` WHERE `id` = :id');
			$query->bindValue(':id', $uid);
			$query->execute() or error(db_error($query));
			modLog('Deleted user ' . utf8tohtml($user['username']) . ' <small>(#' . $user['id'] . ')</small>');
			header('Location: ?/users', true, $config['redirect_http']);
			return;
		}
		if($_POST['username'] == '') { error(sprintf($config['error']['required'], 'username')); }

		$set_permissions = collect_permissions_from_request($uid == $kara_user['id'] ? $config['permissions']['editusers'] : 0);
		if($set_permissions != $user['permissions']) { modLog('Modified permissions of user "' . utf8tohtml($user['username']) . '" <small>(#' . $user['id'] . ')</small>'); }

		$query = prepare('UPDATE ``karausers`` SET `username` = :username, `permissions` = :permissions WHERE `id` = :id');
		$query->bindValue(':id', $uid);
		$query->bindValue(':permissions', $set_permissions);
		$query->bindValue(':username', $_POST['username']);
		$query->execute() or error(db_error($query));

		if($user['username'] !== $_POST['username']) {
			// account was renamed
			modLog('Renamed user "' . utf8tohtml($user['username']) . '" <small>(#' . $user['id'] . ')</small> to "' . utf8tohtml($_POST['username']) . '"');
		}

		if($_POST['password'] != '') {
			list($version, $password) = crypt_password($_POST['password']);

			$query = prepare('UPDATE ``karausers`` SET `password` = :password, `version` = :version WHERE `id` = :id');
			$query->bindValue(':id', $uid);
			$query->bindValue(':password', $password);
			$query->bindValue(':version', $version);
			$query->execute() or error(db_error($query));

			modLog('Changed password for ' . utf8tohtml($_POST['username']) . ' <small>(#' . $user['id'] . ')</small>');

			if($uid == $kara_user['id']) {
				login($_POST['username'], $_POST['password']);
				setCookies();
			}
		}

		if(hasPerm('editusers')) { header('Location: ?/users', true, $config['redirect_http']); }
		else { header('Location: ?/', true, $config['redirect_http']); }

		return;
	}

	if(hasPerm('change_password') && $uid == $kara_user['id'] && isset($_POST['password'])) {
		if($_POST['password'] != '') {
			list($version, $password) = crypt_password($_POST['password']);

			$query = prepare('UPDATE ``karausers`` SET `password` = :password, `version` = :version WHERE `id` = :id');
			$query->bindValue(':id', $uid);
			$query->bindValue(':password', $password);
			$query->bindValue(':version', $version);
			$query->execute() or error(db_error($query));

			modLog('Changed own password');

			login($user['username'], $_POST['password']);
			setCookies();
		}
		header('Location: ?/', true, $config['redirect_http']);
		return;
	}

	if(hasPerm('modlog')) {
		$query = prepare('SELECT * FROM ``modlogs`` WHERE `mod` = :id ORDER BY `time` DESC LIMIT 5');
		$query->bindValue(':id', $uid);
		$query->execute() or error(db_error($query));
		$log = $query->fetchAll(PDO::FETCH_ASSOC);
	} else {
		$log = [];
	}

	$user['boards'] = explode(',', $user['boards']);

	showUserPage('Edit user', $config['file_mod_user'], ['user' => $user, 'user_active' => $active_perms, 'logs' => $log, 'boards' => getAllBoardByURIs(), 'token' => make_secure_link_token('users/' . $user['id'])]);
}

function mod_user_new(Context $ctx) {
	global $pdo, $config;
	if(!hasPerm('createusers')) { error($config['error']['noaccess']); }
	if(isset($_POST['username'], $_POST['password'])) {
		if($_POST['username'] == '') { error(sprintf($config['error']['required'], 'username')); }
		if($_POST['password'] == '') { error(sprintf($config['error']['required'], 'password')); }
		add_new_user_quick($_POST['username'], $_POST['password'], collect_permissions_from_request(0));
		header('Location: ?/users', true, $config['redirect_http']);
		return;
	}
	showUserPage('New user', $config['file_mod_user'], ['new' => true, 'boards' => getAllBoardByURIs(), 'token' => make_secure_link_token('users/new')]);
}


function mod_users(Context $ctx) {
	$config = $ctx->get('config');
	if(!hasPerm('editusers')) { error($config['error']['noaccess']); }
	$query = query("SELECT
		*,
		(SELECT `time` FROM ``modlogs`` WHERE `mod` = `id` ORDER BY `time` DESC LIMIT 1) AS `last`,
		(SELECT `text` FROM ``modlogs`` WHERE `mod` = `id` ORDER BY `time` DESC LIMIT 1) AS `action`
		FROM ``karausers`` ORDER BY `id` DESC,`id`") or error(db_error());
	$users = $query->fetchAll(PDO::FETCH_ASSOC);
	foreach($users as &$user) {
		$user['promote_token'] = make_secure_link_token("users/{$user['id']}/promote");
		$user['demote_token'] = make_secure_link_token("users/{$user['id']}/demote");
	}
	showUserPage(sprintf('%s (%d)', 'Manage users', count($users)), $config['file_mod_users'], [ 'users' => $users ]);
}

function mod_rebuild(Context $ctx) {
	$config = $ctx->get('config');

	if(!hasPerm('rebuild')) { error($config['error']['noaccess']); }

	if(isset($_POST['rebuild'])) {
		showUserPage('Rebuild', $config['file_mod_rebuilt'], [ 'logs' => karachan_rebuild_pages(
			isset($_POST['rebuild_cache']),
			isset($_POST['rebuild_javascript']),
			isset($_POST['rebuild_index']),
			isset($_POST['rebuild_thread']),
			isset($_POST['boards_all'])
		) ]);
		return;
	}
	showUserPage('Rebuild', $config['file_mod_rebuild'], ['boards' => getAllBoardByURIs(), 'token' => make_secure_link_token('rebuild')]);
}

function mod_reports(Context $ctx) {
	global $kara_user;
	$config = $ctx->get('config');

	if(!hasPerm('reports')) { error($config['error']['noaccess']); }

	$query = prepare("SELECT * FROM ``reports`` ORDER BY `time` DESC LIMIT :limit");
	$query->bindValue(':limit', $config['mod']['recent_reports'], PDO::PARAM_INT);
	$query->execute() or error(db_error($query));
	$reports = $query->fetchAll(PDO::FETCH_ASSOC);

	$report_queries = [];
	foreach($reports as $report) {
		if(!isset($report_queries[$report['board']]))
			$report_queries[$report['board']] = [];
		$report_queries[$report['board']][] = $report['post'];
	}

	$report_posts = [];
	foreach($report_queries as $board => $posts) {
		$report_posts[$board] = [];

		$query = query(sprintf('SELECT * FROM ``posts_%s`` WHERE `id` = ' . implode(' OR `id` = ', $posts), $board)) or error(db_error());
		while($post = $query->fetch(PDO::FETCH_ASSOC)) {
			$report_posts[$board][$post['id']] = $post;
		}
	}

	$count = 0;
	$body = '';
	foreach($reports as $report) {
		if(!isset($report_posts[$report['board']][$report['post']])) {
			// // Invalid report (post has since been deleted)
			$query = prepare("DELETE FROM ``reports`` WHERE `post` = :id AND `board` = :board");
			$query->bindValue(':id', $report['post'], PDO::PARAM_INT);
			$query->bindValue(':board', $report['board']);
			$query->execute() or error(db_error($query));
			continue;
		}

		openBoard($report['board']);

		$post = &$report_posts[$report['board']][$report['post']];

		if(!$post['thread']) {
			// Still need to fix this:
			$po = new Thread($post, '?/', $kara_user, false);
		} else {
			$po = new Post($post, '?/', $kara_user);
		}

		// a little messy and inefficient
		$append_html = Element($config['file_mod_report'], array(
			'report' => $report,
			'config' => $config,
			'karadiap' => $kara_user,
			'token' => make_secure_link_token('reports/' . $report['id'] . '/dismiss'),
			'token_all' => make_secure_link_token('reports/' . $report['id'] . '/dismiss&all'),
			'token_post' => make_secure_link_token('reports/'. $report['id'] . '/dismiss&post'),
		));

		// Bug fix
		$po->body = truncate($po->body, $po->link(), $config['body_truncate'] - substr_count($append_html, '<br>'));

		if(mb_strlen($po->body) + mb_strlen($append_html) > $config['body_truncate_char']) {
			// still too long; temporarily increase limit in the config
			$__old_body_truncate_char = $config['body_truncate_char'];
			$config['body_truncate_char'] = mb_strlen($po->body) + mb_strlen($append_html);
		}

		$po->body .= $append_html;

		$body .= $po->build(true) . '<hr>';

		if(isset($__old_body_truncate_char))
			$config['body_truncate_char'] = $__old_body_truncate_char;

		$count++;
	}

	showUserPage(
		sprintf('%s (%d)', 'Report queue', $count),
		$config['file_mod_reports'],
		[
			'reports' => $body,
			'count' => $count
		]
	);
}

function mod_report_dismiss(Context $ctx, $id, $action) {
	$config = $ctx->get('config');

	$query = prepare("SELECT `post`, `board`, `ip` FROM ``reports`` WHERE `id` = :id");
	$query->bindValue(':id', $id);
	$query->execute() or error(db_error($query));
	if($report = $query->fetch(PDO::FETCH_ASSOC)) {
		$ip = $report['ip'];
		$board = $report['board'];
		$post = $report['post'];
	} else
		error($config['error']['404']);

	switch($action){
		case '&post':
			if(!hasPerm('report_dismiss_post'))
				error($config['error']['noaccess']);

			$query = prepare("DELETE FROM ``reports`` WHERE `post` = :post");
			$query->bindValue(':post', $post);
			modLog("Dismissed all reports for post #{$id}", $board);
			break;
		case '&all':
			if(!hasPerm('report_dismiss_ip'))
				error($config['error']['noaccess']);

			$query = prepare("DELETE FROM ``reports`` WHERE `ip` = :ip");
			$query->bindValue(':ip', $ip);
			$cip = cloak_ip($ip);
			modLog("Dismissed all reports by <a href=\"?/IP/$cip\">$cip</a>");
			break;
		case '':
		default:
			if(!hasPerm('report_dismiss'))
				error($config['error']['noaccess']);

			$query = prepare("DELETE FROM ``reports`` WHERE `id` = :id");
			$query->bindValue(':id', $id);
			modLog("Dismissed a report for post #{$id}", $board);
			break;
	}
	$query->execute() or error(db_error($query));

	header('Location: ?/reports', true, $config['redirect_http']);
}