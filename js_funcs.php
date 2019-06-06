<?php
require_once "includes/core.php";

class js {
	
	static public function delCard() {
		global $USER, $CONNECTION;
		
		// Benutzer muss Admin sein
		if ($USER->data["admin"] != 1)
			die('Fehlende Berechtigung');
		
		if (!isset($_POST["id"]) || !is_numeric($_POST["id"]))
			die("ID nicht gesendet!");
		
		mysqli_query($CONNECTION, 'UPDATE pb_users SET card_id = "0" WHERE id = '
		. $_POST["id"] . ' LIMIT 1');
		die("OK");
		
	}
	
	
	static public function addCard() {
		global $USER, $CONNECTION;
		
		// Benutzer muss Admin sein
		if ($USER->data["admin"] != 1)
			die('Fehlende Berechtigung');
		
		if (!isset($_POST["id"]) || !is_numeric($_POST["id"]))
			die('Falsche ID');
		
		// Perform the first request to set the Value
		systemRewrite("active_any_key", "0");
		systemRewrite("active_timestamp", time());
		$i = 0;
		
		// Close the Session-File:
		session_write_close();
		
		while ($i < 31) {
			$i++;
			
			$row = mysqli_fetch_array(mysqli_query($CONNECTION, 'SELECT active_any_key FROM '
			. 'pb_system WHERE id = 1 LIMIT 1'), MYSQLI_ASSOC);
			
			if ($row["active_any_key"] != "0") {
				// Set the new Value:
				mysqli_query($CONNECTION, "UPDATE pb_users SET card_id = '" . $row["active_any_key"]
				. "' WHERE id = " . $_POST["id"] . " LIMIT 1");
				die($row["active_any_key"]);
			}
			
			// Save some time
			sleep(1);
		}
		die("Es konnte kein Key gefunden werden!");
		
	}
	
	
	static public function triggerCommand() {
		global $USER;
		
		// Trigger any command:
		if (isset($_POST["id"])) {
			if (!in_array($_POST["id"], $USER->getCurrentActions()))
				die("Keine Berechtigung");
			callCommand($_POST["id"]);
			die("OK");
		}
		else
			die("Fehler bei der Datenvermittlung");
	}
	
	
	static public function validPwd() {
		
		// The Values have to be Set
		if (!isset($_POST["id"]) || !isset($_POST["pwd"]))
			die("Es gab einen Datenuebertragungsfehler!");
		
		if (!is_numeric($_POST["id"]))
			die("Die ID wurde nicht korrekt uebertragen!");
		
		$benutzer = new user($_POST["id"]);
		if (md5($_POST["pwd"] . $benutzer->data["salt"]) == $benutzer->data["pwd"]) {
			// Everything is fine and Password is accepted
			$_SESSION["pb"]["authed_pwd_for"] = $_POST["id"];
			self::doLogin();
			die("OK");
		}
		else
			die("Falsches Passwort");
		
	}
	
	
	static public function validCard() {
		global $CONNECTION;
		$benutzer = new user($_POST["id"]);
		
		// To enable multiple requests:
		session_write_close();
		
		// The Values have to be Set
		if (!isset($_POST["id"]))
			die("Es gab einen Datenuebertragungsfehler!");
		if (!is_numeric($_POST["id"]))
			die("Die ID wurde nicht korrekt uebertragen!");
		
		// Set the marker in the DB:
		mysqli_query($CONNECTION, 'UPDATE pb_system SET active_certain_key = "' . $benutzer->data["card_id"]
		. '", active_timestamp = ' . time() . ' WHERE id = 1 LIMIT 1');
		
		// Do a loop to test:
		$i = 0;
		while ($i < 30) {
			$i++;
			$check = mysqli_fetch_array(mysqli_query($CONNECTION,
			'SELECT active_certain_key FROM pb_system WHERE id = 1 LIMIT 1'), MYSQLI_ASSOC);
			if ($check["active_certain_key"] == "0") {
				// OK, Now the bad part: Reopen the session:
				session_start();
				
				// All clear:
				$_SESSION["pb"]["authed_card_for"] = $_POST["id"];
				
				// If no Password is required:
				if (empty($benutzer->data["pwd"]))
					$_SESSION["pb"]["authed_pwd_for"] = $_POST["id"];
				
				// Check and Exit
				self::doLogin();
				die("OK");
			}
			sleep(1);
		}
		writeLog(3, false, $benutzer->data["id"]);
		systemRewrite("active_certain_key", "0");
	}
	
	
	static private function doLogin() {
		global $SYSTEM;
		
		// No, because not both are set
		if (!isset($_SESSION["pb"]["authed_pwd_for"], $_SESSION["pb"]["authed_card_for"]))
			return false;
		// No, because the Authorizations are not equal:
		if ($_SESSION["pb"]["authed_pwd_for"] != $_SESSION["pb"]["authed_card_for"])
			return false;
		// No, because the data is just wrong:
		if (empty($_SESSION["pb"]["authed_pwd_for"]) || !is_numeric($_SESSION["pb"]["authed_pwd_for"]))
			return false;
		
		// No I can have a closer look at the privilidges:
		$benutzer = new user($_SESSION["pb"]["authed_pwd_for"]);
		// No rights to do anything:
		if ($benutzer->data["active"] != 1)
			return false;
		// No rights to enter Iface
		if ($benutzer->data["iface"] == 0 || ($SYSTEM["lockdown"] == 1 && $benutzer->data["admin"] != 1))
			return false;
		
		// So, I think this covered pretty much every case, from now on the user is good to proceed:
		$_SESSION = array();
		$_SESSION["pb"]["id"] = $benutzer->data["id"];
		writeLog(3, true);
		return true;
		
	}
	
}

// Define the allowed actions for guests
$guestarray = array("validPwd", "validCard");

// Get the action:
if (!isset($_POST["action"]))
	die("Es wurde keine Aktion gesetzt");
if (!method_exists("js", $_POST["action"]))
	die("Die Methode wurde nicht gefunden");
if (isset($_SESSION["pb"]["id"]) && !empty($_SESSION["pb"]["id"]) && !in_array($_POST["action"], $guestarray))
	call_user_func("js::" . $_POST["action"]);
elseif (in_array($_POST["action"], $guestarray))
	call_user_func("js::" . $_POST["action"]);
else
	die("Methode nicht authorisiert");

?>