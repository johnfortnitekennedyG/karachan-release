{% verbatim %}
function fmt(s, a) {
	return s.replace(/\{([0-9]+)\}/g, function(x) { return a[x[1]]; });
}

function until(timestamp) {
	let difference = timestamp - Date.now() / 1000 | 0;
	switch (true) {
	case (difference < 60):
		return "" + difference + ' second(s)';
	case (difference < 3600): // 60 * 60 = 3600
		return "" + Math.round(difference / 60) + ' minute(s)';
	case (difference < 86400): // 60 * 60 * 24 = 86400
		return "" + Math.round(difference / 3600) + ' hour(s)';
	case (difference < 604800): // 60 * 60 * 24 * 7 = 604800
		return "" + Math.round(difference / 86400) + ' day(s)';
	case (difference < 31536000): // 60 * 60 * 24 * 365 = 31536000
		return "" + Math.round(difference / 604800) + ' week(s)';
	default:
		return "" + Math.round(difference / 31536000) + ' year(s)';
	}
}

function ago(timestamp) {
	let difference = (Date.now() / 1000 | 0) - timestamp;
	switch (true) {
	case (difference < 60):
		return "" + difference + ' second(s)';
	case (difference < 3600): /// 60 * 60 = 3600
		return "" + Math.round(difference/(60)) + ' minute(s)';
	case (difference < 86400): // 60 * 60 * 24 = 86400
		return "" + Math.round(difference/(3600)) + ' hour(s)';
	case (difference < 604800): // 60 * 60 * 24 * 7 = 604800
		return "" + Math.round(difference/(86400)) + ' day(s)';
	case (difference < 31536000): // 60 * 60 * 24 * 365 = 31536000
		return "" + Math.round(difference/(604800)) + ' week(s)';
	default:
		return "" + Math.round(difference/(31536000)) + ' year(s)';
	}
}

var datelocale =
	{ days: ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday']
	, shortDays: ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"]
	, months: ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December']
	, shortMonths: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
	, AM: 'AM'
	, PM: 'PM'
	, am: 'am'
	, pm: 'pm'
	};


function alert(a, do_confirm, confirm_ok_action, confirm_cancel_action) {
	let handler, div, bg, closebtn, okbtn;
	let close = function() {
		handler.fadeOut(400, function() { handler.remove(); });
		return false;
	};

	handler = $("<div id='alert_handler'></div>").hide().appendTo('body');

	bg = $("<div id='alert_background'></div>").appendTo(handler);

	div = $("<div id='alert_div'></div>").appendTo(handler);
	closebtn = $("<a id='alert_close' href='javascript:void(0)'><i class='fa fa-times'></i></div>")
		.appendTo(div);

	$("<div id='alert_message'></div>").html(a).appendTo(div);

	okbtn = $("<button class='button alert_button'>"+"OK"+"</button>").appendTo(div);

	if (do_confirm) {
		confirm_ok_action = (typeof confirm_ok_action !== "function") ? function(){} : confirm_ok_action;
		confirm_cancel_action = (typeof confirm_cancel_action !== "function") ? function(){} : confirm_cancel_action;
		okbtn.click(confirm_ok_action);
		$("<button class='button alert_button'>"+"Cancel"+"</button>").click(confirm_cancel_action).click(close).appendTo(div);
		bg.click(confirm_cancel_action);
		okbtn.click(confirm_cancel_action);
		closebtn.click(confirm_cancel_action);
	}

	bg.click(close);
	okbtn.click(close);
	closebtn.click(close);

	handler.fadeIn(400);
}

var saved = {};


var selectedstyle = 'Default';
var styles = {
	{% endverbatim %}
	{% for stylesheet in stylesheets %}{% verbatim %}'{% endverbatim %}{{ stylesheet.name|addslashes }}{% verbatim %}' : '{% endverbatim %}{{ stylesheet.uri|addslashes }}{% verbatim %}',
	{% endverbatim %}{% endfor %}{% verbatim %}
};

if (typeof board_name === 'undefined') {
	var board_name = false;
}

function changeStyle(styleName) {
	localStorage.stylesheet = styleName;
	if (!document.getElementById('stylesheet')) {
		let s = document.createElement('link');
		s.rel = 'stylesheet';
		s.type = 'text/css';
		s.id = 'stylesheet';
		let x = document.getElementsByTagName('head')[0];
		x.appendChild(s);
	}

	let mainStylesheetElement = document.getElementById('stylesheet');
	let userStylesheetElement = document.getElementById('stylesheet-user');

	// Override main stylesheet with the user selected one.
	if (!userStylesheetElement) {
		userStylesheetElement = document.createElement('link');
		userStylesheetElement.rel = 'stylesheet';
		userStylesheetElement.media = 'none';
		userStylesheetElement.type = 'text/css';
		userStylesheetElement.id = 'stylesheet';
		let x = document.getElementsByTagName('head')[0];
		x.appendChild(userStylesheetElement);
	}

	// tiny helper to swap silhouette once CSS is applied
	function applyBoardSilhouette(){
        // guardrails: need a body + a usable board_name
		const el=document.body||document.documentElement;
		if(!el) return;
		if(typeof window.board_name==='undefined' || !window.board_name) return;

		// read computed background-image (keeps repeat/pos/attach via shorthand intact)
		const img=window.getComputedStyle(el).backgroundImage;
		if(!img || img==='none') return;

		// replace any /assets/silhouette/<name>.png -> /assets/silhouette/<board_name>.png
		const re=/url\((['"]?)([^'")]*\/assets\/silhouette\/)([^\/'")]+)\.png(\?[^'")]*)?\1\)/gi;
		const updated=img.replace(
			re,
			(_,q,prefix,_old,query)=>'url('+(q||'')+prefix+board_name+'.png'+(query||'')+(q||'')+')'
		);

		if(updated!==img){
			// write back just background-image to avoid nuking positions/etc on other layers
			el.style.backgroundImage=updated;
		}
	}

	// When the new one is loaded, disable the old one
	userStylesheetElement.onload = function() {
		this.media = 'all';
		mainStylesheetElement.media = 'none';
		applyBoardSilhouette();
	}

	let style = styles[styleName];
	if (style !== '') {
		// Add the version of the resource if the style is not the embedded one.
		style += `?v=${resourceVersion}`;
	}

	document.getElementById('stylesheet').href = style;
	selectedstyle = styleName;

	// also try a micro-delay fallback in case some engines lag on onload
	setTimeout(applyBoardSilhouette, 0);
	
	if (document.getElementsByClassName('styles').length != 0) {
		let styleLinks = document.getElementsByClassName('styles')[0].childNodes;
		for (let i = 0; i < styleLinks.length; i++) {
			styleLinks[i].className = '';
		}
	}
	if (typeof $ != 'undefined') {
		$(window).trigger('stylesheet', styleName);
	}
}


var resourceVersion = document.currentScript.getAttribute('data-resource-version');
if (localStorage.stylesheet) {
	for (let styleName in styles) {
		if (styleName == localStorage.stylesheet) {
			changeStyle(styleName);
			break;
		}
	}
}

function getCookie(cookie_name) {
	let results = document.cookie.match('(^|;) ?' + cookie_name + '=([^;]*)(;|$)');
	if (results) {
		return unescape(results[2]);
	} else {
		return null;
	}
}

function highlightReply(id) {
	if (typeof window.event != "undefined" && event.which == 2) {
		// don't highlight on middle click
		return true;
	}

	let divs = document.getElementsByTagName('div');
	for (let i = 0; i < divs.length; i++) {
		if (divs[i].className.indexOf('post') != -1) {
			divs[i].className = divs[i].className.replace(/highlighted/, '');
		}
	}
	if (id) {
		let post = document.getElementById('reply_' + id);
		if (post) {
			post.className += ' highlighted';
		}
		window.location.hash = id;
	}
	return true;
}

function generatePassword() {
	let pass = '';
	let chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_+';
	for (let i = 0; i < 16; i++) {
		let rnd = Math.floor(Math.random() * chars.length);
		pass += chars.substring(rnd, rnd + 1);
	}
	return pass;
}

function doPost(form) {
	if (form.elements['name']) {
		localStorage.name = form.elements['name'].value.replace(/( |^)## .+$/, '');
	}
	if (form.elements['password']) {
		localStorage.password = form.elements['password'].value;
	}

	saved[document.location] = form.elements['body'].value;
	sessionStorage.body = JSON.stringify(saved);

	return form.elements['body'].value != "" || (form.elements['file'] && form.elements['file'].value != "") || (form.elements.file_url && form.elements['file_url'].value != "");
}

function citeReply(id, with_link) {
	let textarea = document.getElementById('body');
	if (!textarea) {
		return false;
	}

	if (textarea.selectionStart || textarea.selectionStart == '0') {
		let start = textarea.selectionStart;
		let end = textarea.selectionEnd;
		textarea.value = textarea.value.substring(0, start) + '>>' + id + '\n' + textarea.value.substring(end, textarea.value.length);

		textarea.selectionStart += ('>>' + id).length + 1;
		textarea.selectionEnd = textarea.selectionStart;
	} else {
		// ???
		textarea.value += '>>' + id + '\n';
	}
	if (typeof $ != 'undefined') {
		let select = document.getSelection().toString();
		if (select) {
			let body = $('#reply_' + id + ', #op_' + id).find('div.body');  // TODO: support for OPs
			let index = body.text().indexOf(select.replace('\n', ''));  // for some reason this only works like this
			if (index > -1) {
				textarea.value += '>' + select + '\n';
			}
		}

		$(window).trigger('cite', [id, with_link]);
		$(textarea).change();
	}
	return false;
}

function rememberStuff() {
	if (!localStorage.kekpass) { localStorage.kekpass = generatePassword(); }
	if (document.forms.post) {
		if (document.forms.post.password) {
			if (!localStorage.password) { localStorage.password = generatePassword(); }
			document.forms.post.password.value = localStorage.password;
		}

		if (localStorage.name && document.forms.post.elements['name']) {
			document.forms.post.elements['name'].value = localStorage.name;
		}

		if (window.location.hash.indexOf('q') == 1) {
			citeReply(window.location.hash.substring(2), true);
		}

		if (sessionStorage.body) {
			let saved = JSON.parse(sessionStorage.body);
			if (getCookie('{% endverbatim %}{{ config.cookies.js }}{% verbatim %}')) {
				// Remove successful posts
				let successful = JSON.parse(getCookie('{% endverbatim %}{{ config.cookies.js }}{% verbatim %}'));
				for (let url in successful) {
					saved[url] = null;
				}
				sessionStorage.body = JSON.stringify(saved);

				document.cookie = '{% endverbatim %}{{ config.cookies.js }}{% verbatim %}={};expires=0;path=/;';
			}
			if (saved[document.location]) {
				document.forms.post.body.value = saved[document.location];
			}
		}

		if (localStorage.body) {
			document.forms.post.body.value = localStorage.body;
			localStorage.body = '';
		}
	}
}

var script_settings = function(script_name) {
	this.script_name = script_name;
	this.get = function(var_name, default_val) {
		if (typeof tb_settings == 'undefined' ||
			typeof tb_settings[this.script_name] == 'undefined' ||
			typeof tb_settings[this.script_name][var_name] == 'undefined') {
			return default_val;
		}
		return tb_settings[this.script_name][var_name];
	}
};

function init() {
	{% endverbatim %}
	{% if config.allow_delete %}
	if (document.forms.postcontrols) { document.forms.postcontrols.password.value = localStorage.password; }
	{% endif %}
	{% verbatim %}
	if (window.location.hash.indexOf('q') != 1 && window.location.hash.substring(1))
		highlightReply(window.location.hash.substring(1));
}

onready_callbacks = [];
function onReady(fnc) {
	onready_callbacks.push(fnc);
}

function ready() {
	for (let i = 0; i < onready_callbacks.length; i++) {
		onready_callbacks[i]();
	}
}

{% endverbatim %}

var post_date = "{{ config.post_date }}";
var max_images = {{ config.max_images }};

onReady(init);

{% if config.google_analytics %}{% verbatim %}

var _gaq = _gaq || [];_gaq.push(['_setAccount', '{% endverbatim %}{{ config.google_analytics }}{% verbatim %}']);{% endverbatim %}{% if config.google_analytics_domain %}{% verbatim %}_gaq.push(['_setDomainName', '{% endverbatim %}{{ config.google_analytics_domain }}{% verbatim %}']){% endverbatim %}{% endif %}{% if not config.google_analytics_domain %}{% verbatim %}_gaq.push(['_setDomainName', 'none']){% endverbatim %}{% endif %}{% verbatim %};_gaq.push(['_trackPageview']);(function() {var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;ga.src = ('https:' == document.location.protocol ? 'https://' : 'http://') + 'stats.g.doubleclick.net/dc.js';let s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);})();{% endverbatim %}{% endif %}