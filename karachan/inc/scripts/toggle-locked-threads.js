/*
 * toggle-locked-threads.js
 *
 * Usage:
 *   $config['additional_javascript'][] = 'inc/scripts/jquery.min.js';
 *   //$config['additional_javascript'][] = 'inc/scripts/options.js';
 *   //$config['additional_javascript'][] = 'inc/scripts/options/general.js';
 *   $config['additional_javascript'][] = 'inc/scripts/toggle-locked-threads.js';
 *
 */

if (active_page == 'ukko' || active_page == 'index' || (window.Options && Options.get_tab('general')))
$(document).ready(function(){
	var hide_locked_threads = localStorage['hidelockedthreads'] ? true : false;

	$('<style type="text/css"> img.hidden{ opacity: 0.1; background: grey; border: 1px solid #000; } </style>').appendTo($('head'));
	
	var hideLockedThread = function($thread) {
		if (active_page == 'ukko' || active_page == 'index')
		$thread
			.hide()
			.addClass('hidden');
	};
	
	var restoreLockedThread = function($thread) {
		$thread
			.show()
			.removeClass('hidden');
	};
	
	var getThreadFromIcon = function($icon) {
		return $icon.parent().parent().parent()
	};

	var selector, event;
        if (window.Options && Options.get_tab('general')) {
                selector = '#toggle-locked-threads>input';
                event = 'change';
                Options.extend_tab("general", "<label id='toggle-locked-threads'><input type='checkbox' /> "+'Hide locked threads'+"</label>");
        }
        else {
                selector = '#toggle-locked-threads a';
                event = 'click';
		$('hr:first').before('<div id="toggle-locked-threads" style="text-align:right"><a class="unimportant" href="javascript:void(0)">-</a></div>');
        }
	
	$('div#toggle-locked-threads a')
		.text(hide_locked_threads ? 'Show locked threads' : 'Hide locked threads');

	$(selector)
		.on(event, function() {
			hide_locked_threads = !hide_locked_threads;
			if (hide_locked_threads) {
				$('img.icon[title="Locked"], i.fa-lock.fa').each(function() {
					hideLockedThread(getThreadFromIcon($(this)));
				});
				localStorage.hidelockedthreads = true;
			} else {
				$('img.icon[title="Locked"], i.fa-lock.fa').each(function() {
					restoreLockedThread(getThreadFromIcon($(this)));
				});
				delete localStorage.hidelockedthreads;
			}
			
			$(this).text(hide_locked_threads ? 'Show locked threads' : 'Hide locked threads')
		});
	
	if (hide_locked_threads) {
		$('img.icon[title="Locked"], i.fa-lock.fa').each(function() {
			hideLockedThread(getThreadFromIcon($(this)));
		});

		if (window.Options && Options.get_tab('general')) {
			$('#toggle-locked-threads>input').prop('checked', true);
		}
	}
        $(document).on('new_post', function(e, post) {
		if (hide_locked_threads) {
			$(post).find('img.icon[title="Locked"], i.fa-lock.fa').each(function() {
	                        hideLockedThread(getThreadFromIcon($(this)));
       		        });
		}
	});
});

