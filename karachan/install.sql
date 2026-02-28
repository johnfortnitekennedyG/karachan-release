-- phpMyAdmin SQL Dump
-- version 4.0.4.1
-- http://www.phpmyadmin.net

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

-- Table structure for table `bans`
CREATE TABLE IF NOT EXISTS `bans` (
	`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
	`ipstart` varbinary(16) NOT NULL,
	`ipend` varbinary(16) DEFAULT NULL,
	`created` int(10) unsigned NOT NULL,
	`expires` int(10) unsigned DEFAULT NULL,
	`creator` int(10) NOT NULL,
	`reason` text,
	`seen` tinyint(1) NOT NULL,
	`post` blob,
	PRIMARY KEY (`id`),
	KEY `expires` (`expires`),
	KEY `ipstart` (`ipstart`,`ipend`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1 ;

-- Table structure for table `boards`
CREATE TABLE IF NOT EXISTS `boards` (
	`uri` varchar(58) CHARACTER SET utf8 NOT NULL,
	`title` tinytext NOT NULL,
	`subtitle` tinytext,
	`owner` int(11) unsigned NOT NULL DEFAULT 0,
	-- `indexed` boolean default true,
	PRIMARY KEY (`uri`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table structure for table `cites`
CREATE TABLE IF NOT EXISTS `cites` (
	`board` varchar(58) NOT NULL,
	`post` int(11) NOT NULL,
	`target_board` varchar(58) NOT NULL,
	`target` int(11) NOT NULL,
	KEY `target` (`target_board`,`target`),
	KEY `post` (`board`,`post`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Table structure for table `ip_notes`
CREATE TABLE IF NOT EXISTS `ip_notes` (
	`id` int(11) unsigned NOT NULL AUTO_INCREMENT,
	`ip` varchar(39) CHARACTER SET ascii NOT NULL,
	`mod` int(11) DEFAULT NULL,
	`time` int(11) NOT NULL,
	`body` text NOT NULL,
	UNIQUE KEY `id` (`id`),
	KEY `ip_lookup` (`ip`, `time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1 ;

-- Table structure for table `modlogs`
CREATE TABLE IF NOT EXISTS `modlogs` (
	`mod` int(11) NOT NULL,
	`ip` varchar(39) CHARACTER SET ascii NOT NULL,
	`board` varchar(58) CHARACTER SET utf8 DEFAULT NULL,
	`time` int(11) NOT NULL,
	`text` text NOT NULL,
	KEY `time` (`time`),
	KEY `mod`(`mod`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table structure for table `karausers`
CREATE TABLE IF NOT EXISTS `karausers` (
	`id` int(11) unsigned NOT NULL AUTO_INCREMENT,
	`username` varchar(30) NOT NULL,
	`password` varchar(256) CHARACTER SET ascii NOT NULL COMMENT 'SHA256',
	`version` varchar(64) CHARACTER SET ascii NOT NULL,
	`permissions` bigint unsigned NOT NULL DEFAULT 0,
	PRIMARY KEY (`id`),
	UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table structure for table `news`
CREATE TABLE IF NOT EXISTS `news` (
	`id` int(11) unsigned NOT NULL AUTO_INCREMENT,
	`name` text NOT NULL,
	`time` int(11) NOT NULL,
	`subject` text NOT NULL,
	`body` text NOT NULL,
	UNIQUE KEY `id` (`id`),
	KEY `time` (`time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1 ;

-- Table structure for table `reports`
CREATE TABLE IF NOT EXISTS `reports` (
	`id` int(11) unsigned NOT NULL AUTO_INCREMENT,
	`time` int(11) NOT NULL,
	`ip` varchar(39) CHARACTER SET ascii NOT NULL,
	`board` varchar(58) CHARACTER SET utf8 DEFAULT NULL,
	`post` int(11) NOT NULL,
	`reason` text NOT NULL,
	PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1 ;

-- Table structure for table `flood`
CREATE TABLE IF NOT EXISTS `flood` (
	`id` int(11) unsigned NOT NULL AUTO_INCREMENT,
	`ip` varchar(39) NOT NULL,
	`board` varchar(58) CHARACTER SET utf8 NOT NULL,
	`time` int(11) NOT NULL,
	`posthash` char(32) NOT NULL,
	`filehash` char(32) DEFAULT NULL,
	`isreply` tinyint(1) NOT NULL,
	PRIMARY KEY (`id`),
	KEY `ip` (`ip`),
	KEY `posthash` (`posthash`),
	KEY `filehash` (`filehash`),
	KEY `time` (`time`)
) ENGINE=InnoDB DEFAULT CHARSET=ascii COLLATE=ascii_bin AUTO_INCREMENT=1 ;

-- Table structure for table `ban_appeals`
CREATE TABLE IF NOT EXISTS `ban_appeals` (
	`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
	`ban_id` int(10) unsigned NOT NULL,
	`time` int(10) unsigned NOT NULL,
	`message` text NOT NULL,
	`denied` tinyint(1) NOT NULL,
	PRIMARY KEY (`id`),
	KEY `ban_id` (`ban_id`),
	CONSTRAINT `fk_ban_id` FOREIGN KEY (`ban_id`) REFERENCES `bans`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1 ;

-- Table structure for table `pearl_extras`
CREATE TABLE IF NOT EXISTS `pearl_extras` (
	`ip` VARCHAR(45) CHARACTER SET ascii NOT NULL PRIMARY KEY,
	`discord` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
	`mobile` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
	`tuxler` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
	`vpn` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
	`incognito` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
	`wm` INT(4) NOT NULL DEFAULT 0,
	`hm` INT(4) NOT NULL DEFAULT 0,
	`wi` INT(4) NOT NULL DEFAULT 0,
	`hi` INT(4) NOT NULL DEFAULT 0,
	`kp` char(16) NOT NULL,
	`created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ;

-- Host: localhost
-- Generation Time: Jul 30, 2013 at 09:45 PM
-- Server version: 5.6.10
-- PHP Version: 5.3.15