SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

CREATE TABLE `databases` (
  `database_name` varchar(30) COLLATE utf8_unicode_ci NOT NULL,
  `database_user` int(11) unsigned NOT NULL,
  `database_type` tinytext COLLATE utf8_unicode_ci NOT NULL,
  `database_desc` text COLLATE utf8_unicode_ci NOT NULL,
  UNIQUE KEY `database_name` (`database_name`),
  KEY `database_user` (`database_user`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE `grants` (
  `grant_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `grant_name` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`grant_id`),
  UNIQUE KEY `grant_name` (`grant_name`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE `groups` (
  `group_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `group_name` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`group_id`),
  UNIQUE KEY `group_name` (`group_name`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE `group_grant` (
  `group_id` int(11) unsigned NOT NULL,
  `grant_id` int(11) unsigned NOT NULL,
  PRIMARY KEY (`group_id`,`grant_id`),
  KEY `grant_id` (`grant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE `news` (
  `news_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `news_title` text COLLATE utf8_unicode_ci NOT NULL,
  `news_description` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `news_content` longtext COLLATE utf8_unicode_ci NOT NULL,
  `news_author` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `news_date` int(11) NOT NULL,
  `news_language` varchar(2) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`news_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE `quotas` (
  `quota_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `quota_name` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`quota_id`),
  UNIQUE KEY `quota_name` (`quota_name`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE `register` (
  `register_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `register_email` varchar(150) COLLATE utf8_unicode_ci NOT NULL,
  `register_code` varchar(32) COLLATE utf8_unicode_ci NOT NULL,
  `register_date` int(11) unsigned NOT NULL,
  PRIMARY KEY (`register_id`),
  UNIQUE KEY `register_email` (`register_email`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE `storages` (
  `storage_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `storage_path` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `storage_size` int(11) NOT NULL,
  PRIMARY KEY (`storage_id`),
  UNIQUE KEY `storage_path` (`storage_path`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE `tokens` (
  `token_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `token_value` tinytext COLLATE utf8_unicode_ci NOT NULL,
  `token_lease` int(11) unsigned NOT NULL,
  `token_name` tinytext COLLATE utf8_unicode_ci,
  `token_user` int(11) unsigned NOT NULL,
  PRIMARY KEY (`token_id`),
  KEY `token_user` (`token_user`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE `token_grant` (
  `token_id` int(11) unsigned NOT NULL,
  `grant_id` int(11) unsigned NOT NULL,
  PRIMARY KEY (`token_id`,`grant_id`),
  KEY `grant_id` (`grant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE `users` (
  `user_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_name` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `user_ldap` int(11) unsigned NOT NULL,
  `user_date` int(11) unsigned NOT NULL,
  `user_status` tinyint(1) NOT NULL DEFAULT '0',
  `user_last_notification` int(11) NOT NULL DEFAULT '0',
  `user_code` varchar(10) COLLATE utf8_unicode_ci NOT NULL DEFAULT '0',
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `user_name` (`user_name`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE `user_grant` (
  `user_id` int(11) unsigned NOT NULL,
  `grant_id` int(11) unsigned NOT NULL,
  PRIMARY KEY (`user_id`,`grant_id`),
  KEY `grant_id` (`grant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE `user_group` (
  `user_id` int(11) unsigned NOT NULL,
  `group_id` int(11) unsigned NOT NULL,
  PRIMARY KEY (`user_id`,`group_id`),
  KEY `group_id` (`group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE `user_quota` (
  `user_id` int(11) unsigned NOT NULL,
  `quota_id` int(11) unsigned NOT NULL,
  `quota_used` int(11) unsigned NOT NULL DEFAULT '0',
  `quota_max` int(11) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`user_id`,`quota_id`),
  KEY `quota_id` (`quota_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;


ALTER TABLE `databases`
  ADD CONSTRAINT `databases_ibfk_3` FOREIGN KEY (`database_user`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

ALTER TABLE `group_grant`
  ADD CONSTRAINT `group_grant_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `groups` (`group_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `group_grant_ibfk_2` FOREIGN KEY (`grant_id`) REFERENCES `grants` (`grant_id`) ON DELETE CASCADE;

ALTER TABLE `tokens`
  ADD CONSTRAINT `tokens_ibfk_1` FOREIGN KEY (`token_user`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

ALTER TABLE `token_grant`
  ADD CONSTRAINT `token_grant_ibfk_1` FOREIGN KEY (`token_id`) REFERENCES `tokens` (`token_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `token_grant_ibfk_2` FOREIGN KEY (`grant_id`) REFERENCES `grants` (`grant_id`) ON DELETE CASCADE;

ALTER TABLE `user_grant`
  ADD CONSTRAINT `user_grant_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_grant_ibfk_2` FOREIGN KEY (`grant_id`) REFERENCES `grants` (`grant_id`) ON DELETE CASCADE;

ALTER TABLE `user_group`
  ADD CONSTRAINT `user_group_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_group_ibfk_2` FOREIGN KEY (`group_id`) REFERENCES `groups` (`group_id`) ON DELETE CASCADE;

ALTER TABLE `user_quota`
  ADD CONSTRAINT `user_quota_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_quota_ibfk_2` FOREIGN KEY (`quota_id`) REFERENCES `quotas` (`quota_id`) ON DELETE CASCADE;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;