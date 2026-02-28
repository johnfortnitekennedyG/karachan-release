<?php

/**
 * @param bool $trust_headers. If true, trust the `HTTP_X_FORWARDED_PROTO` header to check if the connection is HTTPS.
 * @return bool Returns if the client-server connection is an encrypted one (HTTPS).
 */
function is_connection_secure(bool $trust_headers): bool {
	if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
		return true;
	} elseif ($trust_headers && isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
		return true;
	}
	return false;
}

function pearl_get_real_ip() {
	if(isset($_SERVER['HTTP_CF_CONNECTING_IP'])) {
		return $_SERVER['HTTP_CF_CONNECTING_IP'];
	} elseif(isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
		// this might be a list: client, proxy1, proxy2, ...
		return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
	} elseif(isset($_SERVER['REMOTE_ADDR'])) {
		return $_SERVER['REMOTE_ADDR'];
	}
	return '0.0.0.0';
}

function ipv4to6($ip) {
	if (strpos($ip, ':') !== false) {
		if (strpos($ip, '.') > 0) { $ip = substr($ip, strrpos($ip, ':')+1); }
		else { return $ip; }  //native ipv6
	}
	$iparr = array_pad(explode('.', $ip), 4, 0);
	$part7 = base_convert(($iparr[0] * 256) + $iparr[1], 10, 16);
	$part8 = base_convert(($iparr[2] * 256) + $iparr[3], 10, 16);
	return '::ffff:'.$part7.':'.$part8;
}

function rDNS($ip_addr) {
	global $config;
	if ($config['cache']['enabled'] && ($host = cache::get('rdns_' . $ip_addr))) { return $host; }
	if (!$config['dns_system']) { $host = gethostbyaddr($ip_addr); } else {
		$resp = shell_exec_error('host -W 3 ' . $ip_addr);
		if (preg_match('/domain name pointer ([^\s]+)$/', $resp, $m)) { $host = $m[1]; }
		else { $host = $ip_addr; }
	}

	$isip = filter_var($host, FILTER_VALIDATE_IP);
	if ($config['fcrdns'] && !$isip && DNS($host) != $ip_addr) { $host = $ip_addr; }
	if ($config['cache']['enabled']) { cache::set('rdns_' . $ip_addr, $host); }
	return $host;
}

function DNS($host) {
	global $config;
	if ($config['cache']['enabled'] && ($ip_addr = cache::get('dns_' . $host))) { return $ip_addr != '?' ? $ip_addr : false; }

	if (!$config['dns_system']) {
		$ip_addr = gethostbyname($host);
		if ($ip_addr == $host) { $ip_addr = false; }
	} else {
		$resp = shell_exec_error('host -W 1 ' . $host);
		if (preg_match('/has address ([^\s]+)$/', $resp, $m)) { $ip_addr = $m[1]; }
		else { $ip_addr = false; }
	}

	if ($config['cache']['enabled']) { cache::set('dns_' . $host, $ip_addr !== false ? $ip_addr : '?'); }
	return $ip_addr;
}

function checkDNSBL() {
	global $config;
	$ip = pearl_get_real_ip();
	if (strstr($ip, ':') !== false) { return; } // No IPv6 support yet.
	if (preg_match("/^(::(ffff:)?)?(127\.|192\.168\.|10\.|172\.(1[6-9]|2[0-9]|3[0-1])\.|0\.|255\.)/", $ip)) { return; } // It's pointless to check for local IP addresses in dnsbls, isn't it?
	if (in_array($ip, $config['dnsbl_exceptions'])) { return; }

	//reverse ip octects
	$ipaddr = implode('.', array_reverse(explode('.', $ip)));

	foreach ($config['dnsbl'] as $blacklist) {
		if (!is_array($blacklist)) { $blacklist = array($blacklist); }
		if (($lookup = str_replace('%', $ipaddr, $blacklist[0])) == $blacklist[0]) { $lookup = $ipaddr . '.' . $blacklist[0]; }
		if (!$ip = DNS($lookup)) { continue; } // not in list
		$blacklist_name = isset($blacklist[2]) ? $blacklist[2] : $blacklist[0];
		if (!isset($blacklist[1])) {
			// If you're listed at all, you're blocked.
			error(sprintf($config['error']['dnsbl'], $blacklist_name));
		} elseif (is_array($blacklist[1])) {
			foreach ($blacklist[1] as $octet) {
				if ($ip == $octet || $ip == '127.0.0.' . $octet) { error(sprintf($config['error']['dnsbl'], $blacklist_name)); }
			}
		} elseif (is_callable($blacklist[1])) {
			if ($blacklist[1]($ip)) { error(sprintf($config['error']['dnsbl'], $blacklist_name)); }
		} else {
			if ($ip == $blacklist[1] || $ip == '127.0.0.' . $blacklist[1]) { error(sprintf($config['error']['dnsbl'], $blacklist_name)); }
		}
	}
}