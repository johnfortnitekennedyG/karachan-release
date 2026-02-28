/*
 * expand-all-images.js
 *
 * Adds an "Expand all images" button to the top of the page.
 *
 * Usage:
 *   $config['additional_javascript'][] = 'inc/scripts/jquery.min.js';
 *   $config['additional_javascript'][] = 'inc/scripts/inline-expanding.js';
 *   $config['additional_javascript'][] = 'inc/scripts/expand-all-images.js';
 *
 */

if (active_page == 'ukko' || active_page == 'thread' || active_page == 'index') {
	onReady(function() {
		$('hr:first').before('<div id="expand-all-images" style="text-align:right"><a class="unimportant" href="javascript:void(0)"></a></div>');
		$('div#expand-all-images a')
			.text('Expand all images')
			.click(function() {
				$('a img.post-image').each(function() {
					// Don't expand YouTube embeds
					if ($(this).parent().parent().hasClass('video-container')) {
						return;
					}

					// or WEBM
					if (/^\/player\.php\?/.test($(this).parent().attr('href'))) {
						return;
					}

					if (!$(this).parent().data('expanded')) {
						$(this).parent().click();
					}
				});

				if (!$('#shrink-all-images').length) {
					$('hr:first').before('<div id="shrink-all-images" style="text-align:right"><a class="unimportant" href="javascript:void(0)"></a></div>');
				}

				$('div#shrink-all-images a')
					.text('Shrink all images')
					.click(function() {
						$('a img.full-image').each(function() {
							if ($(this).parent().data('expanded')) {
								$(this).parent().click();
							}
						});
						$(this).parent().remove();
					});
			});
	});
}