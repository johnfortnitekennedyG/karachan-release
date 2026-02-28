CREATE TABLE IF NOT EXISTS ``posts_{{ board }}`` (
	`id` int(11) unsigned NOT NULL AUTO_INCREMENT,
	
	`thread` int(11) DEFAULT NULL,
	`subject` varchar(100) DEFAULT NULL,
	`name` varchar(35) DEFAULT NULL,
	`trip` varchar(15) DEFAULT NULL,
	`capcode` varchar(50) DEFAULT NULL,

	`body` text NOT NULL,
	`body_nomarkup` text,
	`files` text DEFAULT NULL,
	`filehash` text CHARACTER SET ascii,
	`pearlsecurity` text DEFAULT NULL,
	`embed` text,

	`time` int(11) NOT NULL,
	`bump` int(11) DEFAULT NULL,
	`num_files` TINYINT(4) DEFAULT 0,
	`password` varchar(64) DEFAULT NULL,
	`ip` varchar(39) CHARACTER SET ascii NOT NULL,
	
	`sticky` TINYINT(1) UNSIGNED NOT NULL,
	`locked` TINYINT(1) UNSIGNED NOT NULL,
	`cycle` TINYINT(1) UNSIGNED NOT NULL,
	`pamped` TINYINT(1) UNSIGNED NOT NULL,
	`imagespoilered` TINYINT(1) UNSIGNED NOT NULL,
	`pendingapproval` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,

	UNIQUE KEY `id` (`id`),
	KEY `thread_id` (`thread`,`id`),
	KEY `filehash` (`filehash`(40)),
	KEY `time` (`time`),
	KEY `ip` (`ip`),
	KEY `list_threads` (`thread`, `sticky`, `bump`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1 ;

