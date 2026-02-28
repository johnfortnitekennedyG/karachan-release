/*
 * upload-selection.js - makes upload fields in post form more compact
 *
 * Released under the MIT license
 * Copyright (c) 2014 Marcin Łabanowski <marcin@6irc.net>
 *
 * Usage:
 *   $config['additional_javascript'][] = 'inc/scripts/jquery.min.js';
 *   //$config['additional_javascript'][] = 'inc/scripts/wpaint.js';
 *   $config['additional_javascript'][] = 'inc/scripts/upload-selection.js';
 *                                                  
 */

$(function(){
  var enabled_file = true;
  var enabled_url = $("#upload_url").length > 0;
  var enabled_embed = $("#upload_embed").length > 0;
  var enabled_oekaki = typeof window.oekaki != "undefined";

  var disable_all = function() {
    $("#upload").hide();
    $("[id^=upload_file]").hide();
    $(".file_separator").hide();
    $("#upload_url").hide();
    $("#upload_embed").hide();
    $(".add_image").hide();
    $(".dropzone-wrap").hide();

    $('[id^=upload_file]').each(function(i, v) {
        $(v).val('');
    });

    if (enabled_oekaki) {
      if (window.oekaki.initialized) {
        window.oekaki.deinit();
      }
    }
  };

  enable_file = function() {
    disable_all();
    $("#upload").show();
    $(".dropzone-wrap").show();
    $(".file_separator").show();
    $("[id^=upload_file]").show();
    $(".add_image").show();
  };

  enable_url = function() {
    disable_all();
    $("#upload").show();
    $("#upload_url").show();

    $('label[for="file_url"]').html("URL");
  };

  enable_embed = function() {
    disable_all();
    $("#upload_embed").show();
  };

  enable_oekaki = function() {
    disable_all();

    window.oekaki.init();
  };

  if (enabled_url || enabled_embed || enabled_oekaki) {
    $("<tr><th>"+"Select"+"</th><td id='upload_selection'></td></tr>").insertBefore("#upload");
    var my_html = "<a href='javascript:void(0)' onclick='enable_file(); return false;'>"+"File"+"</a>";
    if (enabled_url) {
      my_html += " / <a href='javascript:void(0)' onclick='enable_url(); return false;'>"+"Remote"+"</a>";
    }
    if (enabled_embed) {
      my_html += " / <a href='javascript:void(0)' onclick='enable_embed(); return false;'>"+"Embed"+"</a>";
    }
    if (enabled_oekaki) {
      my_html += " / <a href='javascript:void(0)' onclick='enable_oekaki(); return false;'>"+"Oekaki"+"</a>";

      $("#confirm_oekaki_label").hide();
    }
    $("#upload_selection").html(my_html);

    enable_file();
  }
});
