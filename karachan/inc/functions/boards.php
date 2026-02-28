<?php

// Get all board URIs.
function getAllBoardByURIs() {
	global $config;
	if ($config['cache']['enabled'] && ($boards = cache::get('boarduricache'))) { return $boards; }
	$query = query("SELECT * FROM ``boards`` ORDER BY `uri`") or error(db_error());
	$boards = $query->fetchAll();
	if ($config['cache']['enabled']) { cache::set('boarduricache', $boards); }
	return $boards;
}

// Get board information.
function getBoardInfo($uri) {
	global $config;
	if ($config['cache']['enabled'] && ($board = cache::get('board_' . $uri))) { return $board; }
	$query = prepare("SELECT * FROM ``boards`` WHERE `uri` = :uri LIMIT 1");
	$query->bindValue(':uri', $uri);
	$query->execute() or error(db_error($query));
	if ($board = $query->fetch(PDO::FETCH_ASSOC)) {
		if ($config['cache']['enabled']) { cache::set('board_' . $uri, $board); }
		return $board;
	}
	return false;
}

// Get userboard
function findUserboard($id) {
	global $config;
	if ($config['cache']['enabled'] && ($board = cache::get('userboard_' . $id))) { return $board; }
	$query = prepare("SELECT * FROM ``boards`` WHERE `owner` = :owner LIMIT 1");
	$query->bindValue(':owner', $id);
	$query->execute() or error(db_error($query));
	if ($board = $query->fetch(PDO::FETCH_ASSOC)) {
		if ($config['cache']['enabled']) { cache::set('userboard_' . $id, $board); }
		return $board;
	}
	return false;
}

// Open board directory.
function openBoard($uri) {
	global $config, $build_pages, $board;
	if ($config['try_smarter']) { $build_pages = array(); }
	if (isset ($board) && isset ($board['uri']) && $board['uri'] == $uri) { return true; } // And what if we don't really need to change a board we have opened?
	$board = getBoardInfo($uri);
	if ($board) {
		$board['dir'] = sprintf($config['board_path'], $board['uri']);
		$board['url'] = sprintf($config['board_abbreviation'], $board['uri']);
		loadConfig();
		if(!in_array($board['uri'], $config['private_boards'])) {
			if(!file_exists($board['dir'])) { @mkdir($board['dir'], 0777) or error("Couldn't create " . $board['dir'] . ". Check permissions.", true); }
		}
		return true;
	}
	$board = null;
	return false;
}

function openBoardSafe($uri) {
	global $config, $kara_user, $board;
	if(openBoard($uri)) {
		if(!isset($kara_user) && in_array($board['uri'], $config['private_boards'])) { error("This board is private."); }
		if(!hasPerm('postinlocked') && $config['board_locked']) { error("Board is locked"); }
		return true;
	}
	return false;
}

// $brief means that we won't need to generate anything yet
function index($page, $kara_user=false, $brief = false) {
	global $board, $config, $debug;

	$body = '';
	$offset = round($page*$config['threads_per_page']-$config['threads_per_page']);

	$query = prepare(sprintf("SELECT * FROM ``posts_%s`` WHERE `thread` IS NULL ORDER BY `sticky` DESC, `bump` DESC LIMIT :offset,:threads_per_page", $board['uri']));
	$query->bindValue(':offset', $offset, PDO::PARAM_INT);
	$query->bindValue(':threads_per_page', $config['threads_per_page'], PDO::PARAM_INT);
	$query->execute() or error(db_error($query));

	if ($page == 1 && $query->rowCount() < $config['threads_per_page']) { $board['thread_count'] = $query->rowCount(); }
	if ($query->rowCount() < 1 && $page > 1) { return false; }

	$threads = array();

	while ($th = $query->fetch(PDO::FETCH_ASSOC)) {
		$thread = new Thread($th, $kara_user ? '?/' : '/', $kara_user);

		if ($config['cache']['enabled']) {
			$cached = cache::get("thread_index_{$board['uri']}_{$th['id']}");
			if (isset($cached['replies'], $cached['omitted'])) {
				$replies = $cached['replies'];
				$omitted = $cached['omitted'];
			} else {
				unset($cached);
			}
		}

		if (!isset($cached)) {
			$posts = prepare(sprintf("SELECT * FROM ``posts_%s`` WHERE `thread` = :id ORDER BY `id` DESC LIMIT :limit", $board['uri']));
			$posts->bindValue(':id', $th['id']);
			$posts->bindValue(':limit', ($th['sticky'] ? $config['threads_preview_sticky'] : $config['threads_preview']), PDO::PARAM_INT);
			$posts->execute() or error(db_error($posts));

			$replies = array_reverse($posts->fetchAll(PDO::FETCH_ASSOC));

			if (count($replies) == ($th['sticky'] ? $config['threads_preview_sticky'] : $config['threads_preview'])) {
				$count = numPosts($th['id']);
				$omitted = array('post_count' => $count['replies'], 'image_count' => $count['images']);
			} else {
				$omitted = false;
			}

			if ($config['cache']['enabled'])
				cache::set("thread_index_{$board['uri']}_{$th['id']}", array(
					'replies' => $replies,
					'omitted' => $omitted,
				));
		}

		$num_images = 0;
		foreach ($replies as $po) {
			if ($po['num_files']) { $num_images+=$po['num_files']; }
			$thread->add(new Post($po, $kara_user ? '?/' : '/', $kara_user));
		}

		$thread->images = $num_images;
		$thread->replies = isset($omitted['post_count']) ? $omitted['post_count'] : count($replies);

		if ($omitted) {
			$thread->omitted = $omitted['post_count'] - ($th['sticky'] ? $config['threads_preview_sticky'] : $config['threads_preview']);
			$thread->omitted_images = $omitted['image_count'] - $num_images;
		}
		$threads[] = $thread;
		if (!$brief) { $body .= $thread->build(true); }
	}

	return array(
		'board' => $board,
		'body' => $body,
		'config' => $config,
		'threads' => $threads,
	);
}

function getPageButtonLink($rt, $n) {
	global $config;
	return $rt . ($n == 1 ? $config['file_index'] : sprintf($config['file_boardpage'], $n)); 
}

function getPageButtons($pages, $kara_user=false) {
	global $config, $board;

	$btn = array();
	$bPage = ($kara_user ? '?/' : '/') . $board['dir'];
	foreach ($pages as $num => $page) {
		if (isset($page['selected'])) {
			// Previous button
			if ($num == 0) {
				// There is no previous page.
				$btn['prev'] = 'Previous';
			} else {
				$loc = getPageButtonLink($bPage, $num);
				$btn['prev'] = '<form action="' . $loc . '" method="get">' . ($kara_user ?
						'<input type="hidden" name="status" value="301" />' .
						'<input type="hidden" name="r" value="' . htmlentities($loc) . '" />'
					:'') . '<input type="submit" value="Previous"/></form>';
			}

			if ($num == count($pages) - 1) {
				// There is no next page.
				$btn['next'] = 'Next';
			} else {
				$loc = getPageButtonLink($bPage, $num + 2);
				$btn['next'] = '<form action="' . $loc . '" method="get">' . ($kara_user ?
						'<input type="hidden" name="status" value="301" />' .
						'<input type="hidden" name="r" value="' . htmlentities($loc) . '" />'
					:'') . '<input type="submit" value="Next"/></form>';
			}
		}
	}

	return $btn;
}

function getPages($kara_user=false) {
	global $board, $config;

	if (isset($board['thread_count'])) {
		$count = $board['thread_count'];
	} else {
		// Count threads
		$query = query(sprintf("SELECT COUNT(*) FROM ``posts_%s`` WHERE `thread` IS NULL", $board['uri'])) or error(db_error());
		$count = $query->fetchColumn();
	}
	$count = floor(($config['threads_per_page'] + $count - 1) / $config['threads_per_page']);

	if ($count < 1) { $count = 1; }

	$pages = array();
	for ($x=0;$x<$count && $x<$config['max_pages'];$x++) {
		$pages[] = array(
			'num' => $x+1,
			'link' => getPageButtonLink(($kara_user ? '?/' : '/') . $board['dir'], $x+1)
		);
	}

	return $pages;
}

// wipes a board directory
function wipe_board_directory($uri) {
	global $config;
	$board_directory = sprintf($config['board_path'], $uri);
	if(file_exists($board_directory)) { // wipe the dir if it exists
		$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($board_directory, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
		foreach($files as $file) { if($file->isDir()) { rmdir($file->getRealPath()); } else { unlink($file->getRealPath()); } }
		rmdir($board_directory);
	}
}

// create a new board
function add_new_board_quick($newuri, $boardtitle, $subtitle, $ownerid = 0) {
	global $kara_user, $board, $config;

	$bytes = 0;
	$chars = preg_split('//u', $newuri, -1, PREG_SPLIT_NO_EMPTY);
	foreach ($chars as $char) {
		$o = 0;
		$ord = ordutf8($char, $o);
		if ($ord > 0x0080) { $bytes += 5; } // @01ff
		else { $bytes ++; }
	}
	$bytes += strlen('posts_.frm');
	if ($bytes > 255) { error('Your filesystem cannot handle a board URI of that length (' . $bytes . '/255 bytes)'); exit; }
	if (openBoard($newuri)) { return '<li>' . sprintf($config['error']['boardexists'], $newuri) . '</li>'; }

	// board preparation
	$query = prepare('INSERT INTO ``boards`` VALUES (:uri, :title, :subtitle, :owner)');
	$query->bindValue(':uri', $newuri);
	$query->bindValue(':title', $boardtitle);
	$query->bindValue(':subtitle', $subtitle);
	$query->bindValue(':owner', $ownerid);
	$query->execute() or error(db_error($query));
	if (!openBoard($newuri)) { error('Couldnt open board after creation.'); }

	// posts preparation
	$query = Element('posts.sql', [ 'board' => $board['uri'] ]);
	query($query) or error(db_error());

	modLog('Created a new board: ' . sprintf($config['board_abbreviation'], $newuri));

	cache::delete('boarduricache'); // clear cache
	buildBoardIndexAndPages(true); // build the board
	openBoard($newuri);
	return '<li>Board created: ' . sprintf($config['board_abbreviation'], $newuri) . '</li>';
}

// delete a existing board
function delete_board_quick($uri) {
	global $kara_user, $board, $config;

	if(!openBoard($uri)) { error($config['error']['noboard']); }

	$query = prepare('DELETE FROM ``boards`` WHERE `uri` = :uri');
	$query->bindValue(':uri', $board['uri']);
	$query->execute() or error(db_error($query));

	cache::delete('board_' . $board['uri']);
	cache::delete('userboard_' . $board['owner']);
	cache::delete('boarduricache');

	modLog('Deleted board: ' . sprintf($config['board_abbreviation'], $board['uri']), false);

	// Delete posting table
	$query = query(sprintf('DROP TABLE IF EXISTS ``posts_%s``', $board['uri'])) or error(db_error());

	// Clear reports
	$query = prepare('DELETE FROM ``reports`` WHERE `board` = :id');
	$query->bindValue(':id', $board['uri'], PDO::PARAM_STR);
	$query->execute() or error(db_error($query));

	// Delete from table
	$query = prepare('DELETE FROM ``boards`` WHERE `uri` = :uri');
	$query->bindValue(':uri', $board['uri'], PDO::PARAM_STR);
	$query->execute() or error(db_error($query));

	$query = prepare("SELECT `board`, `post` FROM ``cites`` WHERE `target_board` = :board ORDER BY `board`");
	$query->bindValue(':board', $board['uri']);
	$query->execute() or error(db_error($query));
	while($cite = $query->fetch(PDO::FETCH_ASSOC)) {
		if($board['uri'] != $cite['board']) {
			if(!isset($tmp_board)) { $tmp_board = $board; }
			openBoard($cite['board']);
			rebuildPost($cite['post']);
		}
	}
	if(isset($tmp_board)) { $board = $tmp_board; }

	$query = prepare('DELETE FROM ``cites`` WHERE `board` = :board OR `target_board` = :board');
	$query->bindValue(':board', $board['uri']);
	$query->execute() or error(db_error($query));

	// Delete entire board directory
	wipe_board_directory($uri);
}