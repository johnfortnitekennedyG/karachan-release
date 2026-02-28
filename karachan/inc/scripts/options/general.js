/*
 * options/general.js - general settings tab for options panel
 */

+function(){
var tab = Options.add_tab("general", "home", "General");
$(function(){
	var stor = $("<div>Storage: </div>");
	stor.appendTo(tab.content);

	var reset_localstorage = function() {
		let keep = localStorage.getItem("kekpass");
		localStorage.clear();
		if(keep !== null) { localStorage.setItem("kekpass", keep); }
	};
	$("<button>Export</button>").appendTo(stor).on("click", function() {
		let obj = {};
		for(let i=0; i<localStorage.length; i++) {
			let key = localStorage.key(i);
			if(key === "kekpass") { continue; }
			obj[key] = localStorage.getItem(key);
		}
		let str = JSON.stringify(obj);
		$(".output").remove();
		$("<input type='text' class='output'>").appendTo(stor).val(str);
	});
	$("<button>Import</button>").appendTo(stor).on("click", function() {
		var str = prompt("Paste your storage data");
		if (!str) { return false; }
		var obj = JSON.parse(str);
		if (!obj) { return false; }
		reset_localstorage();
		for(let i in obj) {
			if(i === "kekpass") continue;
			localStorage[i] = obj[i];
		}
		document.location.reload();
	});
	$("<button>Erase</button>").appendTo(stor).on("click", function() {
		if (confirm("Are you sure you want to erase your storage? This involves your hidden threads, watched threads, post password and many more.")) {
			reset_localstorage();
			document.location.reload();
		}
	});
});
}();