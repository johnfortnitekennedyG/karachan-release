/*
* topbar script - allow users choose board options as they wish
*/

if(navigator.userAgent.match(/iPhone|iPod|iPad|Android|Opera Mini|Blackberry|PlayBook|Windows Phone|Tablet PC|Windows CE|IEMobile/i)) {
	$('html').addClass("mobile-style");
	device_type = "mobile";
}
else {
	$('html').addClass("desktop-style");
	device_type = "desktop";
}

+function () {
var options_button, options_handler, options_background, options_div, options_close, options_tablist, options_tabs, options_current_tab;

var Options = {};
window.Options = Options;

var first_tab = function () {
	for (var i in options_tabs) { return i; }
	return false;
};

Options.show = function () {
	if (!options_current_tab) { Options.select_tab(first_tab(), true); }
	options_handler.fadeIn();
};
Options.hide = function () {
	options_handler.fadeOut();
};

options_tabs = {};
Options.add_tab = function (id, icon, name, content) {
	var tab = {};
	if (typeof content == "string") { content = $("<div>" + content + "</div>"); }

	tab.id = id;
	tab.name = name;
	tab.icon = $("<div class='options_tab_icon'><i class='fa fa-" + icon + "'></i><div>" + name + "</div></div>");
	tab.content = $("<div class='options_tab'></div>").css("display", "none");

	tab.content.appendTo(options_div);

	tab.icon.on("click", function () {
		Options.select_tab(id);
	}).appendTo(options_tablist);

	$("<h2>" + name + "</h2>").appendTo(tab.content);

	if (content) { content.appendTo(tab.content); }

	options_tabs[id] = tab;
	return tab;
};

Options.get_tab = function (id) {
	return options_tabs[id];
};

Options.extend_tab = function (id, content) {
	if (typeof content == "string") { content = $("<div>" + content + "</div>"); }
	content.appendTo(options_tabs[id].content);
	return options_tabs[id];
};

Options.select_tab = function (id, quick) {
	if (options_current_tab) {
		if (options_current_tab.id == id) { return false; }
		options_current_tab.content.fadeOut();
		options_current_tab.icon.removeClass("active");
	}
	var tab = options_tabs[id];
	options_current_tab = tab;
	options_current_tab.icon.addClass("active");
	tab.content[quick ? "show" : "fadeIn"]();

	return tab;
};

options_handler = $("<div id='options_handler'></div>").css("display", "none");
options_background = $("<div id='options_background'></div>").on("click", Options.hide).appendTo(options_handler);
options_div = $("<div id='options_div'></div>").appendTo(options_handler);
options_close = $("<a id='options_close' href='javascript:void(0)'><i class='fa fa-times'></i></div>")
	.on("click", Options.hide).appendTo(options_div);
options_tablist = $("<div id='options_tablist'></div>").appendTo(options_div);

$(function(){
	const $boardlist=$(".boardlist:first");
	if(!$boardlist.length) { return; } // nothing to do

	// reset + loading state
	$boardlist.addClass("is-loading").empty().append($("<span class='sub' aria-live='polite'></span>").text("[ Loading... ]"));

	// make sure handler is in body
	options_handler.appendTo($(document.body));

	// fetch data
	$.getJSON("/kcindex.php").done(function(groups){
		// rebuild content in-place (dont replace container -> no layout shift)
		$boardlist.empty();
		$.each(groups, function(_, group){
			const $span=$("<span class='sub'></span>");
			$span.append("[ ");

			$.each(group, function(i, item){
				const href=item.link.endsWith(".php") ? item.link : (modRoot + item.link);
				const $a=$("<a></a>").attr("href", href).text(item.name);
				if(item.title) { $a.attr("title", item.title); }
				$span.append($a);
				if(i<group.length-1){ $span.append(" / "); }
			});

			$span.append(" ]");
			$boardlist.append($span);
		});
		const $options=$("<a href='javascript:void(0)' title='Options' class='boardlist-options'>[Options]</a>");
		$options.on("click", Options.show);
		$boardlist.append($options);
		$boardlist.removeClass("is-loading");
	}).fail(function(){
		// bro this is rigged
		$boardlist.find(".sub").first().text("[ Failed to load ]");
		$boardlist.addClass("is-loading");
	});
});

}();