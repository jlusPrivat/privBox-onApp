<?php
require_once "includes/core.php";

class js {
	
	static public function delCard() {
		global $USER, $CONNECTION;
		
		// Benutzer muss Admin sein
		if ($USER->data["canManage"] != 1)
			die('Fehlende Berechtigung');
		
		if (!isset($_POST["id"]) || !is_numeric($_POST["id"]))
			die("ID nicht gesendet!");
		
		mysqli_query($CONNECTION, 'UPDATE pb_users SET serial = 0 WHERE userId = '
		. $_POST["id"] . ' LIMIT 1');
		die("OK");
		
	}
	
	
	static public function addCard() {
		global $USER, $CONNECTION;
		
		// Benutzer muss Admin sein
		if ($USER->data["canManage"] != 1)
			die('Fehlende Berechtigung');
		
		if (!isset($_POST["id"]) || !is_numeric($_POST["id"]))
			die('Falsche ID');
		
		// Perform the first request to set the Value
		switchLoginWebOut(true, $USER->data['name']);
		systemRewrite("getAnyCard", 0);
		$i = 0;
		
		// Close the Session-File:
		session_write_close();
		
		while ($i < 31) {
			$i++;
			
			$row = mysqli_fetch_array(mysqli_query($CONNECTION, 'SELECT getAnyCard FROM '
			. 'pb_system WHERE id = 1 LIMIT 1'), MYSQLI_ASSOC);
			
			if ($row["getAnyCard"] != "0") {
				switchLoginWebOut(false);
				// Set the new Value:
				mysqli_query($CONNECTION, "UPDATE pb_users SET serial = '" . $row["getAnyCard"]
				. "' WHERE userId = " . $_POST["id"] . " LIMIT 1");
				die($row["getAnyCard"]);
			}
			
			// Save some time
			sleep(1);
		}
		switchLoginWebOut(false);
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
		if (password_verify($_POST['pwd'], $benutzer->data['pwd'])) {
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
		
		// The Values have to be Set
		if (!isset($_POST["id"]))
			die("Es gab einen Datenuebertragungsfehler!");
		if (!is_numeric($_POST["id"]))
			die("Die ID wurde nicht korrekt uebertragen!");
		if (!empty($SYSTEM['getCertainCard']))
			die("Es gibt schon einen aktiven Pull");
				
		// If no Password and card is required:
		if (empty($benutzer->data["pwd"]) && $benutzer->data['serial'] == 0) {
			$_SESSION["pb"]["authed_pwd_for"] = $_POST["id"];
			$_SESSION["pb"]["authed_card_for"] = $_POST["id"];
			self::doLogin();
			die('OK');
		}
		
		// Set the marker in the DB:
		mysqli_query($CONNECTION, 'UPDATE pb_system SET getCertainCard = "' . $benutzer->data["serial"]
		. '" WHERE id = 1 LIMIT 1');
		
		// To enable multiple requests:
		session_write_close();
		switchLoginWebOut(true, $benutzer->data['name']);
		
		// Do a loop to test:
		$i = 0;
		while ($i < 30) {
			$i++;
			$check = mysqli_fetch_array(mysqli_query($CONNECTION,
			'SELECT getCertainCard FROM pb_system WHERE id = 1 LIMIT 1'), MYSQLI_ASSOC);
			if ($check["getCertainCard"] == 0) {
				// OK, Now the bad part: Reopen the session:
				session_start();
				
				// All clear:
				$_SESSION["pb"]["authed_card_for"] = $_POST["id"];
				
				// Check and Exit
				self::doLogin();
				switchLoginWebOut(false);
				die("OK");
			}
			sleep(1);
		}
		systemRewrite("getCertainCard", 0);
		switchLoginWebOut(false);
		writeLog('loginToInterfaceFailed', 'Webinterface: Login Timeout', $_POST['id']);
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
		
		// No I can have a closer look at the privileges:
		$benutzer = new user($_SESSION["pb"]["authed_pwd_for"]);
		// No rights to do anything:
		if ($benutzer->data["active"] != 1)
			return false;
		// No rights to enter Iface
		if ($benutzer->data["canUseIFace"] == 0)
			return false;
		
		// So, I think this covered pretty much every case, from now on the user is good to proceed:
		$_SESSION = array();
		$_SESSION["pb"]["id"] = $benutzer->data["userId"];
		writeLog('loginToInterface', 'Webinterface: Login', $benutzer->data['userId']);
		return true;
		
	}
	
	
	static public function abortLogin() {
		global $SYSTEM;
		
		systemRewrite('getCertainCard', 0);
		switchLoginWebOut(false);
	}
	
}

// Define the allowed actions for guests
$guestarray = array("validPwd", "validCard", "abortLogin");

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