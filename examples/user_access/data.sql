DROP TABLE IF EXISTS `group_members`;
DROP TABLE IF EXISTS `group_access`;
DROP TABLE IF EXISTS `accesses`;
DROP TABLE IF EXISTS `groups`;
DROP TABLE IF EXISTS `users`;

CREATE TABLE `users` (
  `user_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(16) NOT NULL,
  `given_name` varchar(64) NOT NULL,
  `surname` varchar(64) NOT NULL,
  `sex` enum('male','female') NOT NULL,
  `birthdate` date NOT NULL,
  `email` varchar(64) DEFAULT '',
  `password` varchar(70) DEFAULT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `groups` (
  `group_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  PRIMARY KEY (`group_id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `accesses` (
  `access_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `code_name` varchar(20) NOT NULL,
  PRIMARY KEY (`access_id`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `code_name` (`code_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `group_access` (
  `access_id` int(10) unsigned NOT NULL,
  `group_id` int(10) unsigned NOT NULL,
  `valid_until` date DEFAULT '0000-00-00',
  `permanent` tinyint(1) NOT NULL,
  PRIMARY KEY (`group_id`,`access_id`),
  KEY `access_id` (`access_id`),
  FOREIGN KEY (`access_id`) REFERENCES `accesses` (`access_id`) ON DELETE CASCADE,
  FOREIGN KEY (`group_id`) REFERENCES `groups` (`group_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `group_members` (
  `user_id` int(10) unsigned NOT NULL,
  `group_id` int(10) unsigned NOT NULL,
  `valid_until` date DEFAULT '0000-00-00',
  `permanent` tinyint(1) NOT NULL,
  PRIMARY KEY (`group_id`,`user_id`),
  KEY `user_id` (`user_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  FOREIGN KEY (`group_id`) REFERENCES `groups` (`group_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
