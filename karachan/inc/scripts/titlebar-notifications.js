/*
 * titlebar-notifications.js - a library for showing number of new events in titlebar
 *
 * Released under the MIT license
 * Copyright (c) 2014 Marcin Łabanowski <marcin@6irc.net>
 *
 * Usage:
 *   $config['additional_javascript'][] = 'inc/scripts/titlebar-notifications.js';
 *   //$config['additional_javascript'][] = 'inc/scripts/auto-reload.js';
 *   //$config['additional_javascript'][] = 'inc/scripts/watch.js';
 *
 */

var orig_title = document.title;

$(function(){
  orig_title = document.title;
});

update_title = function() {
  var updates = 0;
  for(var i in title_collectors) {
    updates += title_collectors[i]();
  }
  document.title = (updates ? "("+updates+") " : "") + orig_title;
};

var title_collectors = [];
add_title_collector = function(f) {
  title_collectors.push(f);
};
