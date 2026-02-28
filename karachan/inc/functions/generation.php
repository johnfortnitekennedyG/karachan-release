<?php

use MatthiasMullie\Minify;

// Generate code
function buildScripts() {
	global $config;

	// Generate javascript code
	$stylesheets = array();
	foreach($config['stylesheets'] as $name => $uri) {
		$stylesheets[] = array('name' => addslashes($name), 'uri' => addslashes((!empty($uri) ? '/assets/' : '') . $uri));
	}
	$script = Element('main.js', array('config' => $config, 'stylesheets' => $stylesheets));
	foreach($config['additional_javascript'] as $file) { $script .= file_get_contents($file); }

	// Generate css code
	$stylesheet = file_get_contents('assets/style.css') . "\n" . file_get_contents('assets/fam.css') . "\n" .
					file_get_contents('assets/flags.css') . "\n" . file_get_contents('assets/highlight.css');
	
	// Save the processed stuff.
	if($config['minify_assets']) {
		$minifier = new Minify\CSS();
		$minifier->add($stylesheet);
		$minifier->minify($config['file_stylesheet']);
		
		$minifier = new Minify\JS();
		$minifier->add($script);
		$minifier->minify($config['file_script']);
	} else {
		file_write($config['file_stylesheet'], $stylesheet);
		file_write($config['file_script'], $script);
	}
}

// Generate global index, for new posts and stuff.
function generateKCIndex() {
	global $config, $board;
	
	$recent_posts = Array();
	$stats = Array();
	
	$boards = getAllBoardByURIs();
	
	$query = '';
	foreach ($boards as &$_board) {
		if(in_array($_board['uri'], $config['private_boards'])) { continue; }
		$query .= sprintf("SELECT *, '%s' AS `board` FROM ``posts_%s`` UNION ALL ", $_board['uri'], $_board['uri']);
	}
	if($query != ''){
		$query = preg_replace('/UNION ALL $/', 'ORDER BY `time` DESC LIMIT ' . (int)$config['homepage_maxposts'], $query);
		$query = query($query) or error(db_error());
		while ($post = $query->fetch(PDO::FETCH_ASSOC)) {
			if($post['pendingapproval'] < 2) {
				openBoard($post['board']);
				$post['link'] = $board['dir'] . getThreadFileLink($post) . '#' . $post['id'];
				if ($post['body'] != "") { $post['snippet'] = message_snippet($post['body'], 30); }
				else { $post['snippet'] = "<em>" . '(no comment)' . "</em>"; }
				$post['board_name'] = $board['title'];
				$recent_posts[] = $post;
			}
		}

		// Total posts
		$query = 'SELECT SUM(`top`) FROM (';
		foreach ($boards as &$_board) {
			if(in_array($_board['uri'], $config['private_boards'])) { continue; }
			$query .= sprintf("SELECT MAX(`id`) AS `top` FROM ``posts_%s`` UNION ALL ", $_board['uri']);
		}
		$query = preg_replace('/UNION ALL $/', ') AS `posts_all`', $query);
		$query = query($query) or error(db_error());
		$stats['total_posts'] = number_format($query->fetchColumn());

		// Unique IPs
		$query = 'SELECT COUNT(DISTINCT(`ip`)) FROM (';
		foreach ($boards as &$_board) {
			if(in_array($_board['uri'], $config['private_boards'])) { continue; }
			$query .= sprintf("SELECT `ip` FROM ``posts_%s`` UNION ALL ", $_board['uri']);
		}
		$query = preg_replace('/UNION ALL $/', ') AS `posts_all`', $query);
		$query = query($query) or error(db_error());
		$stats['unique_posters'] = number_format($query->fetchColumn());
	} else {
		$stats['total_posts'] = number_format(0);
		$stats['unique_posters'] = number_format(0);
	}
	
	// Active content
	$size = 0;
	foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator("stnk", FilesystemIterator::SKIP_DOTS)) as $file) { $size += $file->getSize(); }
	foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator("krth", FilesystemIterator::SKIP_DOTS)) as $file) { $size += $file->getSize(); }
	$stats['active_content'] = $size;
	
	//news entries
	$query = query("SELECT * FROM ``news`` ORDER BY `time` DESC" . ($config['homepage_maxnews'] ? ' LIMIT ' . $config['homepage_maxnews'] : '')) or error(db_error());
	$news = $query->fetchAll(PDO::FETCH_ASSOC);

	// Excluded boards for the boardlist
	$boardlist = array_filter($boards, function($board) use ($config) { return !in_array($board['uri'], $config['private_boards']); });
	file_write("index.html", Element('homepage.html', Array(
		'config' => $config,
		'recent_posts' => $recent_posts,
		'stats' => $stats,
		'news' => $news,
		'boards' => $boardlist
	)));
}

// Generate catalog
function generateBoardCatalog() {
	global $board, $config, $kara_user;

	$recent_posts = array();
	$stats = array();
	$query = query(sprintf("SELECT *, `id` AS `thread_id`, (SELECT COUNT(`id`) FROM ``posts_%s`` WHERE `thread` = `thread_id`) AS `reply_count`, (SELECT SUM(`num_files`) FROM ``posts_%s`` WHERE `thread` = `thread_id` AND `num_files` IS NOT NULL) AS `image_count`, '%s' AS `board` FROM ``posts_%s`` WHERE `thread`  IS NULL ORDER BY `bump` DESC",
	$board['uri'], $board['uri'], $board['uri'], $board['uri'], $board['uri'])) or error(db_error());

	while ($post = $query->fetch(PDO::FETCH_ASSOC)) {
		if($kara_user) { $post['link'] = $config['file_account'] . '?/'. $board['dir'] . getThreadFileLink($post); }
		else { $post['link'] = $board['dir'] . getThreadFileLink($post); }
		$post['board_name'] = $board['title'];

		if ($post['embed'] && preg_match('/^https?:\/\/(\w+\.)?(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9\-_]{10,11})(&.+)?$/i', $post['embed'], $matches)) {
			$post['youtube'] = $matches[2];
		}

		$post['file'] = $post['imagespoilered'] ? $config['spoiler_image'] : $config['image_deleted'];
		if(isset($post['files']) && $post['files'] && !$post['imagespoilered']) {
			$post['file'] = $config['image_banana'];
			$files = json_decode($post['files']);
			foreach($files as $file) {
				$thumb = 'krth/' . pathinfo($file->file, PATHINFO_FILENAME) . '.jpg';
				if(file_exists($thumb)) { $post['file'] = $thumb; break; }
			}
		}

		if(empty($post['image_count'])) { $post['image_count'] = 0; }
		$post['pubdate'] = date('r', $post['time']);
		$recent_posts[] = $post;
	}

	$link = $kara_user ? $config['file_account'] . '?/' . $board['dir'] : $board['dir'];
	return Element($config['file_catalog'], Array(
		'config' => $config,
		'recent_posts' => $recent_posts,
		'stats' => $stats,
		'board' => $board,
		'link' => $link,
		'karadiap' => $kara_user
	));
}

// Build the index and catalog of the pages. Board should be valid.
function buildBoardIndexAndPages($buildhomepage) {
	global $board, $config, $build_pages, $kara_user;
	if(!in_array($board['uri'], $config['private_boards'])) {
		$pages = null;

		// Try to build most pages.
		for ($page = 1; $page <= $config['max_pages']; $page++) {
			$filename = $board['dir'] . ($page == 1 ? $config['file_index'] : sprintf($config['file_boardpage'], $page));
			$wont_build_this_page = $config['try_smarter'] && isset($build_pages) && !empty($build_pages) && !in_array($page, $build_pages);
			if($wont_build_this_page) { continue; }

			$content = index($page, false, $wont_build_this_page);
			if(!$content) { break; }

			// Tries to avoid rebuilding if the body is the same as the one in cache.
			if($config['cache']['enabled']) {
				$contentHash = md5(json_encode($content['body']));
				$contentHashKey = '_index_hashed_'. $board['uri'] . '_' . $page;
				$cachedHash = cache::get($contentHashKey);
				if($cachedHash == $contentHash) { continue; }
				cache::set($contentHashKey, $contentHash, 3600);
			}

			if(!$pages) { $pages = getPages(); }
			$content['pages'] = $pages;
			$content['pages'][$page-1]['selected'] = true;
			$content['btn'] = getPageButtons($content['pages']);
			file_write($filename, Element($config['file_board_index'], $content));
		}

		// Clear leftover pages.
		if($page < $config['max_pages']) {
			for (;$page<=$config['max_pages'];$page++) {
				$filename = $board['dir'] . ($page == 1 ? $config['file_index'] : sprintf($config['file_boardpage'], $page));
				file_unlink($filename);
			}
		}
		file_write($board['dir'] . $config['file_catalog'], generateBoardCatalog());
	}
	if($buildhomepage) { generateKCIndex(); }
	if($config['try_smarter']) { $build_pages = array(); }
}

// filelink for a thread's OP.
function getThreadFileLink($post, $page50 = false) {
	global $config;
	$post = (array)$post;
	return sprintf($page50 ? $config['file_post50'] : $config['file_post'], (isset($post['thread']) && $post['thread']) ? $post['thread'] : $post['id']);
}

// what they actually call
function buildThreadFilesFunction($id, $rebuildindex = false) {
	global $board, $config;
	$id = round($id);
	if(!in_array($board['uri'], $config['private_boards'])) {
		$body_full = buildThreadUniversal($id, false, false);
		if($body_full) {
			file_write($board['dir'] . sprintf($config['file_post'], $id), $body_full);
			$noko50_body = buildThreadUniversal($id, false, true);
			if($noko50_body) { file_write($board['dir'] . sprintf($config['file_post50'], $id), $noko50_body); }
		}
		if($rebuildindex) {
			buildBoardIndexAndPages(false);
		}
	}
}

// unified builder: normal thread OR noko50 variant.
// usage:
//	$body_full=buildThreadUniversal($id, $kara_user=false, $noko=false);
//	$body_50=buildThreadUniversal($id, $kara_user=false, $noko=true);
function buildThreadUniversal($id, $kara_user=false, $noko=false) {
	global $board, $config, $build_pages;
	if($config['cache']['enabled'] && !$kara_user) { // cache + try_smarter handling (keep original side effects; no file writes here)
		cache::delete("thread_index_{$board['uri']}_{$id}");
		cache::delete("thread_{$board['uri']}_{$id}");
	}
	if($config['try_smarter'] && !$kara_user) { $build_pages[] = thread_find_page($id); }
	$thread = buildThreadUniversal_fetch($id, $kara_user, $noko);
	if($thread) {
		return Element($config['file_thread'], [
			'board'=>$board,
			'thread'=>$thread,
			'body'=>$thread->build(false, $noko),
			'config'=>$config,
			'id'=>$id,
			'karadiap'=>$kara_user,
			'return'=>($kara_user ? '?'.$board['url'].$config['file_index'] : $board['dir'].$config['file_index'])
		]);
	}
	return $kara_user ? 'Could not display thread.' : null;
}

// internal helper: builds a Thread object for either full or noko50 slice
function buildThreadUniversal_fetch($id, $kara_user, $noko) {
	global $board, $config;

	// full thread (ascending)
	if(!$noko) {
		$q = prepare(sprintf("SELECT * FROM ``posts_%s`` WHERE (`thread` IS NULL AND `id`=:id) OR `thread`=:id ORDER BY `thread`,`id`", $board['uri']));
		$q->bindValue(':id', $id, PDO::PARAM_INT);
		$q->execute() or error(db_error($q));
		while($p=$q->fetch(PDO::FETCH_ASSOC)) {
			if(!isset($thread)) { $thread = new Thread($p, $kara_user ? '?/' : '/', $kara_user); }
			else { $thread->add(new Post($p, $kara_user ? '?/' : '/', $kara_user)); }
		}
		if(!isset($thread)) { return null; } // sanity check
		return $thread;
	}

	// noko50 path (latest N posts, compute omitted + omitted_images)
	$limit = $config['noko50_count']+1;
	$q = prepare(sprintf("SELECT * FROM ``posts_%s`` WHERE (`thread` IS NULL AND `id`=:id) OR `thread`=:id ORDER BY `thread`,`id` DESC LIMIT :limit", $board['uri']));
	$q->bindValue(':id', $id, PDO::PARAM_INT);
	$q->bindValue(':limit', $limit, PDO::PARAM_INT);
	$q->execute() or error(db_error($q));

	$num_images_in_window = 0;
	while($p=$q->fetch(PDO::FETCH_ASSOC)) {
		if(!isset($thread)) { $thread = new Thread($p, $kara_user ? '?/' : '/', $kara_user); }
		else { if(!empty($p['files'])) { $num_images_in_window += (int)$p['num_files']; } $thread->add(new Post($p, $kara_user ? '?/' : '/', $kara_user)); }
	}
	if(!isset($thread) || $q->rowCount() < $limit) { return null; } // sanity check

	// if we actually had more than noko50_count replies, compute omitted counts
	$cq = prepare(sprintf("SELECT COUNT(`id`) AS `num` FROM ``posts_%s`` WHERE `thread`=:thread UNION ALL SELECT COALESCE(SUM(`num_files`),0) FROM ``posts_%s`` WHERE `files` IS NOT NULL AND `thread`=:thread", $board['uri'], $board['uri']));
	$cq->bindValue(':thread', $id, PDO::PARAM_INT);
	$cq->execute() or error(db_error($cq));
	$c=$cq->fetch(); $total_posts=(int)$c['num']; $thread->omitted=max(0, $total_posts - $config['noko50_count']);
	$c=$cq->fetch(); $total_imgs=(int)$c['num']; $thread->omitted_images=max(0, $total_imgs - $num_images_in_window);
	$thread->posts = array_reverse($thread->posts); // reverse back to ascending order for rendering
	if(count($thread->posts)>$config['noko50_count']) { // trim to exactly noko50_count from tail if needed (handles edge cases)
		$excess = count($thread->posts)-$config['noko50_count'];
		$omitted_imgs = 0;
		for($i=0;$i<$excess;$i++){
			$pp = $thread->posts[$i];
			if(!empty($pp->files)) { $omitted_imgs += (int)$pp->num_files; }
		}
		$thread->posts = array_slice($thread->posts, -$config['noko50_count']);
		$thread->omitted += $excess;
		$thread->omitted_images += $omitted_imgs;
	}
	return $thread;
}


// show user page!
function showUserPage($title, $template, $args, $subtitle = false) {
	global $config, $kara_user;
	$options = [
		'config' => $config,
		'karadiap' => $kara_user,
		'hide_dashboard_link' => $template == $config['file_mod_dashboard'],
		'title' => $title,
		'subtitle' => $subtitle,
		'body' => Element(
			$template,
			array_merge(
				[ 'config' => $config, 'karadiap' => $kara_user ],
				$args
			)
		)
	];
	echo Element($config['file_static_page'], $options);
}

// rebuilding system
function karachan_rebuild_pages($rebuildcache, $rebuildscripts, $rebuildindex, $rebuildthread, $rebuildentirety) {
	global $config, $twig;

	// start timer
	$start = microtime(true);
	$log = [];
	$boards = getAllBoardByURIs();
	$config['try_smarter'] = false;

	// rebuilding the entirety of the boards.
	if($rebuildentirety) {
		$config['always_regenerate_markup'] = true;
		foreach($boards as $board) { wipe_board_directory($board['uri']); }

		// lol
		$rebuildindex = true;
		$rebuildthread = true;
		$rebuildcache = true;
		$rebuildscripts = true;
	}

	if($rebuildcache) {
		if($config['cache']['enabled']) { $log[] = 'Flushing cache'; cache::flush(); }
		$log[] = 'Clearing template cache';
		load_twig();
		$twig->getCache()->clear();
	}

	if($rebuildscripts) {
		$log[] = 'Rebuilding <strong>site scripts</strong>';
		buildScripts();
	}

	foreach($boards as $board) {
		if(!($rebuildentirety || isset($_POST['board_' . $board['uri']]))) { continue; }
		openBoard($board['uri']);
		autocleanThreadsMaxpages();
		if($rebuildthread) {
			$query = query(sprintf("SELECT `id` FROM ``posts_%s`` WHERE `thread` IS NULL", $board['uri'])) or error(db_error());
			$rebuilt_ids = [];
			while($post = $query->fetch(PDO::FETCH_ASSOC)) {
				$rebuilt_ids[] = $post['id'];
				buildThreadFilesFunction($post['id']);
			}
			if(!empty($rebuilt_ids)) {
				$final_log_entry = '<strong>' . sprintf($config['board_abbreviation'], $board['uri']) . '</strong>: Rebuilt threads: #' . implode(', #', $rebuilt_ids);
				$log[] = $final_log_entry;
			}
		}
		if($rebuildindex) { buildBoardIndexAndPages(false); $log[] = '<strong>' . sprintf($config['board_abbreviation'], $board['uri']) . '</strong>: Creating index pages'; }
	}
	if($rebuildindex) {
		$log[] = 'Rebuilding <strong>karachan homepage</strong>';
		generateKCIndex();
		$log[] = 'Generating karachan standard pages';
		foreach(glob("templates/pages/*.html") as $filepath) {
			$filename = basename($filepath);
			file_write($filename, Element('page.html', array(
				'title' => ucfirst(basename($filepath, '.' . pathinfo($filepath, PATHINFO_EXTENSION))),
				'config' => $config,
				'body' => file_get_contents($filepath)
			)));
			$log[] = 'Generated karachan std page ' . $filename;
		}
	}
	$time_taken = microtime(true) - $start;
	$log[] = 'Completed in ' . round($time_taken, 4) . ' seconds!';
	return $log;
}