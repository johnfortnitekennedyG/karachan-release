/*
 * show-backlinks.js
 *
 * Usage:
 *   $config['additional_javascript'][] = 'inc/scripts/jquery.min.js';
 *   // $config['additional_javascript'][] = 'inc/scripts/post-hover'; (optional; must come first)
 *   $config['additional_javascript'][] = 'inc/scripts/show-backlinks.js';
 */

onReady(function() {
	let showBackLinks = function() {
		let reply_id = $(this).attr('id').replace(/(^reply_)|(^op_)/, '');

		$(this).find('div.body a:not([rel="nofollow"])').each(function() {
			let id, post, $mentioned;

			if (id = $(this).text().match(/^>>(\d+)$/)) {
				id = id[1];
			} else {
				return;
			}

			$post = $('#reply_' + id);
			if ($post.length == 0){
				$post = $('#op_' + id);
				if ($post.length == 0) {
					return;
				}
			}

			$mentioned = $post.find('p.intro span.mentioned');
			if($mentioned.length == 0) {
				$mentioned = $('<span class="mentioned unimportant"></span>').appendTo($post.find('p.intro'));
			}

			if ($mentioned.find('a.mentioned-' + reply_id).length != 0) {
				return;
			}

			let link = $('<a class="mentioned-' + reply_id + '" onclick="highlightReply(\'' + reply_id + '\');" href="#' + reply_id + '">&gt;&gt;' +
				reply_id + '</a>');
			link.appendTo($mentioned)

			if (window.init_hover) {
				link.each(init_hover);
			}
		});
	};

	$('div.post.reply').each(showBackLinks);
	$('div.post.op').each(showBackLinks);

	$(document).on('new_post', function(e, post) {
		if ($(post).hasClass("op")) {
			$(post).find('div.post.reply').each(showBackLinks);
		} else {
			$(post).parent().find('div.post.reply').each(showBackLinks);
		}
	});
});
