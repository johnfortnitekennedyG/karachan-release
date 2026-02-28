/*
 * expand.js
 *
 * Usage:
 *   $config['additional_javascript'][] = 'inc/scripts/jquery.min.js';
 *   $config['additional_javascript'][] = 'inc/scripts/expand.js';
 *
 */

$(document).ready(function(){
	if($('span.omitted').length == 0)
		return; // nothing to expand

	var do_expand = function() {
		$(this)
			.html($(this).text().replace("Click reply to view.", '<a href="javascript:void(0)">'+"Click to expand"+'</a>.'))
			.find('a').click(window.expand_fun = function() {
				var thread = $(this).parents('[id^="thread_"]');
				var id = thread.attr('id').replace(/^thread_/, '');
				$.ajax({
					url: thread.find('p.intro a.post_no:first').attr('href'),
					context: document.body,
					success: function(data) {
						var last_expanded = false;
						$(data).find('div.post.reply').each(function() {
							thread.find('div.hidden').remove();
							var post_in_doc = thread.find('#' + $(this).attr('id'));
							if(post_in_doc.length == 0) {
								if(last_expanded) {
									$(this).addClass('expanded').insertAfter(last_expanded).before('<br class="expanded">');
								} else {
									$(this).addClass('expanded').insertAfter(thread.find('div.post:first')).after('<br class="expanded">');
								}
								last_expanded = $(this);
								$(document).trigger('new_post', this);
							} else {
								last_expanded = post_in_doc;
							}
						});
						

						thread.find("span.omitted").css('display', 'none');

						$('<span class="omitted hide-expanded"><a href="javascript:void(0)">Hide expanded replies</a>.</span>')
							.insertAfter(thread.find('.op div.body, .op span.omitted').last())
							.click(function() {
								thread.find('.expanded').remove();
								$(this).parent().find(".omitted:not(.hide-expanded)").css('display', '');
								$(this).parent().find(".hide-expanded").remove();
							});
					}
				});
			});
	}

	$('div.post.op span.omitted').each(do_expand);

	$(document).on("new_post", function(e, post) {
		if (!$(post).hasClass("reply")) {
			$(post).find('div.post.op span.omitted').each(do_expand);
		}
	});
});
