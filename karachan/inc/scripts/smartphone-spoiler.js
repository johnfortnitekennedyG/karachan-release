/*
 * smartphone-spoiler.js
 *
 * Usage:
 *   $config['additional_javascript'][] = 'inc/scripts/mobile-style.js';
 *   $config['additional_javascript'][] = 'inc/scripts/smartphone-spoiler.js';
 */

onReady(function() {
	if (device_type == 'mobile') {
		let fix_spoilers = function(where) {
			let spoilers = where.getElementsByClassName('spoiler');
			for (let i = 0; i < spoilers.length; i++) {
				spoilers[i].onmousedown = function() {
					this.style.color = 'white';
				};
			}
		};
		fix_spoilers(document);

		// allow to work with auto-reload.js, etc.
		$(document).on('new_post', function(e, post) {
			fix_spoilers(post);
		});

	}
});
