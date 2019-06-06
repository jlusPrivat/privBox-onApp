<?php
session_start();

// Include needed files:
require_once "mysql.php";
//require_once "phpmailer.php";
//require_once "smtp.php";
require_once "pages.php";
require_once "user.php";

// Kill session, if above the limit:
if (isset($_SESSION["pb"])) {
	if (!isset($_SESSION["pb"]["last_time"]))
		$_SESSION["pb"]["last_time"] = time();
	elseif ($_SESSION["pb"]["last_time"] + (60 * $SYSTEM["session_lifetime"]) < time()) {
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





// Call the command:
function callCommand($id) {
	global $SYSTEM, $CONNECTION;
	
	// If dead_mode is active
	if ($SYSTEM["dead_mode"] == 1) {
		//echo $id . '<br />';
		return NULL;
	}
	
	// Collect needed Information about Command:
	$inf = mysqli_fetch_array(mysqli_query($CONNECTION, 'SELECT command, delay, after_delay FROM pb_commands WHERE id = '
	. $id . ' LIMIT 1'), MYSQLI_ASSOC);
	
	// Run the command:
	exec($inf["command"]);
	
	// Run a second command, if there is a set delay
	if ($inf["delay"] != 0 && !empty($inf["after_delay"])) {
		usleep($inf["delay"]);
		exec($inf["after_delay"]);
	}
}


// With this function you can overwrite a System-parameter:
function systemRewrite($key, $val) {
	global $CONNECTION, $SYSTEM;
	
	// First overwrite in DB:
	mysqli_query($CONNECTION, 'UPDATE pb_system SET ' . $key . ' = ' . (is_int($val) ? $val : '"' . $val . '"') . ' WHERE id = 1 LIMIT 1');
	
	// Then in Variable:
	$SYSTEM[$key] = $val;
}


// Here come all the other functions:
function writeLog($typ, $success, $userOverwrite = 0) {
	global $CONNECTION;
	
	mysqli_query($CONNECTION, 'INSERT INTO pb_log (typ, success, stamp, user) VALUES (' . $typ . ', ' . ($success ? '1' : '0')
	. ', ' . time() . ', ' . (isset($_SESSION["pb"]["id"]) ? $_SESSION["pb"]["id"] : $userOverwrite) . ')');
	
	// Notify, if wanted
	if (isset($_SESSION["pb"]["id"]) || $userOverwrite != 0) {
		$benutzer = new user(isset($_SESSION["pb"]["id"]) ? $_SESSION["pb"]["id"] : $userOverwrite);
		if ($benutzer->data["notify"] == 1)
			sendeMail("Benutzer " . $benutzer->data["username"] . " hat ein Event getriggered.\n\nTyp: " . $typ . "\nSuccess: " . ($success ? 'Ja' : 'Nein'));
	}
}


// Funktion zum Formatieren eines Datums in: Samstag, 11. April 2015
function formatDate($stamp) {
	
	$arrDays = array('Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag');
	$arrMonths = array('Januar', 'Februar', 'M&auml;rz', 'April', 'Mai', 'Juni',
	'Juli', 'September', 'Oktober', 'November', 'Dezember');
	
	return $arrDays[date('w', $stamp)] . ', ' . date('j', $stamp) . '. ' . $arrMonths[date('n', $stamp) - 1]
	. ' ' . date('Y', $stamp);
	
}


function sendeMail($message) {
	
	
}

?>