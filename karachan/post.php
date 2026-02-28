<?php
require_once 'inc/bootstrap.php';

use Karachan\{Context, WebDependencyFactory};
use Karachan\Data\Driver\{LogDriver, HttpDriver};
use Karachan\Functions\Format;
$context = Karachan\build_context($config);
$ip = pearl_get_real_ip();
session_start();

// User login check
check_login($context, false);

// DNS blocklist, first for the sake of security.
if(!hasPerm('bypassrestrictions')) { checkDNSBL(); }

// [Pearl]
if($config['pearl_security_store']) {
	$query = prepare("SELECT * FROM ``pearl_extras`` WHERE ip = ?");
	$query->execute([$ip]) or error(db_error($query));
	$pearlsec = $query->fetch(PDO::FETCH_ASSOC);
	if($config['require_pearl_extra_checks'] && !$pearlsec && !hasPerm('bypassrestrictions')) { error("Please turn on javascript to interact with the site. If you still cannot post, wait a while, otherwise report this as a bug."); }
}

// Delete
if(isset($_POST['delete'])) {
	if(!isset($_POST['board'], $_POST['password'])) { error($config['error']['bot']); }

	// Check if banned
	checkBan();

	if(empty($_POST['password'])){ error($config['error']['invalidpassword']); }
	$password = $_POST['password'];
	$delete = array();
	foreach($_POST as $post => $value) {
		if(preg_match('/^delete_(\d+)$/', $post, $m)) {
			$delete[] = (int)$m[1];
		}
	}

	// Check if board exists
	if(!openBoardSafe($_POST['board'])) { error($config['error']['noboard']); }

	// Check if deletion enabled
	if(!$config['allow_delete']) { error('Post deletion is not allowed!'); }

	if(empty($delete)) { error($config['error']['nodelete']); }

	foreach($delete as &$id) {
		$query = prepare(sprintf("SELECT `id`,`thread`,`time`,`password` FROM ``posts_%s`` WHERE `id` = :id", $board['uri']));
		$query->bindValue(':id', $id, PDO::PARAM_INT);
		$query->execute() or error(db_error($query));

		if($post = $query->fetch(PDO::FETCH_ASSOC)) {
			$thread = false;
			if($config['user_moderation'] && $post['thread']) {
				$thread_query = prepare(sprintf("SELECT `time`,`password` FROM ``posts_%s`` WHERE `id` = :id", $board['uri']));
				$thread_query->bindValue(':id', $post['thread'], PDO::PARAM_INT);
				$thread_query->execute() or error(db_error($query));

				$thread = $thread_query->fetch(PDO::FETCH_ASSOC);
			}

			if($post['time'] < time() - $config['max_delete_time'] && $config['max_delete_time'] != false) {
				error(sprintf($config['error']['delete_too_late'], Format\until($post['time'] + $config['max_delete_time'])));
			}

			if(!hash_equals($post['password'], $password) && (!$thread || !hash_equals($thread['password'], $password))) {
				error($config['error']['invalidpassword']);
			}


			if($post['time'] > time() - $config['delete_time'] && (!$thread || !hash_equals($thread['password'], $password))) {
				error(sprintf($config['error']['delete_too_soon'], Format\until($post['time'] + $config['delete_time'])));
			}

			if(isset($_POST['file'])) {
				// Delete just the file
				deleteFile($id);
				modLog("User at $ip deleted file from their own post #$id");
			} else {
				// Delete entire post
				deletePost($id);
				modLog("User at $ip deleted their own post #$id");
			}

			$context->get(LogDriver::class)->log(
				LogDriver::INFO,
				'Deleted post: /' . $board['dir'] . getThreadFileLink($post) . ($post['thread'] ? '#' . $id : '')
			);
		}
	}

	buildBoardIndexAndPages(true);
	$root = isset($kara_user) ? $config['file_account'] . '?/' : '/';

	if(!isset($_POST['json_response'])) {
		header('Location: ' . $root . $board['dir'] . $config['file_index'], true, $config['redirect_http']);
	} else {
		header('Content-Type: text/json');
		echo json_encode(array('success' => true));
	}

	// We are already done, let's continue our heavy-lifting work in the background (if we run off FastCGI)
	if(function_exists('fastcgi_finish_request')) { @fastcgi_finish_request(); }
} elseif(isset($_POST['report'])) {
	if(!isset($_POST['board'], $_POST['reason'])) { error($config['error']['bot']); }

	$report = array();
	foreach($_POST as $post => $value) {
		if(preg_match('/^delete_(\d+)$/', $post, $m)) {
			$report[] = (int)$m[1];
		}
	}

	// Check if board exists
	if(!openBoardSafe($_POST['board'])) { error($config['error']['noboard']); }

	// Check if banned
	checkBan();

	if(empty($report)) { error($config['error']['noreport']); }
	if(count($report) > $config['report_limit']) { error($config['error']['toomanyreports']); }

	$reason = escape_markup_modifiers($_POST['reason']);
	markup($reason);

	if(mb_strlen($reason) > $config['report_max_length']) { error($config['error']['toolongreport']); }

	foreach($report as &$id) {
		$query = prepare(sprintf("SELECT `id`, `thread` FROM ``posts_%s`` WHERE `id` = :id", $board['uri']));
		$query->bindValue(':id', $id, PDO::PARAM_INT);
		$query->execute() or error(db_error($query));

		$post = $query->fetch(PDO::FETCH_ASSOC);
		if($post === false) {
			$context->get(LogDriver::class)->log(LogDriver::INFO, "Failed to report non-existing post #{$id} in {$board['dir']}");
			error($config['error']['nopost']);
		}

		pearl_notify_discord(
			'**Report:** ' . $config['domain'] . '/' . $board['dir'] . getThreadFileLink($post) . ($post['thread'] ? '#' . $id : '') . " for: \"$reason\"",
			$config['discord_webhook_moderation']
		);

		$context->get(LogDriver::class)->log(
			LogDriver::INFO,
			'Reported post: /'
				 . $board['dir'] . getThreadFileLink($post) . ($post['thread'] ? '#' . $id : '')
				 . " for \"$reason\""
		);
		$query = prepare("INSERT INTO ``reports`` VALUES (NULL, :time, :ip, :board, :post, :reason)");
		$query->bindValue(':time', time(), PDO::PARAM_INT);
		$query->bindValue(':ip', $ip, PDO::PARAM_STR);
		$query->bindValue(':board', $board['uri'], PDO::PARAM_STR);
		$query->bindValue(':post', $id, PDO::PARAM_INT);
		$query->bindValue(':reason', $reason, PDO::PARAM_STR);
		$query->execute() or error(db_error($query));
	}

	$root = isset($kara_user) ? $config['file_account'] . '?/' : '/';
	if(!isset($_POST['json_response'])) {
		$index = $root . $board['dir'] . $config['file_index'];
		echo Element($config['file_static_page'], array('config' => $config, 'body' => '<div style="text-align:center"><a href="javascript:window.close()">[ ' . 'Close window' ." ]</a> <a href='$index'>[ " . 'Return' . ' ]</a></div>', 'title' => 'Report submitted!'));
	} else {
		header('Content-Type: text/json');
		echo json_encode(array('success' => true));
	}
} elseif(isset($_POST['post'])) {
	if(!isset($_POST['body'], $_POST['board'])) { error($config['error']['bot']); }

	// Check if banned
	checkBan();

	$post = array('board' => $_POST['board'], 'files' => array(), 'pearlsecurity' => array());

	// Check if board exists
	if(!openBoardSafe($post['board'])) { error($config['error']['noboard']); }
	if(!isset($_POST['pamped'])) { $post['pamped'] = 0; }
	else { $post['pamped'] = (is_numeric($_POST['pamped']) ? (int)$_POST['pamped'] : 0); }
	if(!isset($_POST['subject'])) { $_POST['subject'] = ''; }
	if(!isset($_POST['password'])) { $_POST['password'] = ''; }
	if(isset($_POST['thread'])) { $post['op'] = false; $post['thread'] = round($_POST['thread']); } else { $post['op'] = true; }

	// Mod check, mods can bypass a whole lot of stuff.
	$post['sticky'] = $post['op'] && isset($_POST['sticky']);
	$post['locked'] = $post['op'] && isset($_POST['lock']);
	$post['raw'] = isset($_POST['raw']);

	if($post['sticky'] && !hasPerm('sticky')) { error($config['error']['noaccess']); }
	if($post['locked'] && !hasPerm('lock')) { error($config['error']['noaccess']); }
	if($post['raw'] && !hasPerm('rawhtml')) { error($config['error']['noaccess']); }
	
	// Mod check, mods can bypass a whole lot of stuff.
	if(!hasPerm('bypassrestrictions')) {
		if(!(($post['op'] && $_POST['post'] == $config['button_newtopic']) || (!$post['op'] && $_POST['post'] == $config['button_reply']))) {
			error($config['error']['bot']);
		}
		if($config['security_question']) {
			if(!isset($_POST['security_question']) || strtolower($config['security_question']['answer']) != strtolower($_POST['security_question'])) {
				error($config['error']['security_question']);
			}
		}
	} else {
		$post['bypass_approval'] = true;
	}

	//Check if thread exists
	if(!$post['op']) {
		$query = prepare(sprintf("SELECT `sticky`,`locked`,`cycle`,`pamped` FROM ``posts_%s`` WHERE `id` = :id AND `thread` IS NULL LIMIT 1", $board['uri']));
		$query->bindValue(':id', $post['thread'], PDO::PARAM_INT);
		$query->execute() or error(db_error());
		if(!$thread = $query->fetch(PDO::FETCH_ASSOC)) { error($config['error']['nonexistant']); }
	} else {
		$thread = false;
	}

	// Check for an embed field
	if($config['enable_embedding'] && isset($_POST['embed']) && !empty($_POST['embed'])) {
		// yep; validate it
		$value = $_POST['embed'];
		foreach($config['embedding'] as &$embed) {
			if(preg_match($embed[0], $value)) {
				// Valid link
				$post['embed'] = $value;
				// This is bad, lol.
				$post['no_longer_require_an_image_for_op'] = true;
				break;
			}
		}
		if(!isset($post['embed'])) { error($config['error']['invalid_embed']); }
	}

	// field fuckery
	$poster_username = isset($kara_user) ? $kara_user['username'] : karachan_random_name();
	if(!hasPerm('bypass_field_disable')) {
		if($config['field_disable_name']) { $_POST['name'] = $poster_username; } // "forced anonymous"
		if($config['field_disable_password']) { $_POST['password'] = ''; }
		if($config['field_disable_subject'] || (!$post['op'] && $config['field_disable_reply_subject'])) { $_POST['subject'] = ''; }
		if($config['field_disable_pamped']) { $post['pamped'] = 0; }
	}

	// here we are
	$post['name'] = isset($_POST['name']) && $_POST['name'] != '' ? $_POST['name'] : $poster_username;
	$post['subject'] = $_POST['subject'];
	$post['body'] = $_POST['body'];
	$post['password'] = $_POST['password'];
	$post['has_file'] = (!isset($post['embed']) && (($post['op'] && !isset($post['no_longer_require_an_image_for_op']) && $config['force_image_op']) || count($_FILES) > 0));
	$post['imagespoilered'] = isset($_POST['spoiler']);

	// body fuckery
	if(!($post['has_file'] || isset($post['embed'])) || (($post['op'] && $config['force_body_op']) || (!$post['op'] && $config['force_body']))) {
		$stripped_whitespace = preg_replace('/[\s]/u', '', $post['body']);
		if($stripped_whitespace == '') {
			error($config['error']['tooshort_body']);
		}
	}

	// Check if thread is locked (or too much replies)  but allow mods to post
	if(!$post['op']) {
		if($thread['locked'] && !hasPerm('postinlocked')) { error($config['error']['locked']); }
		$numposts = numPosts($post['thread']);
		if($config['reply_hard_limit'] != 0 && $config['reply_hard_limit'] <= $numposts['replies']) { error($config['error']['reply_hard_limit']); }
		if($post['has_file'] && $config['image_hard_limit'] != 0 && $config['image_hard_limit'] <= $numposts['images']) { error($config['error']['image_hard_limit']); }
	}

	// Capcode handling
	$post['capcode'] = false;
	if(hasPerm('capcode') && preg_match('/^((.+) )?## (.+)$/', $post['name'], $matches)) {
		$name = $matches[2] != '' ? $matches[2] : $poster_username;
		$cap = $matches[3];
		$post['capcode'] = utf8tohtml($cap);
		$post['name'] = $name;
	}

	// Trippy trip!
	$trip = generate_tripcode($post['name']);
	$post['name'] = $trip[0];
	if($config['disable_tripcodes'] && !hasPerm('tripcode')) { $post['trip'] = ''; } else { $post['trip'] = isset($trip[1]) ? $trip[1] : ''; }

	// Strip combining characters.
	if($config['strip_combining_chars']) {
		$post['name'] = strip_combining_chars($post['name']);
		$post['subject'] = strip_combining_chars($post['subject']);
		$post['body'] = strip_combining_chars($post['body']);
	}

	// Check string lengths
	if(mb_strlen($post['name']) > 35) { error(sprintf($config['error']['toolong'], 'name')); }
	if(mb_strlen($post['subject']) > 100) { error(sprintf($config['error']['toolong'], 'subject')); }
	if(!hasPerm('rawhtml') && mb_strlen($post['body']) > $config['max_body']) { error($config['error']['toolong_body']); }
	if(!hasPerm('rawhtml') && substr_count($post['body'], "\n") >= $config['maximum_lines']) { error($config['error']['toomanylines']); }

	// Here we go.
	$post['body'] = escape_markup_modifiers($post['body']);
	if(isset($post['raw']) && $post['raw']) { $post['body'] .= "\n<mephboard raw html>1</mephboard>"; }

	// Country flags.
	$gi = geoip_open('inc/GeoIPv6.dat', GEOIP_STANDARD);
	if($country_code = geoip_country_code_by_addr_v6($gi, ipv4to6($ip))) {
		$post['body'] .= "\n<mephboard flag>" . strtolower($country_code) . "</mephboard>\n<mephboard flag alt>" . geoip_country_name_by_addr_v6($gi, ipv4to6($ip)) . "</mephboard>";
	}

	// Handle integrity/security values
	$browserbasic = clean_input($_SERVER['HTTP_USER_AGENT'] ?? 'No useragent') . " / " . clean_input($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'No language');
	$post['pearlsecurity']['basic'] = '<label title="' . $browserbasic . '">' . pearl_hide_info($browserbasic) . '</label>';
	$post['pearlsecurity']['password'] = clean_input($post['password']);
	if($pearlsec) {
		if($pearlsec['discord']) { $post['pearlsecurity']['discord'] = 'Discord'; }
		if($pearlsec['tuxler']) { $post['pearlsecurity']['tuxler'] = 'Tuxler VPN'; }
		if($pearlsec['vpn']) { $post['pearlsecurity']['vpn'] = 'WRTC'; }
		if($pearlsec['incognito']) { $post['pearlsecurity']['incognito'] = 'Incognito'; }
		if($pearlsec['mobile']) { $post['pearlsecurity']['mobile'] = 'Mobile'; }
		if($pearlsec['kp']) { $post['pearlsecurity']['password'] .= ' / ' . clean_input($pearlsec['kp']); }
		if($pearlsec['wi'] && $pearlsec['hi'] && $pearlsec['wm'] && $pearlsec['hm']) {
			$post['pearlsecurity']['resolution'] = $pearlsec['wi'] . 'x' . $pearlsec['hi'] . ' (' . $pearlsec['wm'] . 'x' . $pearlsec['hm'] . ')';
		}
	} else {
		$post['pearlsecurity']['jsdisabled'] = 'Disabled JS';
	}
	if(isset($kara_user)) { $post['pearlsecurity']['user'] = $kara_user['username'] . ' (#' . $kara_user['id'] . ')'; }

	// Handle uploaded files.
	$post['filehash'] = '';
	if($post['has_file']) {
		$i = 0;
		$total_filesize = 0;
		foreach($_FILES as $key => $file) {
			if(!in_array($file['error'], array(UPLOAD_ERR_NO_FILE, UPLOAD_ERR_OK))) {
				error(sprintf3($config['error']['phpfileserror'], array(
					'index' => $i+1,
					'code' => $file['error']
				)));
			}

			if($file['size'] && $file['tmp_name']) {
				// Can the file be fucked with?
				if(!is_readable($file['tmp_name'])) { error($config['error']['nomove']); }

				// Check for too many files
				$i++;
				if($i > $config['max_images']) { error($config['error']['toomanyimages']); }

				// Check the file's extension.
				$file_internal_name = urldecode($file['name']);
				$file_extension = strtolower(mb_substr($file_internal_name, mb_strrpos($file_internal_name, '.') + 1));
				if(!in_array($file_extension, $config['allowed_ext']) && !in_array($file_extension, $config['allowed_ext_files'])) {
					error($config['error']['unknownext']);
				}

				// And the filesize.
				$total_filesize += $file['size'];
				if($total_filesize > $config['max_filesize']) {
					error(sprintf3($config['error']['filesize'], array(
						'sz' => number_format($total_filesize),
						'filesz' => number_format($total_filesize),
						'maxsz' => number_format($config['max_filesize'])
					)));
				}

				// Now we can check, is it an image?
				$file['is_an_image'] = !in_array($file_extension, $config['allowed_ext_files']);
				if($file['is_an_image']) {
					// use kara's image processing instead.
					$kara_image = karachan_handle_image($file['tmp_name'], $file_extension);
					$file['file'] = $kara_image['hash'] . ($file_extension == 'gif' ? '.gif' : '.jpg');
					$file['width'] = $kara_image['width']; $file['height'] = $kara_image['height'];
					$file['thumbwidth'] = $kara_image['thumb_width']; $file['thumbheight'] = $kara_image['thumb_height'];
					$post['filehash'] .= $kara_image['hash'];

					// Let's OCR it!
					if($config['tesseract_ocr']) {
						try {
							$txt = ocr_image($config, $fname);
							if($txt !== '') {
								// This one has an effect, that the body is appended to a post body. So you can write a correct spamfilter.
								$post['body_nomarkup'] .= "<mephboard ocr image $key>" . htmlspecialchars($txt) . "</mephboard>";
							}
						} catch (RuntimeException $e) {
							$context->get(LogDriver::class)->log(LogDriver::ERROR, "Could not OCR image: {$e->getMessage()}");
						}
					}
				} else {
					// not an image, it's a file.
					$filehash_internal = md5_file($file['tmp_name']);
					$file['file'] = $filehash_internal . '.' . $file_extension;
					$post['filehash'] .= $filehash_internal;
					if(!@move_uploaded_file($file['tmp_name'], 'stnk/' . $file['file'])) { error($config['error']['nomove']); }
				}

				// Now it's fully prepared.
				$post['files'][] = $file;
				$i++;
			}
		}
		$post['filesize'] = $total_filesize;
		if(empty($post['files'])) { // Files
			$post['has_file'] = false;
			if($post['op'] && !isset($post['no_longer_require_an_image_for_op']) && $config['force_image_op']) {
				error($config['error']['noimage']);
			}
		}
	}

	// body
	$post['body_nomarkup'] = $post['body'];
	$post['tracked_cites'] = markup($post['body'], true);

	// handle post filters
	if(!hasPerm('bypass_filters')) {
		require_once 'inc/filters.php';
		do_filters($post, $ip);
	}

	// Almost there
	$post['num_files'] = sizeof($post['files']);
	$post['id'] = $id = post($post);
	insertFloodPost($post);

	// Handle cyclical threads
	if(!$post['op'] && isset($thread['cycle']) && $thread['cycle']) { delete_cyclical_posts($board['uri'], $post['thread'], $config['cycle_limit']); }
	if(isset($post['tracked_cites']) && !empty($post['tracked_cites'])) {
		$insert_rows = array();
		foreach($post['tracked_cites'] as $cite) {
			$insert_rows[] = '(' . $pdo->quote($board['uri']) . ', ' . (int)$id . ', ' . $pdo->quote($cite[0]) . ', ' . (int)$cite[1] . ')';
		}
		query('INSERT INTO ``cites`` VALUES ' . implode(', ', $insert_rows)) or error(db_error());
	}
	if(!$post['op'] && !$post['pamped'] && !$thread['pamped'] && ($config['reply_limit'] == 0 || $numposts['replies']+1 < $config['reply_limit'])) {
		bumpThread($post['thread']);
	}

	// redirect to board
	$redirect = (isset($kara_user) ? '/' . $config['file_account'] . '?/' : '/') . $board['dir'] . getThreadFileLink($post) . (!$post['op'] ? '#' . $id : '');

	// build the thread HTML (precache) and log
	buildThreadFilesFunction($post['op'] ? $id : $post['thread']);
	$context->get(LogDriver::class)->log(LogDriver::INFO, 'New post: /' . $board['dir'] . getThreadFileLink($post) . (!$post['op'] ? '#' . $id : ''));
	if(!isset($_POST['json_response'])) {
		header('Location: ' . $redirect, true, $config['redirect_http']);
	} else {
		header('Content-Type: text/json; charset=utf-8');
		echo json_encode(array(
			'redirect' => $redirect,
			'noko' => true,
			'id' => $id
		));
	}

	// We are already done, let's continue our heavy-lifting work in the background (if we run off FastCGI)
	if(function_exists('fastcgi_finish_request')) { @fastcgi_finish_request(); }
	if($post['op']) { autocleanThreadsMaxpages($id); if($config['try_smarter']) { $build_pages = range(1, $config['max_pages']); } }
	buildBoardIndexAndPages(true);

	// [Pearl]
	pearl_notify_discord(
		'**New ' . (!$post['op'] ? 'post' : 'thread') . '**: ' . $config['domain'] . '/' . $config['file_account'] . '?/' . $post['board'] . '/' . getThreadFileLink($post) . (!$post['op'] ? '#' . $id : ''),
		$config['discord_webhook_posts']
	);
} elseif(isset($_POST['appeal'])) {
	if(!isset($_POST['ban_id'])) { error($config['error']['bot']); }

	$ban_id = (int)$_POST['ban_id'];
	$ban = Bans::findSingle($ip, $ban_id, $config['require_ban_view'], $config['auto_maintenance']);

	if(empty($ban)) { error($config['error']['noban']); }
	if($ban['expires'] && $ban['expires'] - $ban['created'] <= $config['ban_appeals_min_length']) { error($config['error']['tooshortban']); }

	$query = query("SELECT `denied` FROM ``ban_appeals`` WHERE `ban_id` = $ban_id") or error(db_error());
	$ban_appeals = $query->fetchAll(PDO::FETCH_COLUMN);

	if(count($ban_appeals) >= $config['ban_appeals_max']) { error($config['error']['toomanyappeals']); }
	foreach($ban_appeals as $is_denied) {
		if(!$is_denied) {
			error($config['error']['pendingappeal']);
		}
	}

	if(strlen($_POST['appeal']) > $config['ban_appeal_max_chars']) { error($config['error']['toolongappeal']); }

	$query = prepare("INSERT INTO ``ban_appeals`` VALUES (NULL, :ban_id, :time, :message, 0)");
	$query->bindValue(':ban_id', $ban_id, PDO::PARAM_INT);
	$query->bindValue(':time', time(), PDO::PARAM_INT);
	$query->bindValue(':message', $_POST['appeal']);
	$query->execute() or error(db_error($query));

	displayBan($ban);
} else {
	error($config['error']['nopost']);
}