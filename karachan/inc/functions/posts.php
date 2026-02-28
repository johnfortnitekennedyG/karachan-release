<?php

// make a post, returns the ID
function post(array $post) {
	global $pdo, $board, $config;

	// Prepare post. The last field (pending approval) is defaulted to 1.
	$query = prepare(sprintf(
		"INSERT INTO `posts_%s` VALUES (
			NULL, :thread, :subject, :name, :trip, :capcode,
			:body, :body_nomarkup, :files, :filehash, :pearlsecurity, :embed,
			:time, :time, :num_files, :password, :ip,
			:sticky, :locked, :cycle, :pamped, :imagespoilered, :pendingapproval
		)", $board['uri']
	));

	// Basic stuff
	if(!empty($post['subject'])) { $query->bindValue(':subject', $post['subject']); } else { $query->bindValue(':subject', null, PDO::PARAM_NULL); }
	if(!empty($post['trip'])) { $query->bindValue(':trip', $post['trip']); } else { $query->bindValue(':trip', null, PDO::PARAM_NULL); }

	$query->bindValue(':name', $post['name']);
	$query->bindValue(':body', $post['body']);
	$query->bindValue(':body_nomarkup', $post['body_nomarkup']);
	$query->bindValue(':time', isset($post['time']) ? $post['time'] : time(), PDO::PARAM_INT);
	$query->bindValue(':password', $post['password']);
	$query->bindValue(':ip', isset($post['ip']) ? $post['ip'] : pearl_get_real_ip());

	// some settings
	$query->bindValue(':sticky', ($post['op'] && !empty($post['sticky'])), PDO::PARAM_INT);
	$query->bindValue(':locked', ($post['op'] && !empty($post['locked'])), PDO::PARAM_INT);
	$query->bindValue(':cycle', ($post['op'] && !empty($post['cycle'])), PDO::PARAM_INT);
	$query->bindValue(':pamped', isset($post['pamped']) ? $post['pamped'] : 0, PDO::PARAM_INT);
	$query->bindValue(':imagespoilered', isset($post['imagespoilered']) ? $post['imagespoilered'] : 0, PDO::PARAM_INT);

	if(isset($post['capcode']) && $post['capcode']) { $query->bindValue(':capcode', $post['capcode'], PDO::PARAM_STR); }
	else { $query->bindValue(':capcode', null, PDO::PARAM_NULL); }

	if(!empty($post['embed'])) { $query->bindValue(':embed', $post['embed']); }
	else { $query->bindValue(':embed', null, PDO::PARAM_NULL); }

	// Security tracking
	if(isset($post['pearlsecurity'])) { $query->bindValue(':pearlsecurity', json_encode($post['pearlsecurity'])); }
	else { $query->bindValue(':pearlsecurity', null, PDO::PARAM_NULL); }

	// thread post, or reply?
	$query->bindValue(':thread',
		$post['op'] ? null : $post['thread'],
		$post['op'] ? PDO::PARAM_NULL : PDO::PARAM_INT
	);

	$query->bindValue(':pendingapproval', isset($post['bypass_approval']) ? 0 : (($post['has_file'] ? 1 : 0) | ($config['must_approve_post_body'] ? 2 : 0)), PDO::PARAM_INT);
	if($post['has_file']) {
		$query->bindValue(':files', json_encode($post['files']));
		$query->bindValue(':num_files', $post['num_files']);
		$query->bindValue(':filehash', $post['filehash']);
	} else {
		$query->bindValue(':files', null, PDO::PARAM_NULL);
		$query->bindValue(':num_files', 0);
		$query->bindValue(':filehash', null, PDO::PARAM_NULL);
	}

	if(!$query->execute()) {
		undoImage($post);
		error(db_error($query));
	}

	return $pdo->lastInsertId();
}

// bumping a thread
function bumpThread($id) {
	global $config, $board, $build_pages;
	if($config['try_smarter']) { $build_pages = array_merge(range(1, thread_find_page($id)), $build_pages); }
	$query = prepare(sprintf("UPDATE ``posts_%s`` SET `bump` = :time WHERE `id` = :id AND `thread` IS NULL", $board['uri']));
	$query->bindValue(':time', time(), PDO::PARAM_INT);
	$query->bindValue(':id', $id, PDO::PARAM_INT);
	$query->execute() or error(db_error($query));
}

// get a post by the hash.
function getPostByHash($hash) {
	global $board;
	$query = prepare(sprintf("SELECT `id`,`thread` FROM ``posts_%s`` WHERE `filehash` = :hash", $board['uri']));
	$query->bindValue(':hash', $hash, PDO::PARAM_STR);
	$query->execute() or error(db_error($query));
	if ($post = $query->fetch(PDO::FETCH_ASSOC)) { return $post; }
	return false;
}

// get a post by hash in thread.
function getPostByHashInThread($hash, $thread) {
	global $board;
	$query = prepare(sprintf("SELECT `id`,`thread` FROM ``posts_%s`` WHERE `filehash` = :hash AND ( `thread` = :thread OR `id` = :thread )", $board['uri']));
	$query->bindValue(':hash', $hash, PDO::PARAM_STR);
	$query->bindValue(':thread', $thread, PDO::PARAM_INT);
	$query->execute() or error(db_error($query));
	if ($post = $query->fetch(PDO::FETCH_ASSOC)) { return $post; }
	return false;
}

// is thread locked?
function threadLocked($id) {
	global $board;
	$query = prepare(sprintf("SELECT `locked` FROM ``posts_%s`` WHERE `id` = :id AND `thread` IS NULL LIMIT 1", $board['uri']));
	$query->bindValue(':id', $id, PDO::PARAM_INT);
	$query->execute() or error(db_error());
	if(($locked = $query->fetchColumn()) === false) {
		// Non-existant, so it can't be locked...
		return false;
	}
	return (bool)$locked;
}

// does thread exist?
function threadExists($id) {
	global $board;

	$query = prepare(sprintf("SELECT 1 FROM ``posts_%s`` WHERE `id` = :id AND `thread` IS NULL LIMIT 1", $board['uri']));
	$query->bindValue(':id', $id, PDO::PARAM_INT);
	$query->execute() or error(db_error());

	if($query->rowCount()) { return true; }
	return false;
}

// insert flood post
function insertFloodPost(array $post) {
	global $board;

	$query = prepare("INSERT INTO ``flood`` VALUES (NULL, :ip, :board, :time, :posthash, :filehash, :isreply)");
	$query->bindValue(':ip', pearl_get_real_ip());
	$query->bindValue(':board', $board['uri']);
	$query->bindValue(':time', time());
	$query->bindValue(':posthash', make_comment_hex($post['body_nomarkup']));
	if($post['has_file']) { $query->bindValue(':filehash', $post['filehash']); }
	else { $query->bindValue(':filehash', null, PDO::PARAM_NULL); }
	$query->bindValue(':isreply', !$post['op'], PDO::PARAM_INT);
	$query->execute() or error(db_error($query));
}

// find page of thread
function thread_find_page($thread) {
	global $config, $board;
	$query = query(sprintf("SELECT `id` FROM ``posts_%s`` WHERE `thread` IS NULL ORDER BY `sticky` DESC, `bump` DESC", $board['uri'])) or error(db_error($query));
	$threads = $query->fetchAll(PDO::FETCH_COLUMN);
	if(($index = array_search($thread, $threads)) === false) { return false; }
	return floor(($config['threads_per_page'] + $index) / $config['threads_per_page']);
}

// Remove file from post
function deleteFile($id, $remove_entirely_if_already=true, $file=null) {
	global $board, $config;

	$query = prepare(sprintf("SELECT `thread`, `files`, `num_files` FROM ``posts_%s`` WHERE `id` = :id LIMIT 1", $board['uri']));
	$query->bindValue(':id', $id, PDO::PARAM_INT);
	$query->execute() or error(db_error($query));
	if(!$post = $query->fetch(PDO::FETCH_ASSOC)) { error($config['error']['invalidpost']); }
	$files = json_decode($post['files']);
	$file_to_delete = $file !== false ? $files[(int)$file] : (object)array('file' => false);

	if(!$files[0]) { error('That post has no files.'); }
	if($files[0]->file == 'deleted' && $post['num_files'] == 1 && !$post['thread']) { return; } // Can't delete OP's image completely.

	$query = prepare(sprintf("UPDATE ``posts_%s`` SET `files` = :file WHERE `id` = :id", $board['uri']));
	if(($file && $file_to_delete->file == 'deleted') && $remove_entirely_if_already) {
		// Already deleted; remove file fully
		$files[$file] = null;
	} else {
		foreach ($files as $i => $f) {
			if(($file !== false && $i == $file) || $file === null) {
				// remove .png (or whatever extension) and replace with .jpg
				$thumbFile = pathinfo($f->file, PATHINFO_FILENAME) . '.jpg';
				file_unlink('stnk/' . $f->file);
				file_unlink('krth/' . $thumbFile);
				$files[$i]->file = 'deleted';
			}
		}
	}

	$query->bindValue(':file', json_encode($files), PDO::PARAM_STR);

	$query->bindValue(':id', $id, PDO::PARAM_INT);
	$query->execute() or error(db_error($query));

	if($post['thread']) { buildThreadFilesFunction($post['thread']); }
	else { buildThreadFilesFunction($id); }
}

// rebuild post (markup)
function rebuildPost($id) {
	global $board, $kara_user;

	$query = prepare(sprintf("SELECT * FROM ``posts_%s`` WHERE `id` = :id", $board['uri']));
	$query->bindValue(':id', $id, PDO::PARAM_INT);
	$query->execute() or error(db_error($query));

	if((!$post = $query->fetch(PDO::FETCH_ASSOC)) || !$post['body_nomarkup']) { return false; }

	markup($post['body'] = &$post['body_nomarkup']);
	$post = (object)$post;
	$post = (array)$post;

	$query = prepare(sprintf("UPDATE ``posts_%s`` SET `body` = :body WHERE `id` = :id", $board['uri']));
	$query->bindValue(':body', $post['body']);
	$query->bindValue(':id', $id, PDO::PARAM_INT);
	$query->execute() or error(db_error($query));

	buildThreadFilesFunction($post['thread'] ? $post['thread'] : $id);

	return true;
}

// Returns an associative array with 'replies' and 'images' keys
function numPosts($id) {
	global $board;
	$query = prepare(sprintf("SELECT COUNT(*) AS `replies`, SUM(`num_files`) AS `images` FROM ``posts_%s`` WHERE `thread` = :thread", $board['uri'], $board['uri']));
	$query->bindValue(':thread', $id, PDO::PARAM_INT);
	$query->execute() or error(db_error($query));

	return $query->fetch(PDO::FETCH_ASSOC);
}

// Delete a post (reply or thread)
function deletePost($id, $error_if_doesnt_exist=true, $rebuild_after=true, $nuke_images=true) {
	global $board, $config;

	// Select post and replies (if thread) in one query
	$query = prepare(sprintf("SELECT `id`,`thread`,`files` FROM ``posts_%s`` WHERE `id` = :id OR `thread` = :id", $board['uri']));
	$query->bindValue(':id', $id, PDO::PARAM_INT);
	$query->execute() or error(db_error($query));

	if($query->rowCount() < 1) {
		if($error_if_doesnt_exist) { error($config['error']['invalidpost']); }
		else { return false; }
	}

	$ids = array();

	// Delete posts and maybe replies
	while($post = $query->fetch(PDO::FETCH_ASSOC)) {
		$thread_id = $post['thread'];
		if(!$post['thread']) {
			// Delete thread HTML page
			file_unlink($board['dir'] . getThreadFileLink($post) );
			file_unlink($board['dir'] . getThreadFileLink($post, true) ); // noko50
		} elseif($query->rowCount() == 1) {
			// Rebuild thread
			$rebuild = &$post['thread'];
		}
		if($post['files'] && $nuke_images) {
			// Delete file
			foreach (json_decode($post['files']) as $i => $f) {
				if($f->file !== 'deleted') {
					// remove .png (or whatever extension) and replace with .jpg
					$thumbFile = pathinfo($f->file, PATHINFO_FILENAME) . '.jpg';
					file_unlink('stnk/' . $f->file);
					file_unlink('krth/' . $thumbFile);
				}
			}
		}

		$ids[] = (int)$post['id'];

	}

	$query = prepare(sprintf("DELETE FROM ``posts_%s`` WHERE `id` = :id OR `thread` = :id", $board['uri']));
	$query->bindValue(':id', $id, PDO::PARAM_INT);
	$query->execute() or error(db_error($query));

	$query = prepare("SELECT `board`, `post` FROM ``cites`` WHERE `target_board` = :board AND (`target` = " . implode(' OR `target` = ', $ids) . ") ORDER BY `board`");
	$query->bindValue(':board', $board['uri']);
	$query->execute() or error(db_error($query));
	while($cite = $query->fetch(PDO::FETCH_ASSOC)) {
		if($board['uri'] != $cite['board']) {
			if(!isset($tmp_board))
				$tmp_board = $board['uri'];
			openBoard($cite['board']);
		}
		rebuildPost($cite['post']);
	}

	if(isset($tmp_board)) { openBoard($tmp_board); }

	$query = prepare("DELETE FROM ``cites`` WHERE (`target_board` = :board AND (`target` = " . implode(' OR `target` = ', $ids) . ")) OR (`board` = :board AND (`post` = " . implode(' OR `post` = ', $ids) . "))");
	$query->bindValue(':board', $board['uri']);
	$query->execute() or error(db_error($query));

	// No need to run on OPs
	if($config['anti_bump_flood'] && isset($thread_id)) {
		$query = prepare(sprintf("SELECT `pamped` FROM ``posts_%s`` WHERE `id` = :thread", $board['uri']));
		$query->bindValue(':thread', $thread_id);
		$query->execute() or error(db_error($query));
		$bumplocked = (bool)$query->fetchColumn();

		if(!$bumplocked) {
			$query = prepare(sprintf(
				"SELECT `time` FROM `posts_%s`
				WHERE (`thread` = :thread AND pamped > 0) OR `id` = :thread
				ORDER BY `time` DESC LIMIT 1",
				$board['uri']
			));
			$query->bindValue(':thread', $thread_id);
			$query->execute() or error(db_error($query));
			$bump = $query->fetchColumn();

			$query = prepare(sprintf("UPDATE ``posts_%s`` SET `bump` = :bump WHERE `id` = :thread", $board['uri']));
			$query->bindValue(':bump', $bump);
			$query->bindValue(':thread', $thread_id);
			$query->execute() or error(db_error($query));
		}
	}

	if(isset($rebuild) && $rebuild_after) {
		buildThreadFilesFunction($rebuild, true);
	}

	return true;
}

function autocleanThreadsMaxpages($pid = false) {
	global $board, $config;
	$offset = round($config['max_pages']*$config['threads_per_page']);

	// I too wish there was an easier way of doing this...
	$query = prepare(sprintf("SELECT `id` FROM ``posts_%s`` WHERE `thread` IS NULL ORDER BY `sticky` DESC, `bump` DESC LIMIT :offset, 9001", $board['uri']));
	$query->bindValue(':offset', $offset, PDO::PARAM_INT);

	$query->execute() or error(db_error($query));
	while($post = $query->fetch(PDO::FETCH_ASSOC)) {
		deletePost($post['id'], false, false);
		if($pid) { modLog("Automatically deleting thread #{$post['id']} due to new thread #{$pid}"); }
	}
}

function check_thread_limit($post) {
	global $config, $board;
	if (!isset($config['max_threads_per_hour']) || !$config['max_threads_per_hour']) { return false; }
	if ($post['op']) {
		$query = prepare(sprintf('SELECT COUNT(*) AS `count` FROM ``posts_%s`` WHERE `thread` IS NULL AND FROM_UNIXTIME(`time`) > DATE_SUB(NOW(), INTERVAL 1 HOUR);', $board['uri']));
		$query->execute() or error(db_error($query));
		$r = $query->fetch(PDO::FETCH_ASSOC);

		return $r['count'] >= $config['max_threads_per_hour'];
	}
}

function poster_id($ip, $thread) {
	global $config;

	// Confusing, hard to brute-force, but simple algorithm
	return substr(sha1(sha1($ip . $config['secure_trip_salt'] . $thread) . $config['secure_trip_salt']), 0, $config['poster_id_length']);
}

function generate_tripcode($name) {
	global $config;

	if (!preg_match('/^([^#]+)?(##|#)(.+)$/', $name, $match)) { return array($name); }

	$name = $match[1];
	$secure = $match[2] == '##';
	$trip = $match[3];

	// convert to SHIT_JIS encoding
	$trip = mb_convert_encoding($trip, 'Shift_JIS', 'UTF-8');

	// generate salt
	$salt = substr($trip . 'H..', 1, 2);
	$salt = preg_replace('/[^.-z]/', '.', $salt);
	$salt = strtr($salt, ':;<=>?@[\]^_`', 'ABCDEFGabcdef');

	if ($secure) {
		$trip = '!!' . substr(crypt($trip, str_replace('+', '.', '_..A.' . substr(base64_encode(sha1($trip . $config['secure_trip_salt'], true)), 0, 4))), -10);
	} else {
		$trip = '!' . substr(crypt($trip, $salt), -10);
	}

	return array($name, $trip);
}

/**
 * Delete posts in a cyclical thread.
 *
 * @param string $boardUri The URI of the board.
 * @param int $threadId The ID of the thread.
 * @param int $cycleLimit The number of most recent posts to retain.
 */
function delete_cyclical_posts(string $boardUri, int $threadId, int $cycleLimit): void
{
    $query = prepare(sprintf('
        SELECT p.`id`
        FROM ``posts_%s`` p
        LEFT JOIN (
            SELECT `id`
            FROM ``posts_%s``
            WHERE `thread` = :thread
            ORDER BY `id` DESC
            LIMIT :limit
        ) recent_posts ON p.id = recent_posts.id
        WHERE p.thread = :thread
        AND recent_posts.id IS NULL',
        $boardUri, $boardUri
    ));

    $query->bindValue(':thread', $threadId, PDO::PARAM_INT);
    $query->bindValue(':limit', $cycleLimit, PDO::PARAM_INT);

    $query->execute() or error(db_error($query));
    $ids = $query->fetchAll(PDO::FETCH_COLUMN);

    foreach ($ids as $id) { deletePost($id, false); }
}

function karachan_random_name() {
	$names = array(
		'Dumbass Wigga', 'Dumbass Nigga', 'Nigga', 'Widdle Nigger', 'Widdle Cracker', 'Cracka', 'Hamster', 'Despacito Spider',
		'Laverne', 'Hoagie', 'Dr Fred', 'Bernard', 'Purple Tentacle', 'Green Tentacle', 'Thug', 'Quandale Dingle', 'Pooner',
		'The Naked Barber', 'Bull', 'Goonicide', 'Giggletouch', 'Rance Victim', 'Partybug', 'Mama', 'Lester', 'Paddedbutt',
		'King', 'Noobass', 'Anvil', 'Solloway', 'Im nude bro', 'Dig in yo butt twin', 'The Raped', 'Therapist', 'Boymoder',
		'Freidisciple', 'Rat', 'Mentiras', 'Nail Biter', 'Physique Nigger', 'DTPN', 'Small Dog', 'Lion', 'Deadbeat', 'Femboy',
		'Dont Tap The Glass', 'Shaboingboing (not) live', 'TF2 scout', 'George melons', 'Dih head', 'Dick head', 'Attack Helicopter',
		'Niggerbob', 'Left Testicle', 'Right Testicle', 'Jayla', 'Elias', 'Wadda', 'Sherbet', 'Rance', 'Mei', 'Jeck', 'Frog',
		'Maria', 'Austin', 'Bossman', 'Mossad Agent', 'Penis Monkey', 'Ploppy', 'Ikea Man', 'Poop Nigga', 'Chip', 'Randy Marsh',
		'Little Nigga In The Toilet', 'Memoca', 'Froze', 'I Farted', 'Suenonym From Ohio', 'Bridget', 'May', 'Dale', 'Grant',
		'LowTierGod', 'Rog', 'Meru', 'Kay', 'Brown Monkey', 'Duck', 'Suzy', 'Serial Designation N', 'Toyota Camry', 'Shawn',
		'Macaroni', 'Rane', 'Ty', 'Gnarly', 'Tubular', 'Mondo', 'Awesome', 'Orca', 'Shark', 'Sameki', 'Yuga', 'NIGGAPOOP'
	);
	$standard_name = $names[array_rand($names)];
	if(mt_rand(1, 100) <= 25) { return strtoupper($standard_name) . '.XXX'; } //25% chance for a .XXX name
	return $standard_name;
}