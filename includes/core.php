<?php
session_start();

// Include needed files:
require_once "mysql.php";
require_once "pages.php";
require_once "user.php";

// Kill session, if above the limit:
if (isset($_SESSION["pb"])) {
	if (!isset($_SESSION["pb"]["last_time"]))
		$_SESSION["pb"]["last_time"] = time();
	elseif ($_SESSION["pb"]["last_time"] + (60 * $SYSTEM["sessionTimeout"]) < time()) {
		$_SESSION = array();
		session_destroy();
	}
	else
		$_SESSION["pb"]["last_time"] = time();
}

// User is logged in:
if (isset($_SESSION["pb"]["id"]) && !empty($_SESSION["pb"]["id"])) {
	$USER = new user($_SESSION["pb"]["id"]);
}


// Logs a user out from the desk:
function logoutFromDesk() {
	global $SYSTEM, $CONNECTION;
	$commands = empty($SYSTEM['commandsOnLogout']) ? array() : explode(',', $SYSTEM['commandsOnLogout']);
	foreach ($commands as $command) {
		callCommand($command);
	}
	writeLog('loginToDesk', 'Logged out from Desk', $SYSTEM['currentlyLoggedIn']);
	systemRewrite('currentlyLoggedIn', 0);
	informPinApplication('action=LOGIN_LOGOUT_DESK:;:username=');
}


function logInToDesk($userId) {
	global $SYSTEM, $CONNECTION;
	$member = new user($userId);
	
	// Dont do it, if the user is blocked:
	if ($member->data['active'] != 1)
		return false;
	
	// If someone else is logged, log him out:
	if ($SYSTEM['currentlyLoggedIn'] != 0)
		logoutFromDesk();
	
	$commands = empty($SYSTEM['commandsOnLogin']) ? array() : explode(',', $SYSTEM['commandsOnLogin']);
	foreach ($commands as $command) {
		callCommand($command);
	}
	writeLog('loginToDesk', 'Desk: Logged in', $userId);
	systemRewrite('currentlyLoggedIn', $userId);
	
	// Call the user timed Functions now:
	$commands = $member->getCurrentActions(true);
	foreach ($commands as $command) {
		callCommand($command);
	}
	
	informPinApplication('action=LOGIN_LOGOUT_DESK:;:username=' . $member->data['name']);
}


// Call the command:
// CheckPerm: false Do not Check; 0 Check for Guest; other int check for userId
function callCommand($id, $checkPerm = false) {
	global $CONNECTION;
	include_once 'userFuncs.php';
	
	// Exit, if there is no real id
	if ($id == 0) return false;
	
	// Collect needed Information about Command:
	$command = mysqli_fetch_array(mysqli_query($CONNECTION,
		'SELECT * FROM pb_commands WHERE commandId = ' . $id . ' LIMIT 1'), MYSQLI_ASSOC);
	
	
	// Do the permission check, if needed:
	// Check for Guests:
	if ($checkPerm === 0) {
		if ($command['allowedForGuest'] != 1)
			return false;
	}
	// Check for the user
	elseif (is_numeric($checkPerm)) {
		if (!in_array($id, (new user($checkPerm))->getCurrentActions()))
			return false;
	}
	
	// Collect intel about the actions:
	$actions = mysqli_query($CONNECTION, 'SELECT pinId, useAs, gpio, switchState FROM pb_commandActions A '
		. 'RIGHT JOIN pb_pins B ON A.usePin = B.pinId WHERE commandId = ' . $id);
	while ($action = mysqli_fetch_array($actions, MYSQLI_ASSOC)) {
		switch ($action['useAs']) {
			case 'on':
				switchOutput($action['gpio'], 1);
				$newVal = 1;
			break; case 'off':
				switchOutput($action['gpio'], 0);
				$newVal = 0;
			break; case 'toggle':
				$newVal = $action['switchState'] == 1 ? '0' : '1';
				switchOutput($action['gpio'], $newVal);
			break; case 'push':
				switchOutput($action['gpio'], 1);
				sleep(1);
				switchOutput($action['gpio'], 0);
				$newVal = 0;
			break;
		}
		mysqli_query($CONNECTION, 'UPDATE pb_pins SET switchState = ' . $newVal
			. ' WHERE pinId = ' . $action['pinId'] . ' LIMIT 1');
	}
	
	// Run the user Command:
	if (!empty($command['userFunc']))
		call_user_func('userFuncs::' . $command['userFunc']);
	return true;
}


// This switches the pins with special loginWebOut and updates the pinApplication
function switchLoginWebOut ($state, $username = '') {
	global $CONNECTION;
	// Update the pins:
	mysqli_query($CONNECTION, 'UPDATE pb_pins SET switchState = ' . ($state ? 1 : 0)
	. ' WHERE FIND_IN_SET("loginWebOut", special)>0');
	// Get the pins:
	$sql = mysqli_query($CONNECTION, 'SELECT gpio FROM pb_pins '
	. 'WHERE FIND_IN_SET("loginWebOut", special)>0');
	while ($row = mysqli_fetch_array($sql, MYSQLI_ASSOC))
		switchOutput($row['gpio'], ($state ? 1 : 0));
		
	informPinApplication('action=CARD_REQUIRED:;:newTime=' . ($state ? '30' : '0')
						 . ':;:newUserName=' . $username);
}



function switchOutput ($pin, $state) {
	informPinApplication('action=SWITCH_PINS:;:'
		. ($state ? 'offPins=:;:onPins=' : 'onPins=:;:offPins=')
		. $pin);
}


function informPinApplication ($message) {
	global $SYSTEM;
	// If dead_mode is active
	if ($SYSTEM["deadMode"] == 1) {
		return;
	}
	
	$errno = ''; $errstr = '';
	$fp = @stream_socket_client("tcp://127.0.0.1:4444", $errno, $errstr, 10);
	if ($fp) {
		stream_set_timeout($fp, 3);
		fwrite($fp, $message);
		fclose($fp);
	}
}


function getNextAlarm () {
	global $CONNECTION;
	
	$currentSecFromMidnight = date('s') + 60*date('i') + 3600*date('G');
	// Select all AlarmClocks:
	$alarms = mysqli_query($CONNECTION, 'SELECT alarmId, days, time, active, description, wavName,
						   isBackground FROM pb_alarms');
	$alarmsId = Array();
	$alarmsDescriptions = Array();
	$alarmAreBackground = Array();
	$alarmsTriggerIn = Array();
	$alarmSounds = Array();
	while ($alarm = mysqli_fetch_array($alarms, MYSQLI_ASSOC)) {
		// If not active alarm clock:
		if ($alarm['active'] == 0)
			continue;
		
		// Only look at, if it is running today or tomorrow:
		$days = explode(',', $alarm['days']);
		// For Today:
		if (in_array(date('D'), $days))
			$triggerIn = $alarm['time'] * 60 - $currentSecFromMidnight;
		// For Tomorrow
		else if (in_array(date('D', time()+86400), $days))
			$triggerIn = $alarm['time'] * 60 + 86400 - $currentSecFromMidnight;
		
		// If it passed already, dont consider it:
		if (isset($triggerIn) && $triggerIn > 1) {
			$alarmsId[] = $alarm['alarmId'];
			$alarmsDescriptions[] = $alarm['description'];
			$alarmAreBackground[] = $alarm['isBackground'] ? 'yes' : 'no';
			$alarmsTriggerIn[] = $triggerIn;
			$alarmSounds[] = (file_exists(__DIR__ . '/../songs/' . $alarm['wavName'])
							   ? __DIR__ . '/../songs/' . $alarm['wavName'] : '');
		}
	}
	return 'alarmsIds=' . implode(',', $alarmsId)
	. ':;:alarmsDescriptions=' . implode(',', $alarmsDescriptions)
	. ':;:alarmsAreBackground=' . implode(',', $alarmAreBackground)
	. ':;:alarmsTriggerIn=' . implode(',', $alarmsTriggerIn)
	. ':;:alarmsSounds=' . implode(',', $alarmSounds);
}


// With this function you can overwrite a System-parameter:
function systemRewrite($key, $val) {
	global $CONNECTION, $SYSTEM;
	
	// First overwrite in DB:
	mysqli_query($CONNECTION, 'UPDATE pb_system SET ' . $key . ' = '
				 . (is_int($val) ? $val : '"' . $val . '"') . ' WHERE id = 1 LIMIT 1');
	
	// Then in Variable:
	$SYSTEM[$key] = $val;
}


// This will write an entry in the log:
// Allowed sorts: error, timedAction, loginToInterface, loginToDesk
function writeLog($sort, $content, $userId = 0) {
	global $CONNECTION;
	$allowedSorts = array('error', 'timedAction', 'loginToInterface', 'loginToInterfaceFailed',
			'loginToDesk', 'loginToDeskFailed');
	
	// Checks the sorts to be allowed to get into the db:
	$sorts = explode(',', $sort);
	foreach ($sorts as $one) {
		if (!in_array($one, $allowedSorts)) {
			writeLog('error', 'Unknown log type: ' . $one);
			return NULL;
		}
	}
	
	// Write log into db:
	mysqli_query($CONNECTION, 'INSERT INTO pb_log (sort, time, userId, content) VALUES '
		. '("' . $sort . '", ' . time() . ', ' . $userId . ', "' . $content . '")');
}


// Funktion zum Formatieren eines Datums in: Samstag, 11. April 2015
function formatDate($stamp) {
	
	$arrDays = array('Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag');
	$arrMonths = array('Januar', 'Februar', 'M&auml;rz', 'April', 'Mai', 'Juni',
	'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember');
	
	return $arrDays[date('w', $stamp)] . ', ' . date('j', $stamp) . '. ' . $arrMonths[date('n', $stamp) - 1]
	. ' ' . date('Y', $stamp);
	
}


?>