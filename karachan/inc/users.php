<?php

use Karachan\Context;

defined('MEPHBOARD') or exit;

function hasPerm($action = null, $_kara_user = null) {
	global $config;
	if (isset($_kara_user)) { $kara_user = &$_kara_user; }
	else { global $kara_user; }
	if (is_array($kara_user) && isset($action) && $kara_user['permissions'] & $config['permissions'][$action]) { return true; }
	return false;
}

// create a hash/salt pair for validate logins
function mkhash(string $username, $password = null, $salt = false) {
	global $config;

	if (!$salt) {
		// create some sort of salt for the hash
		$salt = substr(base64_encode(sha1(rand() . time(), true) . $config['cookies']['salt']), 0, 15);
		$generated_salt = true;
	}

	// generate hash (method is not important as long as it's strong)
	$hash = substr(
		base64_encode(
			md5(
				$username . $config['cookies']['salt'] . sha1($username . $password . $salt, true) . sha1($config['password_crypt_version']) // Log out users being logged in with older password encryption schema
				, true
			)
		), 0, 20
	);

	if (isset($generated_salt)) {
		return [ $hash, $salt ];
	} else {
		return $hash;
	}
}

function crypt_password(string $password): array {
	global $config;
	// `salt` database field is reused as a version value. We don't want it to be 0.
	$version = $config['password_crypt_version'] ? $config['password_crypt_version'] : 1;
	$new_salt = generate_salt();
	$password = crypt($password, $config['password_crypt'] . $new_salt . "$");
	return [ $version, $password ];
}

function test_password(string $password, string $salt, string $test): array {
	// Version = 0 denotes an old password hashing schema. In the same column, the
	// password hash was kept previously
	$version = strlen($salt) <= 8 ? (int)$salt : 0;

	if ($version == 0) {
		$comp = hash('sha256', $salt . sha1($test));
	} else {
		$comp = crypt($test, $password);
	}
	return [ $version, hash_equals($password, $comp) ];
}

function generate_salt(): string {
	return strtr(base64_encode(random_bytes(16)), '+', '.');
}

function calc_cookie_name(bool $is_https, bool $is_path_jailed, string $base_name): string {
	if ($is_https) {
		if ($is_path_jailed) {
			return "__Host-$base_name";
		} else {
			return "__Secure-$base_name";
		}
	} else {
		return $base_name;
	}
}

function login(string $username, string $password) {
	global $kara_user, $config;

	$query = prepare("SELECT `id`, `permissions`, `password`, `version` FROM ``karausers`` WHERE BINARY `username` = :username");
	$query->bindValue(':username', $username);
	$query->execute() or error(db_error($query));

	if (strlen($password) <= 64) {
		if ($user = $query->fetch(PDO::FETCH_ASSOC)) {
			list($version, $ok) = test_password($user['password'], $user['version'], $password);
			if ($ok) {
				if ($config['password_crypt_version'] > $version) {
					// It's time to upgrade the password hashing method!
					list ($user['version'], $user['password']) = crypt_password($password);
					$query = prepare("UPDATE ``karausers`` SET `password` = :password, `version` = :version WHERE `id` = :id");
					$query->bindValue(':password', $user['password']);
					$query->bindValue(':version', $user['version']);
					$query->bindValue(':id', $user['id']);
					$query->execute() or error(db_error($query));
				}

				if (!($user['permissions'] & ($config['permissions']['accountapproved'] | $config['permissions']['editusers']))) {
					error("Your account is not approved.");
				}

				return $kara_user = [
					'id' => $user['id'],
					'permissions' => $user['permissions'],
					'username' => $username,
					'hash' => mkhash($username, $user['password'])
				];
			}
		}
	}

	return false;
}

function setCookies(): void {
	global $kara_user, $config;
	if (!$kara_user) { error('setCookies() was called for a non-user!'); }

	$is_https = is_connection_secure($config['cookies']['secure_login_only'] === 1);
	$is_path_jailed = $config['cookies']['jail'];
	$name = calc_cookie_name($is_https, $is_path_jailed, 'karachan_session');

	// <username>:<password>:<salt>
	$value = "{$kara_user['username']}:{$kara_user['hash'][0]}:{$kara_user['hash'][1]}";

	$options = [
		'expires' => time() + $config['cookies']['expire'],
		'path' => $is_path_jailed ? $config['cookies']['path'] : '/',
		'secure' => $is_https,
		'httponly' => $config['cookies']['httponly'],
		'samesite' => 'Strict'
	];

	setcookie($name, $value, $options);
}

function destroyCookies(): void {
	global $config;
	$base_name = 'karachan_session';
	$del_time = time() - 60 * 60 * 24 * 365; // 1 year.
	$jailed_path = $config['cookies']['jail'] ? $config['cookies']['path'] : '/';
	$http_only = $config['cookies']['httponly'];

	$options_multi = [
		$base_name => [
			'expires' => $del_time,
			'path' => $jailed_path ,
			'secure' => false,
			'httponly' => $http_only,
			'samesite' => 'Strict'
		],
		"__Host-$base_name" => [
			'expires' => $del_time,
			'path' => $jailed_path,
			'secure' => true,
			'httponly' => $http_only,
			'samesite' => 'Strict'
		],
		"__Secure-$base_name" => [
			'expires' => $del_time,
			'path' => '/',
			'secure' => true,
			'httponly' => $http_only,
			'samesite' => 'Strict'
		]
	];

	foreach ($options_multi as $name => $options) {
		if (isset($_COOKIE[$name])) {
			setcookie($name, 'deleted', $options);
			unset($_COOKIE[$name]);
		}
	}
}

function modLog(string $action, ?string $_board = null): void {
	global $kara_user, $board, $config;
	$query = prepare("INSERT INTO ``modlogs`` VALUES (:id, :ip, :board, :time, :text)");
	$query->bindValue(':id', (isset($kara_user['id']) ? $kara_user['id'] : -1), PDO::PARAM_INT);
	$query->bindValue(':ip', pearl_get_real_ip());
	$query->bindValue(':time', time(), PDO::PARAM_INT);
	$query->bindValue(':text', $action);
	$rb = isset($kara_user['username']) ? $kara_user['username'] : 'System';
	if (isset($_board)) { $query->bindValue(':board', $_board); $rb .= ' (' . $_board . ')'; }
	elseif (isset($board)) { $query->bindValue(':board', $board['uri']); $rb .= ' (' . $board['uri'] . ')'; }
	else { $query->bindValue(':board', null, PDO::PARAM_NULL); }
	$query->execute() or error(db_error($query));
	pearl_notify_discord('**' . $rb . ':** ' . $action, $config['discord_webhook_moderation']);
}

function make_secure_link_token(string $uri): string {
	global $kara_user, $config;
	return substr(sha1($config['cookies']['salt'] . '-' . $uri . '-' . $kara_user['id']), 0, 8);
}

function check_login(Context $ctx, bool $prompt = false): void {
	global $config, $kara_user;

	$is_https = is_connection_secure($config['cookies']['secure_login_only'] === 1);
	$is_path_jailed = $config['cookies']['jail'];
	$expected_cookie_name = calc_cookie_name($is_https, $is_path_jailed, 'karachan_session');

	// Validate session
	if (isset($_COOKIE[$expected_cookie_name])) {
		// Should be username:hash:salt

		$cookie = explode(':', $_COOKIE[$expected_cookie_name]);
		if (count($cookie) != 3) {
			// Malformed cookies
			destroyCookies();
			if ($prompt) { mod_login($ctx); }
			exit;
		}

		$query = prepare("SELECT `id`, `permissions`, `password` FROM ``karausers`` WHERE `username` = :username");
		$query->bindValue(':username', $cookie[0]);
		$query->execute() or error(db_error($query));
		$user = $query->fetch(PDO::FETCH_ASSOC);

		// validate password hash
		if ($cookie[1] !== mkhash($cookie[0], $user['password'], $cookie[2])) {
			// Malformed cookies
			destroyCookies();
			if ($prompt) { mod_login($ctx); }
			exit;
		}

		$kara_user = array(
			'id' => (int)$user['id'],
			'permissions' => (int)$user['permissions'],
			'username' => $cookie[0]
		);
	}
}

// collect permissions from get
function collect_permissions_from_request($set_permissions) {
	global $config;
	foreach($config['permissions'] as $perm_name => $perm_flag) {
		$key = 'perm_' . $perm_name;
		if(!empty($_POST[$key])) {
			$set_permissions |= $perm_flag;
		}
	}
	return $set_permissions;
}

// very simple function to add users.
function add_new_user_quick($name, $password, $permissions) {
	global $pdo, $config;

	// check if username already exists
	$query = prepare('SELECT 1 FROM ``karausers`` WHERE `username` = :username LIMIT 1');
	$query->bindValue(':username', $name);
	$query->execute() or error(db_error($query));
	if($query->fetch()) {
		return '<li>' . sprintf('User "%s" already exists.', $name) . '</li>';
	}

	// no? create new moderator.
	list($version, $encrypted_password) = crypt_password($password);
	$query = prepare('INSERT INTO ``karausers`` VALUES (NULL, :username, :password, :version, :permissions)');
	$query->bindValue(':username', $name);
	$query->bindValue(':password', $encrypted_password);
	$query->bindValue(':version', $version);
	$query->bindValue(':permissions', $permissions);
	$query->execute() or error(db_error($query));
	$userID = $pdo->lastInsertId();
	modLog('Created a new user: ' . utf8tohtml($name) . ' <small>(#' . $userID . ')</small>');
	return '<li>Created a new user: ' . utf8tohtml($name) . ' <small>(#' . $userID . ')</small></li>';
}

function mod_login(Context $ctx, $redirect = false) {
	$config = $ctx->get('config');
	$args = [];
	$secure_login_mode = $config['cookies']['secure_login_only'];
	if($secure_login_mode !== 0 && !is_connection_secure($secure_login_mode === 1)) {
		$args['error'] = $config['error']['insecure'];
	} elseif (isset($_POST['login'])) {
		// Check if inputs are set and not empty
		if(!isset($_POST['username'], $_POST['password']) || $_POST['username'] == '' || $_POST['password'] == '') {
			$args['error'] = $config['error']['invalid'];
		} elseif (!login($_POST['username'], $_POST['password'])) {
			$args['error'] = $config['error']['invalid'];
		} else {
			modLog('Logged in');

			// Login successful
			// Set cookies
			setCookies();

			if($redirect) { header('Location: ?' . $redirect, true, $config['redirect_http']); }
			else { header('Location: ?/', true, $config['redirect_http']); }
		}
	} elseif (isset($_POST['signup'])) {
		if($config['allow_signups']) {
			$query = prepare("SELECT 1 FROM ``posts_ot`` WHERE ip = ?");
			$query->execute([pearl_get_real_ip()]) or error(db_error($query));
			$pearlsec = $query->fetch(PDO::FETCH_ASSOC);
			if($pearlsec) {
				$sanitizedUsername = '';
				if(isset($_POST['username'])) { if(preg_match('/\A[a-zA-Z0-9_]{3,32}\z/', $_POST['username'])) { $sanitizedUsername = $_POST['username']; } }

				$sanitizedPassword = '';
				if(isset($_POST['password'])) { $len=mb_strlen($_POST['password']??''); if($len >= 8 && $len <= 30) { $sanitizedPassword = $_POST['password']; } }

				$sanitizedReason = '(no reason provided)';
				if(isset($_POST['reason']) && $_POST['reason'] != '') { $sanitizedReason = clean_input($_POST['reason']); }

				// sanitize these entries first.
				if($sanitizedUsername == '' || $sanitizedPassword == '') {
					$args['error'] = $config['error']['invalid'];
				} else {
					modLog($sanitizedUsername . ' wants to sign up for: ' . $sanitizedReason);
					add_new_user_quick($sanitizedUsername, $sanitizedPassword, 0);
					$args['error'] = $config['error']['pendingreg'];
				}
			} else {
				$args['error'] = $config['error']['bot'];
			}
		} else {
			$args['error'] = $config['error']['nregistration'];
		}
	}
	if(isset($_POST['username'])) { $args['username'] = $_POST['username']; }
	showUserPage('Login', $config['file_mod_login'], $args);
}