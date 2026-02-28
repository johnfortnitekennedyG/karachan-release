<?php

// Karachan security modules
function pearl_notify_discord($message, $hook) {
	if($hook != '') {
		$message = escapeshellarg(json_encode(["content" => $message]));
		$hook = escapeshellarg($hook);
		exec("curl -X POST -H 'Content-Type: application/json' -d $message $hook > /dev/null 2>&1 &");
	}
}

// function to encode something using base32
function base32_encode($d) {
	$charset = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
	$b = implode('', array_map(function($c) { return sprintf("%08b", ord($c)); }, str_split($d)));
	return implode('', array_map(function($c) use ($charset) { return $charset[bindec($c)]; }, str_split($b, 5)));
}

// generate a short hash (12 chars) using sha1 and take first few chars
function pearl_hide_info($str) {
	return substr(sha1($str), 0, 12);
}

// and the decoder
function base32_decode($d) {
	$charset = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
	$d = str_split($d);
	$l = array_pop($d);
	$b = '';
	foreach ($d as $c) { $b .= sprintf("%05b", strpos($charset, $c)); }
	$padding = 8 - strlen($b) % 8;
	$b .= str_pad(decbin(strpos($charset, $l)), $padding, '0', STR_PAD_LEFT);
	return implode('', array_map(function($c) { return chr(bindec($c)); }, str_split($b, 8)));
}

// IP cloak (prevents ip leaks)
function cloak_ip($ip, $embed_prefix = 1) {
	global $config;
	$ipcrypt_key = $config["ipcrypt_key"] ? : null;
	if(empty($config["ipcrypt_key"])) { return $ip; }

	$ip_dec = inet_pton($ip);
	if(is_numeric($ip)) { $ipbytes = pack("N", $ip); } elseif($ip_dec !== false) { $ipbytes = $ip_dec; } else { return "#ERROR"; }
	if(strlen($ipbytes) >= 16) { $ipbytes = substr($ipbytes, 0, 16); }
	$cyphertext = @openssl_encrypt($ipbytes, "aes-256-ctr", $config["ipcrypt_key"], OPENSSL_RAW_DATA);
	$ret = ($embed_prefix ? $config["ipcrypt_prefix"] . ":" : "") . base32_encode($cyphertext);
	if(isset($tld) && !empty($tld)) { $ret .= "." . $tld; }
	return $ret;
}

function uncloak_ip($ip) {
	global $config;
	if(empty($config["ipcrypt_key"])) { return $ip; }

	$juice = substr($ip, strlen($config["ipcrypt_prefix"]) + 1);
	if($delimiter = strpos($juice, ".")) { $juice = substr($juice, 0, $delimiter); }
	if(substr($ip, 0, strlen($config["ipcrypt_prefix"]) + 1) === $config["ipcrypt_prefix"] . ":") {
		$plaintext = openssl_decrypt(base32_decode($juice), "aes-256-ctr", $config["ipcrypt_key"], OPENSSL_RAW_DATA);
		if($plaintext === false || strlen($plaintext) == 0) { return "#ERROR"; }
		if(strlen($ip) >= 16) { return inet_ntop($plaintext); }
		return long2ip(unpack("N", $plaintext)[1]);
	}
	return "#ERROR";
}


// cloak masks
function cloak_mask($mask) {
	list($net, $block) = array_pad(explode('/', $mask, 2), 2, null);
	$mask = cloak_ip($net);
	if ($block) { $mask .= '/'.$block; }

	return $mask;
}

function uncloak_mask($mask) {
	list($addr, $block) = array_pad(explode('/', $mask, 2), 2, null);
	$mask = uncloak_ip($addr);
	if ($mask === '#ERROR') { $mask = $addr; }
	if ($block) { $mask .= '/'.$block; }
	return $mask;
}

// passwords
function hashPassword($password) {
	global $config;
	return hash('sha3-256', $password . $config['secure_password_salt']);
}

// ban handling
function displayBan($ban) {
	global $config, $board;

	if (!$ban['seen']) {
		Bans::seen($ban['id']);
	}

	$ban['ip'] = pearl_get_real_ip();

	if ($ban['post'] && isset($ban['post']['board'], $ban['post']['id'])) {
		if (openBoard($ban['post']['board'])) {
			$query = query(sprintf("SELECT `files` FROM ``posts_%s`` WHERE `id` = " . (int)$ban['post']['id'], $board['uri']));
			if ($_post = $query->fetch(PDO::FETCH_ASSOC)) { $ban['post'] = array_merge($ban['post'], $_post); }
		}
		if ($ban['post']['thread']) {
			$post = new Post($ban['post']);
		} else {
			$post = new Thread($ban['post'], null, false, false);
		}
	}

	$denied_appeals = array();
	$pending_appeal = false;

	if ($config['ban_appeals']) {
		$query = query("SELECT `time`, `denied` FROM ``ban_appeals`` WHERE `ban_id` = " . (int)$ban['id']) or error(db_error());
		while ($ban_appeal = $query->fetch(PDO::FETCH_ASSOC)) {
			if ($ban_appeal['denied']) {
				$denied_appeals[] = $ban_appeal['time'];
			} else {
				$pending_appeal = $ban_appeal['time'];
			}
		}
	}

	// Show banned page and exit
	die(
		Element($config['file_static_page'], array(
			'title' => 'Banned!',
			'config' => $config,
			'body' => Element($config['file_banned'], array(
				'config' => $config,
				'ban' => $ban,
				'board' => $board,
				'post' => isset($post) ? $post->build(true) : false,
				'denied_appeals' => $denied_appeals,
				'pending_appeal' => $pending_appeal
			)
		))
	));
}

// are you banned?
function checkBan() {
	global $config;
	$ip = pearl_get_real_ip();
	$bans = Bans::find($ip, true, null, $config['auto_maintenance']);

	foreach ($bans as &$ban) {
		if ($ban['expires'] && $ban['expires'] < time()) {
			if ($config['auto_maintenance']) { Bans::delete($ban['id']); }
			if ($config['require_ban_view'] && !$ban['seen']) {
				if (!isset($_POST['json_response'])) { displayBan($ban); } else {
					header('Content-Type: text/json');
					die(json_encode(array('error' => true, 'banned' => true)));
				}
			}
		} else {
			if (!isset($_POST['json_response'])) { displayBan($ban); } else {
				header('Content-Type: text/json');
				die(json_encode(array('error' => true, 'banned' => true)));
			}
		}
	}

	if ($config['auto_maintenance']) {
		// I'm not sure where else to put this. It doesn't really matter where; it just needs to be called every
		// now and then to keep the ban list tidy.
		if ($config['cache']['enabled']) {
			$last_time_purged = cache::get('purged_bans_last');
			if ($last_time_purged !== false && time() - $last_time_purged > $config['purge_bans']) {
				Bans::purge($config['require_ban_view'], $config['purge_bans']);
				cache::set('purged_bans_last', time());
			}
		} else {
			// Purge every time.
			Bans::purge($config['require_ban_view'], $config['purge_bans']);
		}
	}
}