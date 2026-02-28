<?php
// Karachan's topbar script and maintenance stuff.
require_once 'inc/bootstrap.php';

// karachan integrity scripts
if(isset($_POST['vv']) && $_POST['vv'] === 'karachan') {
	// legitimate enough, go on!
	$ip = pearl_get_real_ip();
	$data = [];

	// js variable list (nails)
	$flags = ['dsc', 'mobile', 'txl', 'vpn', 'cg'];
	$othervars = ['wm', 'hm', 'wi', 'hi'];

	// sanitize incoming
	foreach($flags as $flag) { $data[$flag] = isset($_POST[$flag]) && $_POST[$flag] == 1 ? 1 : 0; }
	foreach($othervars as $var){ $data[$var] = (isset($_POST[$var]) && ctype_digit($_POST[$var])) ? (int)$_POST[$var] : 0; }
	$data['kp'] = substr(isset($_POST['kp']) && is_string($_POST['kp']) && $_POST['kp'] !== '' ? $_POST['kp'] : 'NO-KP', 0, 16);

	// clear old values
	$stmt = prepare("DELETE FROM ``pearl_extras`` WHERE created_at < NOW() - INTERVAL 3 HOUR");
	$stmt->execute();

	// fetch existing values (if any)
	$query = prepare("SELECT discord, mobile, tuxler, vpn, incognito FROM ``pearl_extras`` WHERE ip = ?");
	$query->execute([$ip]);
	$existing = $query->fetch(PDO::FETCH_ASSOC);

	// if it exists, merge to keep any 1s
	if($existing) {
		foreach($flags as $flag) {
			if($existing[$flag] == 1) { $data[$flag] = 1; } // once true, stays true
		}

		// then either update or insert
		$stmt = prepare("UPDATE ``pearl_extras`` SET discord = ?, mobile = ?, tuxler = ?, vpn = ?, incognito = ?, wm = ?, hm = ?, wi = ?, hi = ?, kp = ? WHERE ip = ?");
		$stmt->execute([
			$data['dsc'], $data['mobile'], $data['txl'], $data['vpn'], $data['cg'],
			$data['wm'], $data['hm'], $data['wi'], $data['hi'], $data['kp'],
			$ip
		]);
	} else {
		$stmt = prepare("INSERT INTO ``pearl_extras`` (ip, discord, mobile, tuxler, vpn, incognito, wm, hm, wi, hi, kp) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
		$stmt->execute([
			$ip,
			$data['dsc'], $data['mobile'], $data['txl'], $data['vpn'], $data['cg'],
			$data['wm'], $data['hm'], $data['wi'], $data['hi'], $data['kp']
		]);
	}
}

// Top bar
$topbar=[
	[
		["name"=>"home","link"=>"/","title"=>""],
		["name"=>"rules","link"=>"/rules.html","title"=>"site rules"]
	],
	[
		["name"=>"account","link"=>"/account.php","title"=>"your account"]
	]
];

// build dynamic groups
$publicBoards = [];
$userBoards = [];
foreach(getAllBoardByURIs() as $_board){
	// skip private boards
	if(in_array($_board['uri'], $config['private_boards'])) { continue; }

	// build details
	$uri = $_board['uri'];
	$isUserOwned = $_board['owner'] > 0;
	$name=$isUserOwned ? "u/{$uri}" : $uri;
	$link="/{$uri}/index.html";
	$item=["name" => $name, "link" => $link, "title" => $_board['title']];
	if($isUserOwned) { $userBoards[]=$item; } else { $publicBoards[]=$item; }
}

// append as separate groups if they exist
if(!empty($publicBoards)) { $topbar[] = $publicBoards; }
if(!empty($userBoards)) { $topbar[] = $userBoards; }


header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=300, s-maxage=300, stale-while-revalidate=86400');
echo json_encode($topbar, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);