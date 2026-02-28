<?php

/*
	joaoptm78@gmail.com
	http://www.php.net/manual/en/function.filesize.php#100097
*/
function format_bytes($size) {
	$units = array(' B', ' KB', ' MB', ' GB', ' TB');
	for ($i = 0; $size >= 1024 && $i < 4; $i++) $size /= 1024;
	return round($size, 2).$units[$i];
}

// cleans up input
function clean_input($str) {
	return htmlspecialchars(strip_tags($str), ENT_QUOTES, 'UTF-8');
}

// timestamp formatting
function format_timestamp(int $delta): string {
	if($delta < 60) {
		$num = $delta;
		$unit = 'second';
	} elseif($delta < 3600) {
		$num = round($delta / 60);
		$unit = 'minute';
	} elseif($delta < 86400) {
		$num = round($delta / 3600);
		$unit = 'hour';
	} elseif($delta < 604800) {
		$num = round($delta / 86400);
		$unit = 'day';
	} elseif($delta < 31536000) {
		$num = round($delta / 604800);
		$unit = 'week';
	} else {
		$num = round($delta / 31536000);
		$unit = 'year';
	}

	// add an s if not singular
	return $num . ' ' . $unit . ($num == 1 ? '' : 's');
}

function until(int $timestamp): string {
	return format_timestamp($timestamp - time());
}

function ago(int $timestamp): string {
	return format_timestamp(time() - $timestamp);
}

function sprintf3($str, $vars, $delim = '%') {
	$replaces = array();
	foreach ($vars as $k => $v) {
		$replaces[$delim . $k . $delim] = $v;
	}
	return str_replace(array_keys($replaces),
					   array_values($replaces), $str);
}

function mb_substr_replace($string, $replacement, $start, $length) {
	return mb_substr($string, 0, $start) . $replacement . mb_substr($string, $start + $length);
}

function quote($body, $quote=true) {
	global $config;
	$body = str_replace('<br/>', "\n", $body);
	$body = strip_tags($body);
	$body = preg_replace("/(^|\n)/", '$1&gt;', $body);
	$body .= "\n";
	if ($config['minify_html']) { $body = str_replace("\n", '&#010;', $body); }
	return $body;
}

function markup_url($matches) {
	global $config, $markup_urls;
	$url = $matches[1];
	$after = $matches[2];
	$markup_urls[] = $url;
	$link = (object) array(
		'href' => $config['link_prefix'] . $url,
		'text' => $url,
		'rel' => 'nofollow',
		'target' => '_blank',
	);
	$link = (array)$link;
	$parts = array();
	foreach ($link as $attr => $value) {
		if ($attr == 'text' || $attr == 'after') { continue; }
		$parts[] = $attr . '="' . $value . '"';
	}
	if (isset($link['after'])) { $after = $link['after'] . $after; }
	return '<a ' . implode(' ', $parts) . '>' . $link['text'] . '</a>' . $after;
}

function unicodify($body) {
	$body = str_replace('...', '&hellip;', $body);
	$body = str_replace('&lt;--', '&larr;', $body);
	$body = str_replace('--&gt;', '&rarr;', $body);

	// En and em- dashes are rendered exactly the same in
	// most monospace fonts (they look the same in code
	// editors).
	$body = str_replace('---', '&mdash;', $body); // em dash
	$body = str_replace('--', '&ndash;', $body); // en dash
	return $body;
}

function extract_modifiers($body) {
	$modifiers = array();
	if (preg_match_all('@<mephboard ([\w\s]+)>(.*?)</mephboard>@us', $body, $matches, PREG_SET_ORDER)) {
		foreach ($matches as $match) {
			if (preg_match('/^escape /', $match[1])) { continue; }
			$modifiers[$match[1]] = html_entity_decode($match[2]);
		}
	}
	return $modifiers;
}

function remove_modifiers($body) {
	return $body ? preg_replace('@<mephboard ([\w\s]+)>(.+?)</mephboard>@usm', '', $body) : null;
}

function markup(&$body, $track_cites = false, $op = false) {
	global $board, $config, $markup_urls;

	$modifiers = extract_modifiers($body);

	$body = preg_replace('@<mephboard (?!escape )([\w\s]+)>(.+?)</mephboard>@us', '', $body);
	$body = preg_replace('@<(mephboard) escape ([\w\s]+)>@i', '<$1 $2>', $body);

	if (isset($modifiers['raw html']) && $modifiers['raw html'] == '1') { return array(); }

	$body = str_replace("\r", '', $body);
	$body = utf8tohtml($body);

	if ($config['markup_code']) {
		$code_markup = array();
		$body = preg_replace_callback($config['markup_code'], function($matches) use (&$code_markup) {
			$d = count($code_markup);
			$code_markup[] = $matches;
			return "<code $d>";
		}, $body);
	}

	foreach ($config['markup'] as $markup) {
		if (is_string($markup[1])) {
			$body = preg_replace($markup[0], $markup[1], $body);
		} elseif (is_callable($markup[1])) {
			$body = preg_replace_callback($markup[0], $markup[1], $body);
		}
	}

	if ($config['markup_urls']) {
		$markup_urls = array();
		$body = preg_replace_callback(
				'/((?:https?:\/\/|ftp:\/\/|irc:\/\/)[^\s<>()"]+?(?:\([^\s<>()"]*?\)[^\s<>()"]*?)*)((?:\s|<|>|"|\.||\]|!|\?|,|&#44;|&quot;)*(?:[\s<>()"]|$))/',
				'markup_url',
				$body,
				-1,
				$num_links);

		if ($num_links > $config['max_links']) { error($config['error']['toomanylinks']); }
	}

	if ($config['markup_repair_tidy']) { $body = str_replace('  ', ' &nbsp;', $body); }
	if ($config['auto_unicode']) {
		$body = unicodify($body);
		if ($config['markup_urls']) {
			foreach ($markup_urls as &$url) { $body = str_replace(unicodify($url), $url, $body); }
		}
	}

	$tracked_cites = array();

	// Cites
	if (isset($board) && preg_match_all('/(^|[\s(])&gt;&gt;(\d+?)((?=[\s,.)?!])|$)/m', $body, $cites, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
		if (count($cites[0]) > $config['max_cites']) {
			error($config['error']['toomanycites']);
		}

		$skip_chars = 0;
		$body_tmp = $body;

		$search_cites = array();
		foreach ($cites as $matches) {
			$search_cites[] = '`id` = ' . $matches[2][0];
		}
		$search_cites = array_unique($search_cites);

		$query = query(sprintf('SELECT `thread`, `id` FROM ``posts_%s`` WHERE ' .
			implode(' OR ', $search_cites), $board['uri'])) or error(db_error());

		$cited_posts = array();
		while ($cited = $query->fetch(PDO::FETCH_ASSOC)) {
			$cited_posts[$cited['id']] = $cited['thread'] ? $cited['thread'] : false;
		}

		foreach ($cites as $matches) {
			$cite = $matches[2][0];

			// preg_match_all is not multibyte-safe
			foreach ($matches as &$match) {
				$match[1] = mb_strlen(substr($body_tmp, 0, $match[1]));
			}

			if (isset($cited_posts[$cite])) {
				$replacement = '<a onclick="highlightReply(\''.$cite.'\', event);" href="/' .
					$board['dir'] . getThreadFileLink(array('id' => $cite, 'thread' => $cited_posts[$cite])) . '#' . $cite . '">&gt;&gt;' . $cite . '</a>';

				$body = mb_substr_replace($body, $matches[1][0] . $replacement . $matches[3][0], $matches[0][1] + $skip_chars, mb_strlen($matches[0][0]));
				$skip_chars += mb_strlen($matches[1][0] . $replacement . $matches[3][0]) - mb_strlen($matches[0][0]);

				if ($track_cites && $config['track_cites'])
					$tracked_cites[] = array($board['uri'], $cite);
			}
		}
	}

	// Cross-board linking
	if (preg_match_all('/(^|[\s(])&gt;&gt;&gt;\/(' . $config['board_regex'] . 'f?)\/(\d+)?((?=[\s,.)?!])|$)/um', $body, $cites, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
		if (count($cites[0]) > $config['max_cites']) {
			error($config['error']['toomanycross']);
		}

		$skip_chars = 0;
		$body_tmp = $body;

		if (isset($cited_posts)) {
			// Carry found posts from local board >>X links
			foreach ($cited_posts as $cite => $thread) {
				$cited_posts[$cite] = $board['dir'] . ($thread ? $thread : $cite) . '.html#' . $cite;
			}

			$cited_posts = array(
				$board['uri'] => $cited_posts
			);
		} else
			$cited_posts = array();

		$crossboard_indexes = array();
		$search_cites_boards = array();

		foreach ($cites as $matches) {
			$_board = $matches[2][0];
			$cite = @$matches[3][0];

			if (!isset($search_cites_boards[$_board]))
				$search_cites_boards[$_board] = array();
			$search_cites_boards[$_board][] = $cite;
		}

		$tmp_board = $board['uri'];

		foreach ($search_cites_boards as $_board => $search_cites) {
			$clauses = array();
			foreach ($search_cites as $cite) {
				if (!$cite || isset($cited_posts[$_board][$cite]))
					continue;
				$clauses[] = '`id` = ' . $cite;
			}
			$clauses = array_unique($clauses);

			if ($board['uri'] != $_board) {
				if (!openBoard($_board))
					continue; // Unknown board
			}

			if (!empty($clauses)) {
				$cited_posts[$_board] = array();
				$query = query(sprintf('SELECT `thread`, `id` FROM ``posts_%s`` WHERE ' . implode(' OR ', $clauses), $board['uri'])) or error(db_error());
				while ($cite = $query->fetch(PDO::FETCH_ASSOC)) {
					$cited_posts[$_board][$cite['id']] = $board['dir'] . getThreadFileLink($cite) . '#' . $cite['id'];
				}
			}

			$crossboard_indexes[$_board] = $board['dir'] . $config['file_index'];
		}

		// Restore old board
		if ($board['uri'] != $tmp_board)
			openBoard($tmp_board);

		foreach ($cites as $matches) {
			$_board = $matches[2][0];
			$cite = @$matches[3][0];

			// preg_match_all is not multibyte-safe
			foreach ($matches as &$match) {
				$match[1] = mb_strlen(substr($body_tmp, 0, $match[1]));
			}

			if ($cite) {
				if (isset($cited_posts[$_board][$cite])) {
					$link = $cited_posts[$_board][$cite];

					$replacement = '<a ' .
						($_board == $board['uri'] ?
							'onclick="highlightReply(\''.$cite.'\', event);" '
						: '') . 'href="' . $link . '">' .
						'&gt;&gt;&gt;/' . $_board . '/' . $cite .
						'</a>';

					$body = mb_substr_replace($body, $matches[1][0] . $replacement . $matches[4][0], $matches[0][1] + $skip_chars, mb_strlen($matches[0][0]));
					$skip_chars += mb_strlen($matches[1][0] . $replacement . $matches[4][0]) - mb_strlen($matches[0][0]);

					if ($track_cites && $config['track_cites'])
						$tracked_cites[] = array($_board, $cite);
				}
			} elseif(isset($crossboard_indexes[$_board])) {
				$replacement = '<a href="' . $crossboard_indexes[$_board] . '">' .
						'&gt;&gt;&gt;/' . $_board . '/' .
						'</a>';
				$body = mb_substr_replace($body, $matches[1][0] . $replacement . $matches[4][0], $matches[0][1] + $skip_chars, mb_strlen($matches[0][0]));
				$skip_chars += mb_strlen($matches[1][0] . $replacement . $matches[4][0]) - mb_strlen($matches[0][0]);
			}
		}
	}

	$tracked_cites = array_unique($tracked_cites, SORT_REGULAR);

	// replace words with img tags
	foreach($config['emotes'] as $word=>$img){
		$body = preg_replace_callback(
			'/\b'.preg_quote($word, '/').'\b/i', // <-- added the 'i' at the end
			function($matches) use($img){
				// check if it's already an img (still kinda redundant here, but whatever)
				if(preg_match('/<img[^>]+>/', $matches[0])) return $matches[0];
				return $img;
			},
			$body
		);
	}

	// Karachan's powerful formatting.
	$body = preg_replace("/^\s*&gt;.*$/m", '<span class="quote">$0</span>', $body); // >quote type 1
	$body = preg_replace("/^\s*&lt;.*$/m", '<span class="secondaryquote">$0</span>', $body); // <quote type 2
	$body = preg_replace("/^\s*\^.*$/m", '<span class="powerquote">$0</span>', $body); // ^quote type 3
	$body = preg_replace_callback("/\[\[color=([0-9a-fA-F]{6})(?:,([^]]+))?\]\]\{(.+?)\}/",
		function ($m) {
			// [[color=FFFFFF,bold,italics]]{text}
			$color = "#" . $m[1];
			$modifiers = isset($m[2]) ? explode(",", $m[2]) : [];
			$text = $m[3];
			$styles = ["color:$color"];
			if(in_array("bold", $modifiers)) {
				$styles[] = "font-weight:bold";
			}
			if(in_array("italics", $modifiers)) {
				$styles[] = "font-style:italic";
			}
			return '<span style="' .
				implode("; ", $styles) .
				'">' .
				$text .
				"</span>";
		},
		$body
	);
	$body = preg_replace('/\s+$/', "", $body);
	$body = preg_replace("/\n/", "<br/>", $body);

	// Fix code markup
	if($config['markup_code']) {
		foreach($code_markup as $id => $val) {
			$code = isset($val[2]) ? $val[2] : $val[1]; // code content
			$code_lang = isset($val[2]) ? $val[1] : ""; // language if set

			$class = $code_lang ? "language-$code_lang" : "";

			// escape HTML chars + keep newlines/tabs visible
			$code_html = htmlspecialchars($code);
			$code_html = str_replace(
				["\n", "\t"],
				["&#10;", "&#9;"],
				$code_html
			);

			$pre = "<pre><code class=\"$class\">$code_html</code></pre>";

			$body = str_replace("<code $id>", $pre, $body);
		}
	}

	if ($config['markup_repair_tidy']) {
		$tidy = new tidy();
		$body = str_replace("\t", '&#09;', $body);
		$body = $tidy->repairString($body, array(
			'doctype' => 'omit',
			'bare' => $config['markup_repair_tidy_bare'],
			'literal-attributes' => true,
			'indent' => false,
			'show-body-only' => true,
			'wrap' => 0,
			'output-bom' => false,
			'output-html' => true,
			'newline' => 'LF',
			'quiet' => true,
		), 'utf8');
		$body = str_replace("\n", '', $body);
	}

	// replace tabs with 8 spaces
	$body = str_replace("\t", '		', $body);

	return $tracked_cites;
}

function escape_markup_modifiers($string) {
	return preg_replace('@<(mephboard) ([\w\s]+)>@mi', '<$1 escape $2>', $string);
}

function defined_flags_accumulate($desired_flags) {
	global $config;
	$output_flags = 0x0;
	foreach ($desired_flags as $flagname) {
		if (defined($flagname)) {
			$flag = constant($flagname);
			if (gettype($flag) != 'integer')
				error(sprintf($config['error']['flag_wrongtype'], $flagname));
			$output_flags |= $flag;
		} else {
			if ($config['deprecation_errors'])
				error(sprintf($config['error']['flag_undefined'], $flagname));
		}
	}
	return $output_flags;
}

function utf8tohtml($utf8) {
	$flags = defined_flags_accumulate(['ENT_NOQUOTES', 'ENT_SUBSTITUTE', 'ENT_DISALLOWED']);
	return $utf8 ? htmlspecialchars($utf8, $flags, 'UTF-8') : '';
}

function ordutf8($string, &$offset) {
	$code = ord(substr($string, $offset,1));
	if ($code >= 128) { // otherwise 0xxxxxxx
		if ($code < 224)
			$bytesnumber = 2; // 110xxxxx
		else if ($code < 240)
			$bytesnumber = 3; // 1110xxxx
		else if ($code < 248)
			$bytesnumber = 4; // 11110xxx
		$codetemp = $code - 192 - ($bytesnumber > 2 ? 32 : 0) - ($bytesnumber > 3 ? 16 : 0);
		for ($i = 2; $i <= $bytesnumber; $i++) {
			$offset ++;
			$code2 = ord(substr($string, $offset, 1)) - 128; //10xxxxxx
			$codetemp = $codetemp*64 + $code2;
		}
		$code = $codetemp;
	}
	$offset += 1;
	if ($offset >= strlen($string))
		$offset = -1;
	return $code;
}

// Limit Non_Spacing_Mark and Enclosing_Mark characters
function strip_combining_chars($str) {
	global $config;
	$limit = strval($config['max_combining_chars']+1);
	return preg_replace('/(\p{Me}|\p{Mn}){'.$limit.',}/u','', $str);
}

// Stolen with permission from PlainIB (by Frank Usrs)
function make_comment_hex($str) {
	// remove cross-board citations
	// the numbers don't matter
	$str = preg_replace('!>>>/[A-Za-z0-9]+/!', '', $str);

	if (function_exists('iconv')) {
		// remove diacritics and other noise
		// FIXME: this removes cyrillic entirely
		$oldstr = $str;
		$str = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $str);
		if (!$str) $str = $oldstr;
	}

	$str = strtolower($str);

	// strip all non-alphabet characters
	$str = preg_replace('/[^a-z]/', '', $str);

	return md5($str);
}