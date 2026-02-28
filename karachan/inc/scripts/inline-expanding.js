/*
* inline-expanding.js
*
* Usage:
*   $config['additional_javascript'][] = 'inc/scripts/jquery.min.js';
*   $config['additional_javascript'][] = 'inc/scripts/inline-expanding.js';
*
*/

$(document).ready(function(){
'use strict';

var DEFAULT_MAX = 5;  // default maximum image loads
var inline_expand_post = function() {
	var link = this.getElementsByTagName('a');

	var loadingQueue = (function () {
		var MAX_IMAGES = localStorage.inline_expand_max || DEFAULT_MAX;   // maximum number of images to load concurrently
		var loading = 0;                                                  // number of images that is currently loading
		var waiting = [];                                                 // waiting queue

		var enqueue = function (ele) { waiting.push(ele); };
		var dequeue = function () { return waiting.shift(); };
		var update = function() {
			var ele;
			while (loading < MAX_IMAGES) {
				ele = dequeue();
				if (ele) {
					++loading;
					ele.deferred.resolve();
				} else { return; }
			}
		};
		return {
			remove: function (ele) {
				var i = waiting.indexOf(ele);
				if (i > -1) {
					waiting.splice(i, 1);
				}
				if ($(ele).data('imageLoading') === 'true') {
					$(ele).data('imageLoading', 'false');
					clearTimeout(ele.timeout);
					--loading;
				}
			},
			add: function (ele) {
				ele.deferred = $.Deferred();
				ele.deferred.done(function () {
					var $loadstart = $.Deferred();
					var thumb = ele.childNodes[0];
					var img = ele.childNodes[1];

					var onLoadStart = function (img) {
						if (img.naturalWidth) {
							$loadstart.resolve(img, thumb);
						} else { return (ele.timeout = setTimeout(onLoadStart, 30, img)); }
					};

					$(img).one('load', function () {
						$.when($loadstart).done(function () {
							//  once fully loaded, update the waiting queue
							--loading;
							$(ele).data('imageLoading', 'false');
							update();
						});
					});
					$loadstart.done(function (img, thumb) {
						thumb.style.display = 'none';
						img.style.display = '';
					});

					img.setAttribute('src', ele.href);
					$(ele).data('imageLoading', 'true');
					ele.timeout = onLoadStart(img);
				});

				if (loading < MAX_IMAGES) {
					++loading;
					ele.deferred.resolve();
				} else { enqueue(ele); }
			}
		};
	})();

	for (var i = 0; i < link.length; i++) {
		if (typeof link[i] == "object" && link[i].childNodes && typeof link[i].childNodes[0] !== 'undefined' &&
				link[i].childNodes[0].src && link[i].childNodes[0].className.match(/post-image/)) {
			link[i].onclick = function(e) {
				if(this.href) {
					if(this.href.toLowerCase().endsWith(".spc")) {
						this.removeChild(this.childNodes[0]);
						let iframe=document.createElement("iframe");
						iframe.src="/assets/kspcplayer.html?file="+encodeURIComponent(this.href);
						iframe.width="635";
						iframe.height="335";
						iframe.style.border="none";
						iframe.style.margin="5px";
						this.appendChild(iframe);
						return false; // prevent the default link nav, bruh
					}
				}
				if(this.className.match(/file/)) { return true; }
				var img, post_body, still_open, canvas, scroll;
				var thumb = this.childNodes[0];
				var padding = 5;
				if (thumb.className == 'hidden') { return false; }
				if (e.which == 2 || e.ctrlKey) { return true; }
				if (!$(this).data('expanded')) {
					if (~this.parentNode.className.indexOf('multifile')) { $(this).data('width', this.parentNode.style.width); }

					this.parentNode.removeAttribute('style');
					$(this).data('expanded', 'true');

					if (thumb.tagName === 'CANVAS') {
						canvas = thumb;
						thumb = thumb.nextSibling;
						this.removeChild(canvas);
						canvas.style.display = 'block';
					}

					thumb.style.opacity = '0.4';
					thumb.style.filter = 'alpha(opacity=40)';

					img = document.createElement('img');
					img.className = 'full-image';
					img.style.display = 'none';
					img.setAttribute('alt', 'Fullsized image');
					this.appendChild(img);

					loadingQueue.add(this);
				} else {
					loadingQueue.remove(this);
					scroll = false;

					//  scroll to thumb if not triggered by 'shrink all image'
					if (e.target.className == 'full-image') { scroll = true; }
					if (~this.parentNode.className.indexOf('multifile')) { this.parentNode.style.width = $(this).data('width'); }

					thumb.style.opacity = '';
					thumb.style.display = '';
					if (thumb.nextSibling) this.removeChild(thumb.nextSibling);  //full image loaded or loading
					$(this).removeData('expanded');
					delete thumb.style.filter;

					//  do the scrolling after page reflow
					if (scroll) {
						post_body = $(thumb).parentsUntil('form > div').last();

						//  on multifile posts, determin how many other images are still expanded
						still_open = post_body.find('.post-image').filter(function(){
							return $(this).parent().data('expanded') == 'true';
						}).length;

						//  deal with differnt boards' menu styles
						if (still_open > 0) {
							if (thumb.getBoundingClientRect().top - padding < 0) { $(document).scrollTop($(thumb).parent().parent().offset().top - padding); }
						} else {
							if (post_body[0].getBoundingClientRect().top - padding < 0) { $(document).scrollTop(post_body.offset().top - padding); }
						}
					}
				}
				return false;
			};
		}
	}
};

//  setting up user option
if (window.Options && Options.get_tab('general')) {
	Options.extend_tab('general', '<span id="inline-expand-max">'+ 'Number of simultaneous image downloads: ' + 
									'<input type="number" step="1" min="1" size="4"></span>');
	$('#inline-expand-max input')
		.css('width', '50px')
		.val(localStorage.inline_expand_max || DEFAULT_MAX)
		.on('change', function (e) {
			// validation in case some fucktard tries to enter a negative floating point number
			var n = parseInt(e.target.value);
			var val = (n<1) ? 1 : n;

			localStorage.inline_expand_max = val;
		});
}

if (window.jQuery) {
	$('div[id^="thread_"]').each(inline_expand_post);

	// allow to work with auto-reload.js, etc.
	$(document).on('new_post', function(e, post) {
		inline_expand_post.call(post);
	});
} else {
	inline_expand_post.call(document);
}
});
