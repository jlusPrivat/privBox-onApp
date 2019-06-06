<?php
/*Just add all the db-queries here. Prefix is $_SESSION['installer']['sql_prefix']*/
$queries = array(
'Creating table "pb_commands"' => 'CREATE TABLE IF NOT EXISTS `pb_commands` (
`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `command` text NOT NULL,
  `delay` int(20) NOT NULL,
  `after_delay` text NOT NULL,
  `autostart` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1',

'Creating table "pb_log"' => 'CREATE TABLE IF NOT EXISTS `pb_log` (
`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `typ` int(2) NOT NULL COMMENT \'0 System An; 1 System Reload; 2 Boot-up; 3 iFace; 4 Alarm\',
  `success` tinyint(1) NOT NULL,
  `stamp` int(20) NOT NULL,
  `user` int(10) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1',

'Creating table "pb_permission"' => 'CREATE TABLE IF NOT EXISTS `pb_permissions` (
`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user` int(10) NOT NULL,
  `command` int(10) NOT NULL,
  `from_time` int(20) NOT NULL,
  `to_time` int(20) NOT NULL,
  `days` set(\'1\',\'2\',\'3\',\'4\',\'5\',\'6\',\'7\') NOT NULL,
  `prefered` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1',

'Creating table "pb_system"' => 'CREATE TABLE IF NOT EXISTS `pb_system` (
  `id` int(10) unsigned NOT NULL,
  `lockdown` tinyint(1) NOT NULL,
  `dead_mode` tinyint(1) NOT NULL,
  `mail_from` varchar(50) NOT NULL,
  `mail_to` varchar(50) NOT NULL,
  `smtp_server` varchar(50) NOT NULL,
  `smtp_port` int(6) NOT NULL,
  `smtp_encryption` varchar(20) NOT NULL,
  `smtp_user` varchar(50) NOT NULL,
  `smtp_pwd` varchar(50) NOT NULL,
  `session_lifetime` int(5) NOT NULL,
  `public_server_interface` tinyint(1) NOT NULL,
  `browser_reload` int(10) NOT NULL COMMENT \'In Minutes\',
  `key_length` int(3) NOT NULL,
  `active_certain_key` varchar(20) NOT NULL,
  `active_any_key` varchar(20) NOT NULL,
  `active_timestamp` int(20) NOT NULL,
  `alarm_active` tinyint(1) NOT NULL,
  `alarm_once_timeout` tinyint(1) NOT NULL,
  `alarm_time` int(7) NOT NULL COMMENT \'minutes from midnight\',
  `alarm_days` varchar(30) NOT NULL,
  `alarm_commands` int(30) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1',

'Inserting values in "pb_system"' => 'INSERT INTO `pb_system` (`id`, `lockdown`, `dead_mode`, `mail_from`, `mail_to`, `smtp_server`, `smtp_port`, `smtp_encryption`, `smtp_user`, `smtp_pwd`, `session_lifetime`, `public_server_interface`, `browser_reload`, `key_length`, `active_certain_key`, `active_any_key`, `active_timestamp`, `alarm_active`, `alarm_once_timeout`, `alarm_time`, `alarm_days`, `alarm_commands`) VALUES
(1, 0, 1, \'\', \'\', \'\', 0, \'\', \'\', \'\', 30, 1, 5, 5, \'0\', \'1\', 0, 0, 0, 0, \'\', NULL)',

'Creating table "pb_users"' => 'CREATE TABLE IF NOT EXISTS `pb_users` (
`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `card_id` varchar(20) NOT NULL,
  `pwd` varchar(50) NOT NULL,
  `salt` int(5) NOT NULL,
  `notify` tinyint(1) NOT NULL,
  `admin` tinyint(1) NOT NULL,
  `active` tinyint(1) NOT NULL,
  `alarm_clock` tinyint(1) NOT NULL,
  `deactivate_alarm` tinyint(1) NOT NULL,
  `iface` tinyint(1) NOT NULL,
  `log` tinyint(1) NOT NULL,
  `per_quantif` int(1) NOT NULL COMMENT \'0 off; 1 hour; 2 day; 3 week; 4 month\',
  `per_count` int(4) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=latin1',



);
?>
