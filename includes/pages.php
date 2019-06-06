<?php
class pages {
	
	// The System-Settings can be changed here:
	static public function system() {
		global $USER, $CONNECTION, $SCRIPTS;
		
		if ($USER->data["admin"] == 0 && $USER->data["log"] == 0) {
			return self::noRights();
		}
		
		// Return the Log
		if ($USER->data["admin"] == 0 && $USER->data["log"] == 1 || isset($_GET["log"])) {
			if ($USER->data["admin"] == 1 && isset($_GET["delLog"]) && $_GET["delLog"] == 1) {
				mysqli_query($CONNECTION, 'DELETE FROM pb_log WHERE stamp < ' . (time() - 259200));
			}
			
			return '<h2>System</h2>'
			. ($USER->data["admin"] == 1
			? '<a href="index.php?action=system">&larr; Zur&uuml;ck</a><br /><br />' : '')
			. self::history(30, 0)
			. ($USER->data["admin"] == 1 ? '<br /><br /><a '
			. 'href="index.php?action=system&log=1&delLog=1">Alles von vor 3 Tagen l&ouml;schen</a>' : '');
		}
		
		// Output the Commands:
		if (isset($_GET["commands"])) {
			
			// Insert new Command
			if (isset($_GET["addCommand"])) {
				mysqli_query($CONNECTION, 'INSERT INTO pb_commands (name, command, delay, after_delay, autostart) '
				. 'VALUES ("Neuer Befehl", "", 0, "", 0)');
			}
			// Delete Command:
			if (isset($_GET["delCommand"]) && is_numeric($_GET["delCommand"])) {
				mysqli_query($CONNECTION, 'DELETE FROM pb_commands WHERE id = ' . $_GET["delCommand"]
				. ' LIMIT 1');
			}
			
			// If Data has been sent:
			if (isset($_GET["sent"])) {
				
				// Map the Post data
				$d = array();
				$allowed = array('name', 'command', 'delay', 'after_delay', 'autostart');
				foreach ($_POST as $name => $vals) {
					
					// Here come Data like "command" => array("1" => "gpio", ...)
					
					// No allowed _POST
					if (!in_array($name, $allowed))
						continue;
					
					foreach ($vals as $key => $val) {
						if ($name == "autostart")
							$val = $val == "1" ? 1 : 0;
						if ($name == "delay" && !is_numeric($val))
							continue;
						$d[$key][] = $name . ' = ' . (is_int($val) ? $val : '"' . $val . '"');
					}
					
				}
				
				// Run the requests:
				$msg = '';
				foreach ($d as $key => $val) {
					if (!mysqli_query($CONNECTION, 'UPDATE pb_commands SET ' . implode(", ", $val) . ' WHERE id = '
					. $key . ' LIMIT 1'))
						$msg = '<p style="color: #800000;"><strong>Fehler:</strong> '
						. mysqli_error($CONNECTION) . '</p>';
				}
				if (empty($msg))
					$msg = '<p style="color: #008000;"><strong>Meldung:</strong> Erfolgreich gespeichert!</p>';
				
			}
			
			// Select all Commands
			$sql = mysqli_query($CONNECTION, "SELECT id, name, command, delay, after_delay, autostart FROM pb_commands");
			$output = ''; $bgc = 'DDDDFF';
			while ($row = mysqli_fetch_array($sql, MYSQLI_ASSOC)) {
				$bgc = $bgc == 'DDDDFF' ? 'EEEEEE' : 'DDDDFF';
				$output .= '<tr style="background: linear-gradient(#8080FF, #' . $bgc . ' 10px);">'
				. '<td style="padding-left: 10px;"><a href="index.php?action=system&commands=1&delCommand=' . $row["id"]
				. '"><img src="imgs/delete_small.png" height="16" alt="Del" /></a></td>'
				. '<td style="text-align: center;">Autostart: <input type="radio" name="autostart[' . $row["id"] . ']" value="1"'
				. ($row["autostart"] == 1 ? ' checked="checked"' : '') . ' /> An | '
				. '<input type="radio" name="autostart[' . $row["id"] . ']" value="0"'
				. ($row["autostart"] == 0 ? ' checked="checked"' : '')  . ' /> Aus<br />'
				. 'Name: <input type="text" value="' . $row["name"] . '" name="name[' . $row["id"]
				. ']" size="30" /></td><td style="padding: 10px;">'
				. '<textarea cols="30" rows="1" name="command[' . $row["id"] . ']">' . $row["command"] . '</textarea>'
				. '<br /><br />Nach Verz&ouml;gerung von '
				. '<input type="number" value="' . $row["delay"] . '" name="delay[' . $row["id"] . ']" style="width: 80px;" />'
				. ' 10<sup>-6</sup>s:<br />'
				. '<textarea cols="30" rows="1" name="after_delay[' . $row["id"] . ']">' . $row["after_delay"] . '</textarea>'
				. '</td></tr>';
			}
			
			return '<h2>System</h2>'
			. '<a href="index.php?action=system&commands=1&addCommand=1">Befehl hinzuf&uuml;gen</a>'
			. '<a href="index.php?action=system" class="right">Zur&uuml;ck &rarr;</a>'
			. '<div class="clear"></div>'
			. (isset($msg) ? $msg : '')
			. '<form action="index.php?action=system&commands=1&sent=1" method="post">'
			. '<input type="submit" value="Speichern" class="right" /><br /><br />'
			. '<table border="0" cellspacing="0" style="width: 100%;">' . $output
			. '</table><br /><input type="submit" value="Speichern" class="right" /></form>';
		}
		
		
		$SCRIPTS[] = 'system.js';
		
		
		// Evaluate the Sent Data:
		if (isset($_GET["sent"])) {
			$query = array();
			$textData = array('mail_from', 'mail_to', 'smtp_server', 'smtp_encryption', 'smtp_user');
			$numData = array('smtp_port', 'session_lifetime', 'browser_reload', 'key_length');
			$boolData = array('lockdown', 'dead_mode', 'public_server_interface');
			
			// Process the Post:
			foreach ($_POST as $key => $val) {
				
				// If not wanted:
				if (in_array($key, $textData))
					$val = '"' . $val . '"';
				elseif (in_array($key, $boolData))
					$val = $val == "1" ? 1 : 0;
				elseif ($key == "pwdHelper" && $val == "1") {
					$key = 'smtp_pwd';
					$val = isset($_POST["pwd"]) && !empty($_POST["pwd"]) ? '"' . $_POST["pwd"] . '"' : '""';
				}
				elseif (!in_array($key, $numData))
					continue;
				
				// Insert into the query:
				$query[] = $key . ' = ' . $val;
				
			}
			
			if (mysqli_query($CONNECTION, 'UPDATE pb_system SET ' . implode(', ', $query) . ' WHERE id = 1 LIMIT 1'))
				$msg = '<p style="color: #008000;"><strong>Meldung:</strong> Erfolgreich gespeichert.</p>';
			else
				$msg = '<p style="color: #800000;"><strong>Fehler:</strong> Speicherfehler. '
				. mysqli_error($CONNECTION) . '</p>';
			
		}
		
		// Get the Data:
		$sys = mysqli_fetch_array(mysqli_query($CONNECTION, 'SELECT lockdown, dead_mode, mail_from, '
		. 'mail_to, smtp_server, smtp_port, smtp_encryption, smtp_user, session_lifetime, '
		. 'public_server_interface, browser_reload, key_length FROM '
		. 'pb_system WHERE id = 1 LIMIT 1'), MYSQLI_ASSOC);
		
		
		return '<h2>System</h2>'
		. '<a href="index.php?action=system&commands=1">&larr; Befehle verwalten</a>'
		. '<a href="index.php?action=system&log=1" class="right">Ereignissprotokoll &rarr;</a>'
		. '<div class="clear"></div>'
		. (isset($msg) ? $msg : '')
		. '<form action="index.php?action=system&sent=1" method="post">'
		. '<table border="0" style="width: 80%;"><tr>'
		. '<td>Webiface nur f&uuml;r Admins: </td>'
		. '<td><input type="radio" name="lockdown" value="1"'
		. ($sys["lockdown"] == 1 ? ' checked="checked"' : '') . ' /> Ja | '
		. '<input type="radio" name="lockdown" value="0"'
		. ($sys["lockdown"] == 0 ? ' checked="checked"' : '') . ' /> Nein</td>'
		. '</tr><tr><td>Commands ausf&uuml;hren: </td>'
		. '<td><input type="radio" name="dead_mode" value="0"'
		. ($sys["dead_mode"] == 0 ? ' checked="checked"' : '') . ' /> Ja | '
		. '<input type="radio" name="dead_mode" value="1"'
		. ($sys["dead_mode"] == 1 ? ' checked="checked"' : '') . ' /> Nein</td>'
		. '</tr><tr><td>Offene "controller.php": </td>'
		. '<td><input type="radio" name="public_server_interface" value="1"'
		. ($sys["public_server_interface"] == 1 ? ' checked="checked"' : '') . ' /> Ja | '
		. '<input type="radio" name="public_server_interface" value="0"'
		. ($sys["public_server_interface"] == 0 ? ' checked="checked"' : '') . ' /> Nein</td>'
		. '</tr><tr><td>Aktualisierungsrate Cache: </td>'
		. '<td><input type="number" value="' . $sys["browser_reload"]
		. '" name="browser_reload" style="width: 50px;" /> In Minuten</td>'
		. '</tr><tr><td>Sitzungshaltbarkeit: </td>'
		. '<td><input type="number" value="' . $sys["session_lifetime"]
		. '" name="session_lifetime" style="width: 50px;" /> In Minuten</td>'
		. '</tr><tr><td>Schl&uuml;ssell&auml;nge: </td>'
		. '<td><input type="number" value="' . $sys["key_length"]
		. '" name="key_length" style="width: 50px;" /></td>'
		. '</tr><tr><td>SMTP Absender: </td>'
		. '<td><input type="text" value="' . $sys["mail_from"]
		. '" name="mail_from" /></td>'
		. '</tr><tr><td>SMTP Empf&auml;nger: </td>'
		. '<td><input type="text" value="' . $sys["mail_to"]
		. '" name="mail_to" /></td>'
		. '</tr><tr><td>SMTP-Server: </td>'
		. '<td><input type="text" value="' . $sys["smtp_server"]
		. '" name="smtp_server" /></td>'
		. '</tr><tr><td>SMTP Port: </td>'
		. '<td><input type="number" value="' . $sys["smtp_port"]
		. '" name="smtp_port" style="width: 50px;" /></td>'
		. '</tr><tr><td>SMTP Sicherheit: </td>'
		. '<td><input type="text" value="' . $sys["smtp_encryption"]
		. '" name="smtp_encryption" /></td>'
		. '</tr><tr><td>SMTP Benutzername: </td>'
		. '<td><input type="text" value="' . $sys["smtp_user"]
		. '" name="smtp_user" /></td>'
		. '</tr><tr><td>SMTP Passwort: </td>'
		. '<td><input type="password" name="pwd" disabled="disabled" />'
		. '<input type="checkbox" name="pwdHelper" value="1" onclick="togglePwd();" /></td>'
		. '</tr></table><input type="submit" value="Speichern" /></form>';
	}
	
	
	static public function user() {
		global $USER, $SYSTEM, $CONNECTION, $SCRIPTS;
		
		// If a user has to be changed:
		if ($USER->data["admin"] != 1 || isset($_GET["subac"])) {
			
			// Replace the subac with a value:
			if (!isset($_GET["subac"]))
				$_GET["subac"] = "editBasic";
			
			// Get the wanted ID:
			if (!isset($_GET["id"]) || $USER->data["admin"] != 1)
				$id = $USER->data["id"];
			else
				$id = is_numeric($_GET["id"]) ? $_GET["id"] : $USER->data["id"];
			
			
			
			
			// Get the wanted subaction:
			switch($_GET["subac"]) {
				
				case 'editCard':
					$SCRIPTS[] = 'editCard.js';
					$ben = new user($id);
					$heading = 'Karte festlegen';
					$echo = '<div id="countdown" class="right" style="font-size: 20pt;">30</div>'
					. '<input type="button" value="Karte registrieren" onclick="addCard(' . $id . ');" />'
					. ' <input type="button" value="Karten-ID entfernen" onclick="delCard(' . $id . ');" /><br />'
					. '<p>Aktuelle Karten-ID: <span id="cardId" style="font-weight: bold;">'
					. (empty($ben->data["card_id"]) ? '<span style="color: #800000;">Keine Karte registriert</span>'
					: '#' . $ben->data["card_id"]) . '</span>';
					break;
				
				case 'log': 
					$heading = 'Benutzerprotokoll';
					$echo = self::history(30, $id);
					break;
				
				
				
				case 'editPermissions':
					
					$heading = 'Berechtigungen';
					$echo = '';
					$ben = new user($id);
					
					// If Data has been sent:
					if (isset($_GET["sent"])) {
						
						//die(nl2br(print_r($_POST, true)));
						
						// Process the $_POSTs
						$adminlist = array('prefered', 'days', 'from_time', 'to_time');
						$d = array();
						foreach ($_POST as $type => $valArr) {
							
							// if type not known, just jump:
							if (($USER->data["admin"] == 1 && !in_array($type, $adminlist))
							|| ($USER->data["admin"] != 1 && $type != 'prefered'))
								continue;
							
							// Process the different vals for the keys
							foreach ($valArr as $key => $answer) {
								
								// Convert the answer, if necces:
								if ($type == "from_time" || $type == "to_time") {
									$explode = explode(":", $answer);
									if (count($explode) == 2 && is_numeric($explode[0])
									&& is_numeric($explode[1]))
										$answer = ($explode[0] * 60) + $explode[1];
									else
										$answer = 0;
								}
								elseif ($type == "days") {
									if (!is_array($answer))
										$answer = '';
									else
										$answer = implode(",", $answer);
								}
								
								// Append to the queries
								$d[$key][] = $type . ' = ' . (is_int($answer) ? $answer : '"' . $answer . '"');
								
							}
							
							
						}
						
						// Run the Queries:
						$i = 0;
						$msg = '';
						foreach ($d as $key => $val) {
							$i++;
							if (!mysqli_query($CONNECTION, 'UPDATE pb_permissions SET ' . implode(", ", $val)
							. ' WHERE id = ' . $key . ' LIMIT 1'))
								$msg .= '<p style="color: #800000;"><strong>Fehler:</strong> '
								. mysqli_error($CONNECTION) . '</p>';
							/*echo 'UPDATE pb_permissions SET ' . implode(", ", $val)
							. ' WHERE id = ' . $key . ' LIMIT 1<br />';*/
						}
						$msg .= '<p style="color: #008000;"><strong>Meldung:</strong> ' . $i . ' Sets wurden bearbeitet!';
						
					}
					
					// Add a new Permissionset:
					if (isset($_GET["addSet"]) && is_numeric($_GET["addSet"]) && $USER->data["admin"] == 1) {
						mysqli_query($CONNECTION, 'INSERT INTO pb_permissions (user, command, from_time, to_time, days, '
						. 'prefered) VALUES (' . $id . ', ' . $_GET["addSet"] . ', 0, 1440, "", 0)');
					}
					// Delete a Permissionset:
					if (isset($_GET["delSet"]) && is_numeric($_GET["delSet"]) && $USER->data["admin"] == 1) {
						mysqli_query($CONNECTION, 'DELETE FROM pb_permissions WHERE id = ' . $_GET["delSet"] . ' LIMIT 1');
					}
					
					// Return error, if deactivate_alarm == 1
					if ($ben->data["deactivate_alarm"] == 1)
						$echo = '<p style="color: #800000;"><strong>Meldung:</strong> '
						. 'Sie haben Alarm Aktivit&auml;t Umschalten Aktiv. Alle hier '
						. 'vorgenommen Einstellungen werden daher keinen Effekt haben!</p>';
					
					// Select the permissions:
					$sql = mysqli_query($CONNECTION, 'SELECT c.id AS cid, name, p.id AS pid, '
					. 'from_time, to_time, days, prefered FROM pb_commands c '
					. 'LEFT JOIN pb_permissions p ON p.command = c.id '
					. 'WHERE user = ' . $id . ' ORDER BY from_time');
					
					// Fetch into an array:
					$vals = array();
					while ($row = mysqli_fetch_array($sql, MYSQLI_ASSOC)) {
						$vals[$row["cid"]][] = array(
							"name" => $row["name"],
							"pid" => $row["pid"],
							"from_time" => $row["from_time"],
							"to_time" => $row["to_time"],
							"days" => $row["days"],
							"prefered" => $row["prefered"]
						);
					}
					
					$echo .= (isset($msg) ? $msg : '')
					. '<form action="index.php?action=user&subac=editPermissions&id='
					. $id . '&sent=1" method="post"><input type="submit" value="Speichern" class="right" />';
					
					// A list of all possible commands, so it can be added:
					if ($USER->data["admin"] == 1) {
						$SCRIPTS[] = 'editPermissions.js';
						$sql = mysqli_query($CONNECTION, 'SELECT id, name FROM pb_commands');
						$echo .= '<select><option>Neue Berechtigung</option>';
						while ($row = mysqli_fetch_array($sql, MYSQLI_ASSOC)) {
							// I have to add the User ID too, because js cant get it.
							$echo .= '<option onclick="addSet(' . $id . ', ' . $row["id"]
							. ')">' . $row["name"] . '</option>';
						}
						$echo .= '</select>';
					}
					
					$echo .= '<div class="clear"></div>';
					
					// Output the commands
					foreach ($vals as $command => $sets) {
						$echo .= '<div style="border: 1px solid #000; width: calc(50% - 12px); font-size: 16pt; '
						. 'background: linear-gradient(rgba(200, 200, 255, 1), rgba(200, 200, 255, 0) 10px); '
						. 'background-repeat: none; margin: 0px 5px 10px; padding: 6px 0px 0px; '
						. 'display: inline-block; text-align: center; float: left; border-radius: 3px;">'
						. $sets[0]["name"]
						. ($USER->data["admin"] == 1 ? '<a href="index.php?action=user&subac=editPermissions&id='
						. $id . '&addSet=' . $command . '" class="right"><img src="imgs/add_small.png" alt="Add" '
						. 'style="margin-right: 10px; width: 16pt;" border="0" /></a>' : '')
						. '<div style="border-top: 1px solid #000; font-size: 12pt; margin-top: 6px;">';
						
						// Output the Sets:
						foreach ($sets as $set) {
							// Convert both times into nice times:
							$from_time = $set["from_time"] / 60;
							$from_time = sprintf("%02d", floor($from_time))
							. ':' . sprintf("%02d", ($from_time - floor($from_time)) * 60);
							$to_time = $set["to_time"] / 60;
							$to_time = sprintf("%02d", floor($to_time))
							. ':' . sprintf("%02d", ($to_time - floor($to_time)) * 60);
							
							
							// Output Set:
							$echo .= '<div style="margin: 5px; background-color: #D0D0FF; padding: 6px; '
							. 'text-align: center; border-radius: 3px; position: relative;">'
							. 'Bevorzugt: <input type="radio" value="1" name="prefered[' . $set["pid"] . ']"'
							. ($set["prefered"] == 1 ? ' checked="checked"' : '') . ' /> Ja'
							.  ' | <input type="radio" value="0" name="prefered[' . $set["pid"] . ']"'
							. ($set["prefered"] == 0 ? ' checked="checked"' : '') . ' /> Nein';
							
							if ($USER->data["admin"] == 1) {
								$echo .= '<div style="display: inline-block;">'
								. self::daySelector("days[" . $set["pid"] . "]", explode(",", $set["days"])) . '</div><br />'
								. 'Von <input type="text" value="' . $from_time . '" name="from_time['
								. $set["pid"] . ']" size="6" />'
								. ' bis <input type="text" value="' . $to_time . '" name="to_time['
								. $set["pid"] . ']" size="6" />'
								. '<a href="#" onclick="delSet(' . $id . ', ' . $set["pid"]
								. ');" class="right" style="position: absolute; bottom: 6px; right: 12px;">'
								. '<img src="imgs/delete_small.png" width="16" height="16" alt="Del" /></a>';
							}
							else {
								$nums = array(',', '1', '2', '3', '4', '5', '6', '7');
								$tage = array(', ', '<strong>Mo</strong>', '<strong>Di</strong>',
								'<strong>Mi</strong>', '<strong>Do</strong>', '<strong>Fr</strong>',
								'<strong>Sa</strong>', '<strong>So</strong>');
								$echo .= '<br />Tage: ' . str_replace($nums, $tage, $set["days"])
								. '<br />Von <strong>' . $from_time . '</strong> bis <strong>' . $to_time . '</strong>';
							}
							
							$echo .= '</div>';
						}
						
						$echo .= '</div></div>';
					}
					
					$echo .= '<div class="clear"></div><input type="submit" value="Speichern" class="right" /></form>';
					
					break;
				
				
				default:
					// Benutzer wird geloescht:
					if (isset($_POST["delete"]) && $_POST["delete"] == 1) {
						mysqli_query($CONNECTION, "DELETE FROM pb_users WHERE id = " . $id . " LIMIT 1");
						$heading = 'Basiseinstellungen';
						$echo = '<p style="color: red;">Benutzer wurde gel&ouml;scht</p>';
						break;
					}
					
					// Data has been sent
					if (isset($_GET["sent"], $_POST["active"], $_POST["username"],
					$_POST["iface"], $_POST["deactivate_alarm"]) && !empty($_POST["username"])) {
						
						$d["active"] = $_POST["active"] == 1 ? 1 : 0;
						$d["username"] = $_POST["username"];
						$d["iface"] = $_POST["iface"] == 1 ? 1 : 0;
						$d["deactivate_alarm"] = $_POST["deactivate_alarm"] == 1 ? 1 : 0;
						
						// If admin, there are even a lot more opportunities!
						if ($USER->data["admin"] == 1 && isset($_POST["notify"],
						$_POST["admin"], $_POST["alarm_clock"], $_POST["log"],
						$_POST["per_count"], $_POST["per_quantif"]) && is_numeric($_POST["per_count"])
						&& is_numeric($_POST["per_quantif"]) && $_POST["per_quantif"] >= 0
						&& $_POST["per_quantif"] <= 4) {
							$d["notify"] = $_POST["notify"] == 1 ? 1 : 0;
							$d["admin"] = $_POST["admin"] == 1 ? 1 : 0;
							$d["alarm_clock"] = $_POST["alarm_clock"] == 1 ? 1 : 0;
							$d["log"] = $_POST["log"] == 1 ? 1 : 0;
							$d["per_count"] = $_POST["per_count"];
							$d["per_quantif"] = $_POST["per_quantif"];
						}
						
						// If the password has to be changed:
						if (isset($_POST["pwd_helper"])) {
							// Set pwd to empty
							if (!isset($_POST["pwd"]) || empty($_POST["pwd"]))
								$d["pwd"] = '';
							// Set new pwd:
							else {
								$d["salt"] = rand(10000, 99999);
								$d["pwd"] = md5($_POST["pwd"] . $d["salt"]);
							}
						}
						
						// Generate the query:
						$queryInsert = '';
						foreach ($d as $key => $val) {
							$queryInsert .= (empty($queryInsert) ? '' : ', ')
							. $key . ' = ' . (is_integer($val) ? $val : '"' . $val . '"');
						}
						
						// Run the query:
						if (mysqli_query($CONNECTION, 'UPDATE pb_users SET ' . $queryInsert
						. ' WHERE id = ' . $id . ' LIMIT 1'))
							$notice = '<p style="color: #008000;"><strong>Meldung:</strong> '
							. 'Daten erfolgreich gespeichert!</p>';
						else
							$notice = '<p style="color: #800000;"><strong>Fehler:</strong> '
							. 'Es gab einen Speicherfehler!</p>';
						
					}
					
					// Get all Information:
					$ben = new user($id);
					
					// Show settings only for admins:
					if ($USER->data["admin"] == 1) {
						$adminsettings = '</tr><tr><td>Benachrichtigen: </td>'
						. '<td><input type="radio" name="notify" value="1"'
						. ($ben->data["notify"] == 1 ? ' checked="checked"' : '') . ' /> Ja | '
						. '<input type="radio" name="notify" value="0"'
						. ($ben->data["notify"] == 0 ? ' checked="checked"' : '') . ' /> Nein</td>'
						. '</tr><tr><td>Admin: </td>'
						. '<td><input type="radio" name="admin" value="1"'
						. ($ben->data["admin"] == 1 ? ' checked="checked"' : '') . ' /> Ja | '
						. '<input type="radio" name="admin" value="0"'
						. ($ben->data["admin"] == 0 ? ' checked="checked"' : '') . ' /> Nein</td>'
						. '</tr><tr><td>Wecker: </td>'
						. '<td><input type="radio" name="alarm_clock" value="1"'
						. ($ben->data["alarm_clock"] == 1 ? ' checked="checked"' : '') . ' /> Ja | '
						. '<input type="radio" name="alarm_clock" value="0"'
						. ($ben->data["alarm_clock"] == 0 ? ' checked="checked"' : '') . ' /> Nein</td>'
						. '</tr><tr><td>Logzugriff: </td>'
						. '<td><input type="radio" name="log" value="1"'
						. ($ben->data["log"] == 1 ? ' checked="checked"' : '') . ' /> Ja | '
						. '<input type="radio" name="log" value="0"'
						. ($ben->data["log"] == 0 ? ' checked="checked"' : '') . ' /> Nein</td>'
						. '</tr><tr><td>Zugriff beschr&auml;nken: </td>'
						. '<td><input type="number" name="per_count" value="'
						. $ben->data["per_count"] . '" style="width: 50px;" /><select name="per_quantif">'
						. '<option value="0"' . ($ben->data["per_quantif"] == 0
						? ' selected=selected' : '') . '>Aus</option>'
						. '<option value="1"' . ($ben->data["per_quantif"] == 1
						? ' selected=selected' : '') . '>Pro Stunde</option>'
						. '<option value="2"' . ($ben->data["per_quantif"] == 2
						? ' selected=selected' : '') . '>Pro Tag</option>'
						. '<option value="3"' . ($ben->data["per_quantif"] == 3
						? ' selected=selected' : '') . '>Pro Woche</option>'
						. '<option value="4"' . ($ben->data["per_quantif"] == 4
						? ' selected=selected' : '') . '>Pro Monat</option>'
						. '</select></td>';
					}
					else
						$adminsettings = '';
					
					// EditBasics turn:
					$SCRIPTS[] = 'editBasic.js';
					$heading = 'Basiseinstellungen';
					$echo = (isset($notice) ? $notice : '')
					. '<form action="index.php?action=user&id=' . $id
					. '&subac=editBasic&sent=1" method="post">'
					. '<table border="0"><tr>'
					. '<td style="width: 250px;">Aktiviert: </td>'
					. '<td><input type="radio" name="active" value="1"'
					. ($ben->data["active"] == 1 ? ' checked="checked"' : '') . ' /> An | '
					. '<input type="radio" name="active" value="0"'
					. ($ben->data["active"] == 0 ? ' checked="checked"' : '') . ' /> Aus</td>'
					. '</tr><tr><td>Benutzername: </td>'
					. '<td><input type="text" name="username" value="' . $ben->data["username"] . '" /></td>'
					. '</tr><tr><td>Passwort: </td>'
					. '<td><input type="password" name="pwd" disabled="disabled" />'
					. '<input type="checkbox" name="pwd_helper" onclick="togglePwd();" /> <span style="color: #'
					. (empty($ben->data["pwd"]) ? '800000;">Nicht ' : '008000;">') . 'gesetzt</span></td>'
					. '</tr><tr><td>Interface: </td>'
					. '<td><input type="radio" name="iface" value="1"'
					. ($ben->data["iface"] == 1 ? ' checked="checked"' : '') . ' /> Ja | '
					. '<input type="radio" name="iface" value="0"'
					. ($ben->data["iface"] == 0 ? ' checked="checked"' : '') . ' /> Nein</td>'
					. '</tr><tr><td>Alarm Aktivit&auml;t umschalten: </td>'
					. '<td><input type="radio" name="deactivate_alarm" value="1"'
					. ($ben->data["deactivate_alarm"] == 1 ? ' checked="checked"' : '') . ' /> Ja | '
					. '<input type="radio" name="deactivate_alarm" value="0"'
					. ($ben->data["deactivate_alarm"] == 0 ? ' checked="checked"' : '') . ' /> Nein</td>'
					. $adminsettings
					. '</tr><tr><td style="color: #800000;">Benutzer l&ouml;schen: </td>'
					. '<td><input type="checkbox" value="1" name="delete" onclick="sureDelete();" /></td>'
					. '</tr></table><br /><input type="submit" value="Speichern" /></form>';
					break;
			}
			
			
			
			return '<h2>' . $heading . ' #' . $id . '</h2><div class="right">'
			. ($_GET["subac"] == "log" ? ''
			: ' <a href="index.php?action=user&subac=log&id=' . $id . '">'
			. '<img src="imgs/log_small.png" alt="Log" border="0" /></a>')
			. ($_GET["subac"] == "editBasic" ? ''
			: ' <a href="index.php?action=user&subac=editBasic&id=' . $id . '">'
			. '<img src="imgs/settings_small.png" alt="Log" border="0" /></a>')
			. ($_GET["subac"] == "editPermissions" ? ''
			: ' <a href="index.php?action=user&subac=editPermissions&id=' . $id . '">'
			. '<img src="imgs/perm_small.png" alt="Berechtigungen" border="0" /></a>')
			. ($_GET["subac"] == "editCard" ? ''
			: ' <a href="index.php?action=user&subac=editCard&id=' . $id . '">'
			. '<img src="imgs/card_small.png" alt="Karte" border="0" /></a>')
			. '</div>'
			. ($USER->data["admin"] == 1 ? '<a href="index.php?action=user">&larr; Zur&uuml;ck '
			. 'zur &Uuml;bersicht</a>' : '')
			. '<div class="clear"></div>' . $echo;
			
		}
		
		if (isset($_GET["addUser"])) {
			// Add a new User:
			mysqli_query($CONNECTION, 'INSERT INTO pb_users (username, card_id, pwd, salt, notify, admin, active, '
			. 'alarm_clock, deactivate_alarm, iface, log, per_quantif, per_count) VALUES '
			. '("Neu", "0", "", 0, 0, 0, 0, 0, 0, 0, 0, 0, 0)');
		}
		
		// Collect all user Information:
		$output = '';
		$bgColor = '#EEEEEE';
		$sql = mysqli_query($CONNECTION, 'SELECT id, username, admin, active, iface FROM pb_users');
		while ($row = mysqli_fetch_array($sql, MYSQLI_ASSOC)) {
			// Select the right background-color:
			if ($row["admin"] == 1) $bgColor = '#FFAAAA';
			elseif ($bgColor != '#EEEEEE') $bgColor = '#EEEEEE';
			else $bgColor = '#DDDDFF';
			
			// Append to the output
			$output .= '<tr style="background-color: ' . $bgColor
			. ($row["id"] == $USER->data["id"] ? '; font-weight: bold' : '')
			. ($row["active"] == 0 ? '; color: #888888' : '') . ';">'
			. '<td style="text-align: center;">#' . $row["id"] . '</td>'
			. '<td style="padding: 0px 10px;">' . $row["username"] . '</td>'
			. '<td style="text-align: center;"><a href="index.php?action=user&subac=log&id=' . $row["id"] . '">'
			. '<img src="imgs/log_small.png" alt="Log" border="0" /></a>'
			. ' <a href="index.php?action=user&subac=editBasic&id=' . $row["id"] . '">'
			. '<img src="imgs/settings_small.png" alt="Einstellungen" border="0" /></a>'
			. ' <a href="index.php?action=user&subac=editPermissions&id=' . $row["id"] . '">'
			. '<img src="imgs/perm_small.png" alt="Berechtigungen" border="0" /></a>'
			. ' <a href="index.php?action=user&subac=editCard&id=' . $row["id"] . '">'
			. '<img src="imgs/card_small.png" alt="Karte" border="0" /></a></td></tr>';
		}
		
		// Output the List:
		return '<h2>Benutzer&uuml;bersicht</h2>'
		. '<a href="index.php?action=user&addUser=1">Neuer Benutzer</a>'
		. '<table border="1" style="border-collapse: collapse; width: 100%;"><tr><th>ID</th>'
		. '<th>Benutzername</th><th style="width: 150px;">Verwalten</th></tr>'
		. $output . '</table>';
		
	}
	
	
	// To set the Alarm-Setting
	static public function alarm() {
		global $USER, $SYSTEM, $CONNECTION;
		
		if ($USER->data["alarm_clock"] == 0 && $USER->data["admin"] != 1)
			return self::noRights();
		$systemmatch = false;
		
		// If Data has been sent
		if (isset($_GET["sent"])) {
			if (!isset($_POST["alarm_active"], $_POST["alarm_time"], $_POST["alarm_commands"])) {
				// Not all Data sent:
				$systemmatch = true;
				$error = 'Es wurden nicht alle ben&ouml;tigten Daten gesendet!';
			}
			
			// Check Syntax for alarm_time
			$alarm_time_arr = explode(":", $_POST["alarm_time"]);
			if (!$systemmatch && (count($alarm_time_arr) != 2 || !is_numeric($alarm_time_arr[0])
			|| !is_numeric($alarm_time_arr[1]))) {
				$systemmatch = true;
				$error = 'Die Zeit hat nicht das korrekte Format!';
			}
			
			// Check Syntax for commands
			if (!$systemmatch && !is_numeric($_POST["alarm_commands"])) {
				$systemmatch = true;
				$error = 'Es wurde kein korrekter Befehl angegeben!';
			}
			
			// Scheint alles OK zu sein:
			if (!$systemmatch) {
				$alarm_active = $_POST["alarm_active"];
				$alarm_once_timeout = isset($_POST["alarm_once_timeout"]) && $_POST["alarm_once_timeout"] == "1" ? '1' : '0';
				$alarm_time = ($alarm_time_arr[0] * 60) + $alarm_time_arr[1];
				$alarm_commands = $_POST["alarm_commands"];
				$alarm_days = isset($_POST["alarm_days"]) ? implode(',', $_POST["alarm_days"]) : '';
				
				if (mysqli_query($CONNECTION, 'UPDATE pb_system SET alarm_active = '
				. $alarm_active . ', alarm_once_timeout = ' . $alarm_once_timeout
				. ', alarm_time = ' . $alarm_time . ', alarm_commands = '
				. $alarm_commands . ', alarm_days = "' . $alarm_days . '" WHERE id = 1 LIMIT 1'))
					$ok = 'Die Daten wurden erfolgreich gespeichert!';
				else
					$error = 'Es gab einen Speicherfehler! ' . mysqli_error($CONNECTION);
			}
		}
		else
			$systemmatch = true;
		
		
		// If a file has to be uploaded:
		if (isset($_GET["fileup"])) {
			
			$error = '';
			// Check if there was a transmission error:
			if ($_FILES["wecker"]["error"] != UPLOAD_ERR_OK)
				$error = 'Es gab einen &Uuml;bertragungsfehler! ' . $_FILES["wecker"]["error"];
			
			// Check for filesize:
			if (empty($error) && $_FILES["wecker"]["size"] > 4000000) // 4MB
				$error = 'Datei darf maximal 4 MB gro&szlig; sein!';
			
			// Cant check for mime-type: Too hard :
			if (empty($error) && !move_uploaded_file($_FILES["wecker"]["tmp_name"], "wecker.mp3"))
				$error = 'Datei konnte nicht verschoben werden!';
			
			
			if (empty($error))
				$ok = 'Neuer Weckton eingestellt!';
			
		}
		
		// If data werent sent:
		if ($systemmatch) {
			$alarm_active = $SYSTEM["alarm_active"];
			$alarm_once_timeout = $SYSTEM["alarm_once_timeout"];
			$alarm_time = $SYSTEM["alarm_time"];
			$alarm_commands = $SYSTEM["alarm_commands"];
			$alarm_days = $SYSTEM["alarm_days"];
		}
		
		// Format the alarm_time:
		$alarm_time = $alarm_time / 60;
		$alarm_time = sprintf("%02d", floor($alarm_time)) . ':' . sprintf("%02d", ($alarm_time - floor($alarm_time)) * 60);
		// Get the commands:
		$commands = '';
		$sql = mysqli_query($CONNECTION, 'SELECT id, name FROM pb_commands');
		while ($row = mysqli_fetch_array($sql, MYSQLI_ASSOC)) {
			$commands .= '<option value="' . $row["id"] . '"'
			. ($row["id"] == $alarm_commands ? ' selected="selected"' : '') . '>' . $row["name"] . '</option>';
		}
		
		
		// Output the form
		return '<h2>Weckereinstellungen</h2>'
		. (isset($error) && !empty($error) ? '<p style="color: #800000;"><strong>Fehler:</strong> ' . $error . '</p>' : '')
		. (isset($ok) ? '<p style="color: #008000;"><strong>Meldung:</strong> ' . $ok . '</p>' : '')
		. '<p><strong>Bitte beachten:</strong> Damit der Wecker funktionieren kann, muss Folgendes beachtet werden:</p><ul>'
		. '<li>Der 12V Trafo muss eingeschaltet sein</li><li>Das Soundsystem muss mit dem Command aktiviert werden</li>'
		. '<li>Das Soundsystem muss bereit zum Abspielen sein (Abgesehen von der Stromzufuhr)</li><li>&Auml;nderungen bei '
		. 'den Weckereinstellungen sollten mindestens ' . $SYSTEM["browser_reload"] . ' Minuten zuvor vorgenommen werden</li></ul>'
		. '<form action="index.php?action=alarm&sent=1" method="post"><table border="0" style="width: 80%;"><tr>'
		. '<td>Wecker aktivieren: </td>'
		. '<td><input type="radio" name="alarm_active" value="1"' . ($alarm_active == 1
		? ' checked="checked"' : '') . ' /> An | <input type="radio" name="alarm_active" value="0"'
		. ($alarm_active == 0 ? ' checked="checked"' : '') . ' /> Aus</td>'
		. '</tr><tr><td>Wecker einmal aussetzen: </td>'
		. '<td><input type="checkbox" name="alarm_once_timeout" value="1"'
		. ($alarm_once_timeout == 1 ? ' checked="checked"' : '') . ' /></td>'
		. '</tr><tr><td>Weckzeit: </td>'
		. '<td><input type="text" name="alarm_time" value="' . $alarm_time . '" size="6" /> Format: HH:MM</td>'
		. '</tr><tr><td>Wecktage: </td>'
		. '<td>' . self::daySelector("alarm_days", explode(",", $alarm_days)) . '</td>'
		. '</tr><tr><td>Soundsystem Befehl: </td>'
		. '<td><select name="alarm_commands"><option>Bitte w&auml;hlen</option>' . $commands . '</select></td>'
		. '</tr></table><input type="submit" value="Speichern" /></form>'
		. '<h2>Wecksound</h2>'
		. (is_file("wecker.mp3") ? '<audio src="wecker.mp3" controls>Kein HTML5-Support</audio><br /><br />' : '')
		. '<form action="index.php?action=alarm&fileup=1" method="post" enctype="multipart/form-data">'
		. '<input type="file" name="wecker" /><input type="submit" value="Hochladen" />'
		. '<input type="hidden" name="MAX_FILE_SIZE" value="4000000" /></form>';
	}
	
	
	// Output the Welcome-Text:
	static public function main() {
		global $USER, $CONNECTION, $SCRIPTS;
		$SCRIPTS[] = 'main_page.js';
		
		// Get the current actions:
		$actions = $USER->getCurrentActions();
		$actionLinks = '';
		if (!empty($actions)){
			$sql = mysqli_query($CONNECTION, 'SELECT id, name FROM pb_commands WHERE id IN ('
			. implode(',', $actions) . ')');
			while ($row = mysqli_fetch_array($sql, MYSQLI_ASSOC)) {
				$actionLinks .= '&nbsp;<button onclick="triggerCommand(' . $row["id"] . ')">' . $row["name"] . '</button>';
			}
		}
		
		// Output
		return '<h2>Eigenverwaltung</h2>'
		. ($USER->data["admin"] != 1 && $USER->data["per_quantif"] != 0 ? '<p>Du hast dich bereits <strong>'
		. $USER->getCurrentCount() . ' von ' . $USER->data["per_count"]
		. '</strong> Malen in dem festgelegten Zeitraum als Quick-Accesser authorisiert.</p>' : '')
		. $actionLinks . '<br /><br />'
		. self::history(10, $USER->data["id"]);
		
	}
	
	
	// Just logout... No style needed
	static public function logout() {
		$_SESSION = array();
		session_destroy();
		return '<h2>Logout</h2>'
		. '<p>Ihre Sitzung wurde sicher beendet. <a href="index.php">Hier</a> gelangen Sie zum Login zur&uuml;ck!</p>';
	}
	
	
	static private function daySelector($name, $arr_selected) {
		return '<table border="0"><tr><td>Mo</td><td>Di</td><td>Mi</td>'
		. '<td>Do</td><td>Fr</td><td>Sa</td><td>So</td></tr><tr>'
		. '<td><input type="checkbox" name="' . $name . '[]" value="1"'
		. (in_array(1, $arr_selected) ? ' checked="checked"' : '') . ' /></td>'
		. '<td><input type="checkbox" name="' . $name . '[]" value="2"'
		. (in_array(2, $arr_selected) ? ' checked="checked"' : '') . ' /></td>'
		. '<td><input type="checkbox" name="' . $name . '[]" value="3"'
		. (in_array(3, $arr_selected) ? ' checked="checked"' : '') . ' /></td>'
		. '<td><input type="checkbox" name="' . $name . '[]" value="4"'
		. (in_array(4, $arr_selected) ? ' checked="checked"' : '') . ' /></td>'
		. '<td><input type="checkbox" name="' . $name . '[]" value="5"'
		. (in_array(5, $arr_selected) ? ' checked="checked"' : '') . ' /></td>'
		. '<td><input type="checkbox" name="' . $name . '[]" value="6"'
		. (in_array(6, $arr_selected) ? ' checked="checked"' : '') . ' /></td>'
		. '<td><input type="checkbox" name="' . $name . '[]" value="7"'
		. (in_array(7, $arr_selected) ? ' checked="checked"' : '') . ' /></td>'
		. '</tr></table>';
		
	}
	
	// No Rights to call the wanted page:
	static private function noRights() {
		return '<h2>Keine Berechtigung</h2>';
	}
	
	
	static private function history($limiter = 30, $user_overwrite = 0) {
		global $CONNECTION;
		
		// Get all history-logs:
		if ($user_overwrite > 0)
			$request = 'SELECT typ, success, stamp FROM pb_log WHERE user = ' . $user_overwrite;
		else
			$request = 'SELECT username, typ, success, stamp FROM pb_log LEFT JOIN pb_users ON user = pb_users.id';
		
		// Set the Limits:
		if (isset($_GET["logpage"]) && is_numeric($_GET["logpage"]) && $_GET["logpage"] > 0) {
			$lowerlimit = ($limiter * $_GET["logpage"]);
		}
		else {
			$lowerlimit = 0;
		}
		
		// Run the request:
		$sql = mysqli_query($CONNECTION, $request . ' ORDER BY pb_log.id DESC LIMIT '
		. $lowerlimit . ', ' . $limiter);
		
		// Process the request:
		$output = '';
		$dateBefore = 0;
		while ($row = mysqli_fetch_array($sql, MYSQLI_ASSOC)) {
			
			// If a new day is represented:
			if ($dateBefore != date('z', $row["stamp"])) {
				$dateBefore = date('z', $row["stamp"]);
				if ($dateBefore == date('z'))
					$tag = 'Heute';
				elseif ($dateBefore + 1 == date('z'))
					$tag = 'Gestern';
				else
					$tag = formatDate($row["stamp"]);
				
				$output .= '<tr><th colspan="3" style="background-color: #FFFAAA;">'
				. $tag . '</th></tr>';
			}
			
			// Select output and color by type:
			switch ($row["typ"]) {
				case 0:
					$bgcolor = '#000000';
					$fgcolor = '#FFFFFF';
					$msg = 'System hochgefahren';
					break;
				case 1:
					$bgcolor = '#E0E0E0';
					$fgcolor = '#000000';
					$msg = 'System Browser Neustart';
					break;
				case 2:
					$bgcolor = ($row["success"] == 1 ? '#80FF80' : '#FF8080');
					$fgcolor = '#000000';
					$msg = 'Quick-Access Authorisierung';
					break;
				case 3:
					$bgcolor = ($row["success"] == 1 ? '#80FF80' : '#FF8080');
					$fgcolor = '#000000';
					$msg = 'Webinterface Anmeldung';
					break;
				case 4:
					$bgcolor = '#EEEEEE';
					$fgcolor = '#000000';
					$msg = 'Wecker-Alarm';
					break;
				default:
					$bgcolor = '#FF0000';
					$fgcolor = '#FFFFFF';
					$msg = 'Unbekannte Aktion (Error)';
					break;
			}
			
			// The general output:
			$output .= '<tr style="color: ' . $fgcolor . '; background-color: ' . $bgcolor . ';"><td>'
			. date('H:i:s', $row["stamp"]) . '</td><td>' . $msg . '</td>'
			. ($user_overwrite == 0 ? '<td>' . (empty($row["username"]) ? 'SYSTEM-SVC' : $row["username"]) . '</td>' : '')
			. '</tr>';
		}
		
		// Make a count (for the buttons)
		$links = '';
		$count = mysqli_fetch_array(mysqli_query($CONNECTION, 'SELECT COUNT(*) as zaehler FROM pb_log'
		. ($user_overwrite == 0 ? '' : ' WHERE user = ' . $user_overwrite)), MYSQLI_ASSOC);
		// Display the buttons
		for ($i = 0; $i < ceil($count["zaehler"] / $limiter); $i++) {
			$links .= (empty($links) ? '' : ' | ') . '<a href="' . self::addParamLink('logpage', $i) . '"'
			. (isset($_GET["logpage"]) && $_GET["logpage"] == $i || !isset($_GET["logpage"]) && $i == 0
			? ' style="color: #800000;"' : '')
			. '>' . ($i + 1) . '</a>';
		}
		
		// Return the answer:
		return '<table border="1" style="width: 100%; border-collapse: collapse;">'
		. '<tr><th>Zeit</th><th>Anfrage</th>'
		. ($user_overwrite == 0 ? '<th>Benutzer</th>' : '') . '</tr>' . $output
		. '</table>' . $links;
		
	}
	
	
	
	// Add a parameter (Or change it) for a link:
	static private function addParamLink($paramName, $paramVal, $file = 'index.php') {
		
		if (isset($_GET[$paramName])) {
			// Split all Parameters:
			$params = explode('&', $_SERVER["QUERY_STRING"]);
			$params[array_search($paramName . '=' . $_GET[$paramName], $params)] = $paramName . '=' . $paramVal;
			return $file . '?' . implode('&', $params);
		}
		
		// Easy way: Just append it:
		return $file . '?' . (empty($_SERVER["QUERY_STRING"]) ? ''
		: $_SERVER["QUERY_STRING"] . '&') . $paramName . '=' . $paramVal;
		
		
	}
	
}
?>