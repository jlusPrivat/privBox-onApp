<?php
/*Just add all the db-queries here. Prefix is $_SESSION['installer']['sql_prefix']*/
$queries = array(
'Creating table "pb_alarms"' => 'CREATE TABLE IF NOT EXISTS `pb_alarms` (
  `alarmId` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `description` varchar(20) NOT NULL,
  `days` set(\'Mon\',\'Tue\',\'Wed\',\'Thu\',\'Fri\',\'Sat\',\'Sun\') NOT NULL,
  `time` int(4) NOT NULL COMMENT \'In minutes from midnight\',
  `active` tinyint(1) NOT NULL,
  `wavName` varchar(20) NOT NULL,
  `command` int(10) UNSIGNED NOT NULL,
  PRIMARY KEY (`alarmId`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1',

'Creating table "pb_commandActions"' => 'CREATE TABLE IF NOT EXISTS `pb_commandActions` (
  `actionId` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `commandId` int(15) UNSIGNED NOT NULL,
  `usePin` int(10) UNSIGNED NOT NULL,
  `useAs` set(\'on\',\'off\',\'toggle\',\'push\') NOT NULL,
  PRIMARY KEY (`actionId`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1',

'Creating table "pb_commands"' => 'CREATE TABLE IF NOT EXISTS `pb_commands` (
  `commandId` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `description` varchar(20) NOT NULL,
  `userFunc` varchar(20) DEFAULT NULL,
  `allowedForGuest` tinyint(1) NOT NULL,
  PRIMARY KEY (`commandId`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1',

'Creating table "pb_log"' => 'CREATE TABLE IF NOT EXISTS `pb_log` (
  `logId` int(15) UNSIGNED NOT NULL AUTO_INCREMENT,
  `sort` set(\'error\',\'timedAction\',\'loginToInterface\',\'loginToDesk\',\'loginToInterfaceFailed\',\'loginToDeskFailed\') NOT NULL,
  `time` int(20) NOT NULL,
  `userId` int(10) DEFAULT NULL,
  `content` text NOT NULL,
  PRIMARY KEY (`logId`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1',

'Creating table "pb_pins"' => 'CREATE TABLE IF NOT EXISTS `pb_pins` (
  `pinId` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `gpio` int(3) UNSIGNED DEFAULT NULL,
  `description` varchar(20) NOT NULL,
  `sort` set(\'inButton\',\'inVirtualLatch\',\'inSwitch\',\'output\') NOT NULL,
  `onPushed` int(10) NOT NULL COMMENT \'commandID\',
  `onReleased` int(10) NOT NULL COMMENT \'commandID\',
  `switchState` tinyint(1) NOT NULL,
  `defaultOnStartup` tinyint(1) NOT NULL,
  `special` set(\'alarmIn\',\'loginWebOut\',\'logoutIn\') NOT NULL,
  PRIMARY KEY (`pinId`),
  UNIQUE KEY `gpio` (`gpio`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1',

'Creating table "pb_system"' => 'CREATE TABLE IF NOT EXISTS `pb_system` (
  `id` tinyint(1) NOT NULL,
  `name` varchar(20) NOT NULL,
  `controllerLocalOnly` tinyint(1) NOT NULL,
  `currentlyLoggedIn` int(10) UNSIGNED NOT NULL,
  `commandsOnLogin` varchar(50) NOT NULL,
  `commandsOnLogout` varchar(50) NOT NULL,
  `getAnyCard` int(20) UNSIGNED DEFAULT NULL,
  `getCertainCard` int(20) UNSIGNED NOT NULL,
  `sessionTimeout` int(10) UNSIGNED NOT NULL,
  `deadMode` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1',

'Inserting values in "pb_system"' => 'INSERT INTO `pb_system` (`id`, `name`, `controllerLocalOnly`, `currentlyLoggedIn`, `commandsOnLogin`, `commandsOnLogout`, `getAnyCard`, `getCertainCard`, `sessionTimeout`, `deadMode`) VALUES
(1, \'PrivBox\', 0, 0, \'\', \'2\', 0, 0, 600, 1)',

'Creating table "pb_timings"' => 'CREATE TABLE IF NOT EXISTS `pb_timings` (
  `timingId` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `userId` int(10) UNSIGNED NOT NULL,
  `commandId` int(10) UNSIGNED NOT NULL,
  `days` set(\'Mon\',\'Tue\',\'Wed\',\'Thu\',\'Fri\',\'Sat\',\'Sun\') NOT NULL,
  `timeslotBegin` int(4) NOT NULL COMMENT \'in Minutes from Midnight\',
  `timeslotEnd` int(4) NOT NULL,
  `runByDefault` tinyint(1) NOT NULL,
  PRIMARY KEY (`timingId`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1',

'Creating table "pb_users"' => 'CREATE TABLE IF NOT EXISTS `pb_users` (
  `userId` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(40) NOT NULL,
  `serial` int(20) NOT NULL,
  `pwd` varchar(100) NOT NULL,
  `canSetAlarm` tinyint(1) NOT NULL,
  `canUseIFace` tinyint(1) NOT NULL,
  `canManage` tinyint(1) NOT NULL COMMENT \'Admin: Users, Pins, Commands\',
  `canSeeGeneralLog` tinyint(1) NOT NULL,
  `canEmptyLog` tinyint(1) NOT NULL,
  `canSetTimings` tinyint(1) NOT NULL,
  `active` tinyint(1) NOT NULL,
  PRIMARY KEY (`userId`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1'



);
?>
