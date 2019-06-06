<?php
class pages {
	
	// The System-Settings can be changed here:
	static public function system() {
		global $USER, $CONNECTION, $SCRIPTS, $SYSTEM;
		
		if ($USER->data["canManage"] == 0 && $USER->data["canSeeGeneralLog"] == 0) {
			return self::noRights();
		}
		
		// Return the Log
		if ($USER->data["canManage"] == 0 && $USER->data["canSeeGeneralLog"] == 1 || isset($_GET["log"])) {
			if (($USER->data["canManage"] == 1 || $USER->data["canEmptyLog"] == 1)
			&& isset($_GET["delLog"]) && $_GET["delLog"] == 1) {
				mysqli_query($CONNECTION, 'DELETE FROM pb_log WHERE time < ' . (time() - 259200));
			}
			
			return '<h2><a href="index.php?action=system">System</a> &rarr; Log</h2>'
			. ($USER->data["canManage"] == 1
			? '<a href="index.php?action=system">&larr; Zur&uuml;ck</a><br /><br />' : '')
			. self::history(30, 0)
			. ($USER->data["canManage"] == 1 || $USER->data['canEmptyLog'] == 1 ? '<br /><br /><a '
			. 'href="index.php?action=system&log=1&delLog=1">Alles von vor 3 Tagen l&ouml;schen</a>' : '');
		}
		
		// From here on out anyone but the admin has nothing to do here:
		if ($USER->data['canManage'] != '1')
			return self::noRights();
		
		
		
		// Output the Pins-Settings
		if (isset($_GET["pins"])) {
			
			// Insert a new Pin, if requested to do so
			if (isset($_GET['newPin'])) {
				if (mysqli_query($CONNECTION, 'INSERT INTO pb_pins (gpio, description, sort, onPushed, '
				. 'onReleased, switchState, defaultOnStartup, special) VALUES (NULL, "Neuer Pin", "output", '
				. '0, 0, 0, 0, "")') === true)
					$msg = '<p style="color: #008000;"><strong>Meldung:</strong> Neuen Pin erstellt</p>';
				else
					$msg = '<p style="color: #800000;"><strong>Fehler:</strong> Pin konnte nicht '
					. 'erstellt werden - ' . mysqli_error($CONNECTION) . '</p>';
			}
			// Save the results if wanted
			elseif (isset($_GET['sent'])) {
				$gpios = isset($_POST['gpio']) ? $_POST['gpio'] : array();
				$loeschen = isset($_POST['loeschen']) ? $_POST['loeschen'] : array();
				
				// Run through every item by the gpios and collect messages:
				$msgs = array();
				foreach ($gpios as $pinId => $gpio) {
					// Soll geloescht werden:
					if (isset($loeschen[$pinId]) && $loeschen[$pinId] == 1) {
						if (mysqli_query($CONNECTION, 'DELETE FROM pb_pins WHERE pinId = '
						. $pinId . ' LIMIT 1') === true)
							$msgs[] = '<p style="color: #008000;"><strong>Meldung:</strong> Pin #'
							. $pinId . ' erfolgreich gel&ouml;scht.</p>';
						else
							$msgs[] = '<p style="color: #800000;"><strong>Fehler:</strong> Konnte '
							. 'Pin nicht l&ouml;schen: ' . mysqli_error($CONNECTION) . '</p>';
						continue;
					}
					
					// Get the corresponding data (regardless of sort)
					if (!isset($_POST['description'][$pinId], $_POST['sort'][$pinId],
					$_POST['defaultOnStartup'][$pinId])) {
						$msgs[] = '<p style="color: #800000;"><strong>Fehler:</strong> Datensatz '
						. 'nicht vollst&auml;ndig &uuml;bertragen.</p>';
						continue;
					}
					$description = $_POST['description'][$pinId];
					$sort = $_POST['sort'][$pinId];
					$defaultOnStartup = $_POST['defaultOnStartup'][$pinId];
					$spezial = array();
					
					// In case it is not an output:
					if ($sort == 'inButton' || $sort == 'inVirtualLatch' || $sort == 'inSwitch') {
						$onPushed = isset($_POST['onPushed'][$pinId])
							&& is_numeric($_POST['onPushed'][$pinId])
							&& $_POST['onPushed'][$pinId] >= 0 ? $_POST['onPushed'][$pinId] : 0;
						$onReleased = isset($_POST['onReleased'][$pinId])
							&& is_numeric($_POST['onReleased'][$pinId])
							&& $_POST['onReleased'][$pinId] >= 0 ? $_POST['onReleased'][$pinId] : 0;
						if (isset($_POST['spezialWecker'][$pinId]) && $_POST['spezialWecker'][$pinId] == 1)
							$spezial[] = 'alarmIn';
						if (isset($_POST['spezialLogout'][$pinId]) && $_POST['spezialLogout'][$pinId] == 1)
							$spezial[] = 'logoutIn';
					}
					elseif ($sort == 'output') {
						$onPushed = 0;
						$onReleased = 0;
						if (isset($_POST['spezialWebOut'][$pinId]) && $_POST['spezialWebOut'][$pinId] == 1)
							$spezial[] = 'loginWebOut';
					}
					else {
						$msgs[] = '<p style="color: #800000;"><strong>Fehler:</strong> Invalid sort</p>';
						continue;
					}
					
					// Save with this query:
					$sql = mysqli_query($CONNECTION, "UPDATE pb_pins SET gpio = $gpio, "
					. "description = \"$description\", sort = \"$sort\", defaultOnStartup = $defaultOnStartup, "
					. "special = \"" . implode(',', $spezial) . "\", onPushed = $onPushed, "
					. "onReleased = $onReleased WHERE pinId = $pinId LIMIT 1");
					if ($sql !== true)
						$msgs[] = '<p style="color: #800000;"><strong>Fehler:</strong> Konnte nicht '
						. 'Pin #' . $pinId . ' speichern: ' . mysqli_error($CONNECTION);
				}
				
				// Parse the Messages:
				if (empty($msgs))
					$msg = '<p style="color: #008000;"><strong>Meldung:</strong> Erfolgreich gespeichert</p>';
				else
					$msg = implode('', $msgs);
			}
			
			// Get the commands:
			$sql = mysqli_query($CONNECTION, 'SELECT commandId, description FROM pb_commands');
			$commands = array();
			while ($row = mysqli_fetch_array($sql, MYSQLI_ASSOC))
				$commands[$row['commandId']] = $row['description'];
			// Get the Pins
			$sql = mysqli_query($CONNECTION, 'SELECT * FROM pb_pins');
			$output = '';
			while ($row = mysqli_fetch_array($sql, MYSQLI_ASSOC)) {
				$specials = explode(',', $row['special']);
				$output .= '<div style="margin: 10px; padding: 5px; background-color: #EEEEEE; '
				. 'border-radius: 5px;"><table border="0" style="width: 100%;"><tr>'
				. '<td rowspan="7" style="border-right: 3px solid black; '
				. 'vertical-align: center; font-weight: bold; text-align: center">#' . $row['pinId']
				. '<br>Auf GPIO <input type="number" style="width: 30pt;" required value="'
				. $row['gpio'] . '" name="gpio[' . $row['pinId'] . ']"></td>'
				. '<td>Beschreibung: </td>'
				. '<td><input type="text" name="description[' . $row['pinId']
				. ']" value="' . $row['description'] . '" required></td>'
				. '</tr><tr>'
				. '<td>Pintyp: </td>'
				. '<td><select name="sort[' . $row['pinId'] . ']"><optgroup label="Inputs">'
				. '<option value="inButton"' . ($row['sort'] == 'inButton' ? 'selected' : '')
				. '>Taster (Tastfunktion)</option>'
				. '<option value="inVirtualLatch"' . ($row['sort'] == 'inVirtualLatch' ? 'selected' : '')
				. '>Taster (Schaltfunktion)</option>'
				. '<option value="inSwitch"' . ($row['sort'] == 'inSwitch' ? 'selected' : '')
				. '>Schalter</option>'
				. '</optgroup><optgroup label="Outputs">'
				. '<option value="output"' . ($row['sort'] == 'output' ? 'selected' : '')
				. '>Ausgang allgemein</option>'
				. '</optgroup></select></td></tr>';
				if ($row['sort'] != 'output') {
					$output .= '<tr><td>Befehl beim Dr&uuml;cken: </td>'
					. '<td><select name="onPushed[' . $row['pinId'] . ']">'
					. '<option value="0">-- Nichts machen --</option>';
					foreach ($commands as $id => $desc)
						$output .= '<option value="' . $id . '"' .
						($id == $row['onPushed'] ? ' selected' : '') . '>' . $desc . '</option>';
					$output .= '</select></td></tr><tr><td>Befehl beim Loslassen: </td>'
					. '<td><select name="onReleased[' . $row['pinId'] . ']">'
					. '<option value="0">-- Nichts machen --</option>';
					foreach ($commands as $id => $desc)
						$output .= '<option value="' . $id . '"' .
						($id == $row['onReleased'] ? ' selected' : '') . '>' . $desc . '</option>';
					$output .= '</select></td></tr><tr><td>Spezial: </td>'
					. '<td>Schaltet Wecker: '
					. '<input type="checkbox" name="spezialWecker[' . $row['pinId']
					. ']" value="1"' . (in_array('alarmIn', $specials) ? ' checked' : '') . '><br>'
					. 'Logout Taster: '
					. '<input type="checkbox" name="spezialLogout[' . $row['pinId']
					. ']" value="1"' . (in_array('logoutIn', $specials) ? ' checked' : '') . '>'
					. '</td></tr>';
				}
				else {
					$output .= '<tr><td>Spezial: </td><td>An bei Weblogin: '
					. '<input type="checkbox" name="spezialWebOut[' . $row['pinId'] . ']" '
					. 'value="1"' . (in_array('loginWebOut', $specials) ? ' checked' : '') . '>'
					. '</td></tr>';
				}
				$output .= '<tr><td>Wert nach Hochfahren: </td>'
				. '<td><input type="radio" name="defaultOnStartup[' . $row['pinId'] . ']" value="0"'
				. ($row['defaultOnStartup'] == 0 ? ' checked' : '') . '> Aus |'
				. '<input type="radio" name="defaultOnStartup[' . $row['pinId'] . ']" value="1"'
				. ($row['defaultOnStartup'] == 1 ? ' checked' : '') . '> An'
				. '</td></tr><tr><td>'
				. '<span style="color: #800000;">L&ouml;schen</span></td><td>'
				. '<input type="checkbox" name="loeschen[' . $row['pinId'] . ']" value="1" onclick="'
				. 'if ($(this).is(\':checked\')) {if (confirm(\'Sicher, dass Sie den Pin entfernen wollen?\')) '
				. '{ $(this).prop(\'checked\', true) } else {$(this).prop(\'checked\', false)}}">'
				. '</td></tr></table></div>';
			}
			
			// Output the Pins
			return '<h2>Pins &larr; '
			. '<a href="index.php?action=system&commands=1">Befehle</a> &larr; '
			. '<a href="index.php?action=system">System</a></h2>'
			. '<a href="index.php?action=system&commands=1" class="right">Commands verwalten &rarr;</a>'
			. '<div class="clear"></div>'
			. (isset($msg) ? $msg : '')
			. '&Auml;nderungen an diesen Einstellungen erfordern den Neustart des Controllers.'
			. '<form action="index.php?action=system&pins=1&sent=1" method="post">'
			. '<a href="index.php?action=system&pins=1&newPin=1">Neuen Pin erstellen</a>'
			. '<input type="submit" value="Speichern" class="right">'
			. '<div class="clear"></div>'
			. $output
			. '<div class="clear"></div>'
			. '<input type="submit" value="Speichern" class="right">'
			. '</form>';
		}
		
		
		
		// Output the Commandsinterface (commands only)
		if (isset($_GET["commands"])) {
			// If a new command has to be registered:
			if (isset($_GET['newCommand'])) {
				if (mysqli_query($CONNECTION, 'INSERT INTO pb_commands (description, userFunc, '
				. 'allowedForGuest) VALUES ("Neuer Befehl", NULL, 0)') === true)
					$msg = '<p style="color: #008000;"><strong>Meldung:</strong> Befehl '
					. 'erfolgreich erstellt</p>';
				else
					$msg = '<p style="color: #800000;"><strong>Fehler:</strong> Konnte neuen '
					. 'Befehl nicht erstellen: ' . mysqli_error($CONNECTION);
			}
			
			// If the data have been sent
			elseif (isset($_GET['sent'])) {
				// Get the descriptions:
				$descriptions = isset($_POST['description']) ? $_POST['description'] : array();
				
				$msgs = array();
				foreach ($descriptions as $commandId => $description) {
					// Gather information
					if (!isset($_POST['userFunc'][$commandId], $_POST['comLoeschen'][$commandId])) {
						$msgs[] = '<p style="color: #800000;"><strong>Fehler:</strong> Konnte nicht '
						. 'alle Informationen abrufen.</p>';
						continue;
					}
					$userFunc = $_POST['userFunc'][$commandId];
					$allowedForGuest = isset($_POST['allowedForGuest'][$commandId]) ? 1 : 0;
					$comLoeschen = $_POST['comLoeschen'][$commandId];
					$runOnLogin = isset($_POST['runOnLogin'][$commandId]) ? 1 : 0;
					$runOnLogout = isset($_POST['runOnLogout'][$commandId]) ? 1 : 0;
					
					// Delete the command:
					if ($comLoeschen == 1) {
						mysqli_query($CONNECTION, 'DELETE FROM pb_commands WHERE commandId = '
							. $commandId . ' LIMIT 1');
						mysqli_query($CONNECTION, 'UPDATE pb_alarms SET command = 0 WHERE command = '
							. $commandId);
						mysqli_query($CONNECTION, 'UPDATE pb_pins SET onPushed = 0 WHERE onPushed = '
							. $commandId);
						mysqli_query($CONNECTION, 'UPDATE pb_pins SET onPushed = 0 WHERE onPushed = '
							. $commandId);
						mysqli_query($CONNECTION, 'DELETE FROM pb_timings WHERE commandId = ' . $commandId);
						$commandsOnLogin = explode(',', $SYSTEM['commandsOnLogin']);
						$commandsOnLogout = explode(',', $SYSTEM['commandsOnLogout']);
						if (in_array($commandId, $commandsOnLogin))
							systemRewrite('commandsOnLogin', implode(',', array_filter($commandsOnLogin,
								function($val) use ($commandId) {
									return ($commandId != $val);
							})));
						if (in_array($commandId, $commandsOnLogout))
							systemRewrite('commandsOnLogout', implode(',', array_filter($commandsOnLogout,
								function($val) use ($commandId) {
									return ($commandId != $val);
							})));
						if (empty(mysqli_error($CONNECTION)))
							$msgs[] = '<p style="color: #008000;"><strong>Meldung:</strong> '
							. 'Befehl "' . $description . '" erfolgreich gel&ouml;scht.</p>';
						else
							$msgs[] = '<p style="color: #800000;"><strong>Fehler:</strong> '
							. 'Konnte nicht gel&ouml;scht werden: ' . mysqli_error($CONNECTION);
						continue;
					}
					
					include_once 'userFuncs.php';
					// Parse the input:
					if (strlen($description) < 3 || ($allowedForGuest != 1 && $allowedForGuest != 0)
					|| (!empty($userFunc) && !method_exists('userFuncs', $userFunc))) {
						$msgs[] = '<p style="color: #800000;"><strong>Fehler:</strong> Es gab einen '
						. 'Eingabefehler bei Befehl "' . $description . '".</p>';
						continue;
					}
					
					// Update the Command
					if (mysqli_query($CONNECTION, 'UPDATE pb_commands SET description = "'
					. $description . '", allowedForGuest = ' . $allowedForGuest
					. ', userFunc = "' . $userFunc . '" WHERE commandId = ' . $commandId
					. ' LIMIT 1') !== true)
						$msgs[] = '<p style="color: #800000;"><strong>Fehler:</strong> Befehl konnte '
						. 'nicht gespeichert werden: ' . mysqli_error($CONNECTION) . '</p>';
					// Update the Run On Login or Logout
					$commandsOnLogin = array_filter(explode(',', $SYSTEM['commandsOnLogin']));
					if (!in_array($commandId, $commandsOnLogin) && $runOnLogin == 1) {
						$commandsOnLogin[] = $commandId;
						systemRewrite('commandsOnLogin', implode(',', $commandsOnLogin));
					}
					elseif (in_array($commandId, $commandsOnLogin) && $runOnLogin == 0) {
						systemRewrite('commandsOnLogin', implode(',', array_filter($commandsOnLogin,
							function($val) use ($commandId) {
								return ($commandId != $val);
						})));
					}
					$commandsOnLogout = array_filter(explode(',', $SYSTEM['commandsOnLogout']));
					if (!in_array($commandId, $commandsOnLogout) && $runOnLogout == 1) {
						$commandsOnLogout[] = $commandId;
						systemRewrite('commandsOnLogout', implode(',', $commandsOnLogout));
					}
					elseif (in_array($commandId, $commandsOnLogout) && $runOnLogout == 0) {
						systemRewrite('commandsOnLogout', implode(',', array_filter($commandsOnLogout,
							function($val) use ($commandId) {
								return ($commandId != $val);
						})));
					}
					
					// If there has a new action to be registered
					if (isset($_POST['newUsePin'][$commandId], $_POST['newUseAs'][$commandId])
					&& is_numeric($_POST['newUsePin'][$commandId])
					&& in_array($_POST['newUseAs'][$commandId], array('on', 'off', 'toggle', 'push'))) {
						if (mysqli_query($CONNECTION, 'INSERT INTO pb_commandActions (commandId, '
						. 'usePin, useAs) VALUES (' . $commandId . ', ' . $_POST['newUsePin'][$commandId]
						. ', "' . $_POST['newUseAs'][$commandId] . '")') !== true)
							$msgs[] = '<p style="color: #800000;"><strong>Fehler:</strong> Konnte '
							. 'neue Aktion nicht erstellen: ' . mysqli_error($CONNECTION);
					}
				}
				
				// Get the Actions and practically do the whole saving part all over again:
				$usePins = isset($_POST['usePin']) ? $_POST['usePin'] : array();
				foreach ($usePins as $actionId => $usePin) {
					if (!isset($_POST['useAs'][$actionId]) || !in_array($_POST['useAs'][$actionId],
					array('on', 'off', 'toggle', 'push')) || !is_numeric($usePin)) {
						$msgs[] = '<p style="color: #800000;"><strong>Fehler:</strong> Daten '
						. 'nicht vollst&auml;ndig &uuml;bertragen</p>';
						continue;
					}
					$useAs = $_POST['useAs'][$actionId];
					$actLoeschen = isset($_POST['actLoeschen'][$actionId])
						&& $_POST['actLoeschen'][$actionId] ? true : false;
					
					// Delete the action:
					if ($actLoeschen) {
						if (mysqli_query($CONNECTION, 'DELETE FROM pb_commandActions '
						. 'WHERE actionId = ' . $actionId . ' LIMIT 1') !== true)
							$msgs[] = '<p style="color: #800000;"><strong>Fehler:</strong> '
							. 'Konnte Aktion nicht l&ouml;schen: ' . mysqli_error($CONNECTION);
						continue;
					}
					
					// Update the action:
					if (mysqli_query($CONNECTION, 'UPDATE pb_commandActions SET usePin = ' . $usePin
					. ', useAs = "' . $useAs . '" WHERE actionId = ' . $actionId . ' LIMIT 1') !== true)
						$msgs[] = '<p style="color: #800000;"><strong>Fehler:</strong> '
						. 'Konnte Aktion nicht speichern: ' . mysqli_error($CONNECTION);
				}
				
				// Parse the messages:
				$msg = empty($msgs) ? '<p style="color: #008000;"><strong>Meldung:</strong> Es '
				. 'wurde erfolgreich gespeichert.' : implode($msgs);
			}
			
			
			// Get all Pins for selection
			$sql = mysqli_query($CONNECTION, 'SELECT pinId, description FROM pb_pins WHERE sort = "output"');
			$pins = array();
			while ($row = mysqli_fetch_array($sql, MYSQLI_ASSOC))
				$pins[] = $row;
			// Get the Data
			$sql = mysqli_query($CONNECTION, 'SELECT actionId, A.commandId, description, userFunc, '
				. 'allowedForGuest, usePin, useAs FROM pb_commands A '
				. 'LEFT JOIN pb_commandActions B ON A.commandId = B.commandId '
				. 'ORDER BY A.commandId');
			
			// Process the data:
			$lastCommandId = 0;
			$output = '';
			while ($row = mysqli_fetch_array($sql, MYSQLI_ASSOC)) {
				// If there is a new CommandBox
				if ($lastCommandId != $row['commandId']) {
					$lastCommandId = $row['commandId'];
					$alternating = true;
					$output .= (empty($output) ? '' : '</div>')
					. '<div style="border-radius: 7px; margin: 10px; width: calc(50% - 20px); '
					. 'overflow: hidden; float: left; border: none; '
					. 'background-color: #E49C24; padding-bottom: 7px;">'
					. '<div style="font-weight: bold; color: #FFFFFF; '
					. 'padding: 5px;">#' . $row['commandId'] . ': '
					. '<input type="text" required value="' . $row['description'] . '" style="'
					. 'background-color: #E49C24; color: #FFFFFF; font-weight: bold; border: none; '
					. 'border-bottom: 1px dotted black;" name="description[' . $row['commandId'] . ']"><br>'
					. '<table border="0" style="font-size: 10pt; font-weight: normal;"><tr>'
					. '<td>Interne Funktion<span style="color: #800000;">*</span>: </td>'
					. '<td><input type="text" name="userFunc[' . $row['commandId']
					. ']" value="' . $row['userFunc'] . '" size="8"></td>'
					. '</tr><tr>'
					. '<td>F&uuml;r G&auml;ste: </td>'
					. '<td><input type="checkbox" name="allowedForGuest[' . $row['commandId']
					. ']" value="1"' . ($row['allowedForGuest'] == 1 ? ' checked': '') . '></td>'
					. '</tr><tr><td>Bei Anmeldung: </td>'
					. '<td><input type="checkbox" value="1" name="runOnLogin[' . $row['commandId'] . ']"'
					. (in_array($row['commandId'], explode(',', $SYSTEM['commandsOnLogin']))
					   ? ' checked' : '') . '></td>'
					. '</tr><tr><td>Bei Abmeldung: </td>'
					. '<td><input type="checkbox" value="1" name="runOnLogout[' . $row['commandId'] . ']"'
					. (in_array($row['commandId'], explode(',', $SYSTEM['commandsOnLogout']))
					   ? ' checked' : '') . '></td>'
					. '</tr><tr><td style="color: #800000;">L&ouml;schen: </td>'
					. '<td><input type="radio" name="comLoeschen[' . $row['commandId'] . ']" '
					. 'value="1" class="comLoeschen"> Ja | '
					. '<input type="radio" name="comLoeschen[' . $row['commandId'] . ']" '
					. 'value="0" class="comLoeschen" checked> Nein</td>'
					. '</tr></table></div>'
					// Here comes the form for the new commandAction
					. '<div style="font-weight: normal; background-color: #9CE424;'
					. 'width: calc(100% - 14px); color: #000000; padding: 5px; '
					. 'border: 2px outset #E49C24;">'
					. '<table border="0"><tr>'
					. '<td style="width: 50%; font-weight: bold;">Neu: </td>'
					. '<td><input type="checkbox" class="checkboxNewAction"></td>'
					. '</tr><tr>'
					. '<td>Pin: '
					. '<td><select name="newUsePin[' . $row['commandId'] . ']" style="'
					. 'background-color: #9CE424; '
					. 'border: 1px solid black; padding: 2px 5px; '
					. 'border-radius: 3px;" disabled>'
					. '<option selected>W&auml;hle Pin</option>';
					foreach ($pins as $pin)
						$output .= '<option value="' . $pin['pinId'] . '">'
						. $pin['description'] . '</option>';
					$output .= '</select></td>'
					. '</tr><tr>'
					. '<td>Aktion: </td>'
					. '<td><select name="newUseAs[' . $row['commandId'] . ']" style="'
					. 'background-color: #9CE424; '
					. 'border: 1px solid black; padding: 2px 5px; '
					. 'border-radius: 3px;" disabled>'
					. '<option value="on">Einschalten</option>'
					. '<option value="off">Ausschalten</option>'
					. '<option value="toggle">Umschalten</option>'
					. '<option value="push">Kurz tasten</option>'
					. '</select></td>'
					. '</tr></table></div>';
				}
				// If there is an Action inside:
				if (!empty($row['actionId'])) {
					$alternating = !$alternating;
					$output .= '<div style="font-weight: normal; background-color: '
					. ($alternating ? '#FFBC4E' : '#FFC769')
					. '; width: calc(100% - 14px); color: #000000; padding: 5px; '
					. 'border: 2px outset #E49C24;"><table border="0"><tr>'
					. '<td style="width: 50%;">Pin: '
					. '<td><select name="usePin[' . $row['actionId'] . ']" style="'
					. 'background-color: '
					. ($alternating ? '#FFBC4E' : '#FFC769')
					. '; border: 1px solid black; padding: 2px 5px; '
					. 'border-radius: 3px;">';
					foreach ($pins as $pin)
						$output .= '<option value="' . $pin['pinId'] . '"'
						. ($pin['pinId'] == $row['usePin'] ? ' selected' : '')
						. '>' . $pin['description'] . '</option>';
					$output .= '</select></td>'
					. '</tr><tr>'
					. '<td>Aktion: </td>'
					. '<td><select name="useAs[' . $row['actionId'] . ']" style="'
					. 'background-color: '
					. ($alternating ? '#FFBC4E' : '#FFC769')
					. '; border: 1px solid black; padding: 2px 5px; '
					. 'border-radius: 3px;">'
					. '<option value="on"' . ($row['useAs'] == 'on' ? ' selected' : '')
					. '>Einschalten</option>'
					. '<option value="off"' . ($row['useAs'] == 'off' ? ' selected' : '')
					. '>Ausschalten</option>'
					. '<option value="toggle"' . ($row['useAs'] == 'toggle' ? ' selected' : '')
					. '>Umschalten</option>'
					. '<option value="push"' . ($row['useAs'] == 'push' ? ' selected' : '')
					. '>Kurz tasten</option>'
					. '</select></td>'
					. '</tr><tr><td style="color: #800000;">L&ouml;schen: </td>'
					. '<td><input type="checkbox" name="actLoeschen[' . $row['actionId']
					. ']" value="1" class="actLoeschen"></td>'
					. '</tr></table></div>';
				}
			}
			
			// Output it all
			$SCRIPTS[] = 'systemCommands.js';
			return '<h2>Befehle &larr; <a href="index.php?action=system">System</a> </h2>'
			. '<a href="index.php?action=system&pins=1">&larr; Pins verwalten</a>'
			. '<a href="index.php?action=system" class="right">Zur&uuml;ck &rarr;</a>'
			. '<div class="clear"></div>'
			. (isset($msg) ? $msg : '')
			. '<form action="index.php?action=system&commands=1&sent=1" method="post">'
			. '<a href="index.php?action=system&commands=1&newCommand=1">Neuen Befehl erstellen</a>'
			. '<input type="submit" value="Speichern" class="right">'
			. '<div class="clear"></div>'
			. (!empty($output) ? $output . '</div>' : '')
			. '<div class="clear"></div><span style="font-size: 8pt;">'
			. '<span style="color: #800000;">*</span>: Muss eine Funktion in /userFuncs.php sein.</span>'
			. '<input type="submit" value="Speichern" class="right"></form>';
		}
		
		
		
		// Evaluate the Sent Data:
		if (isset($_GET["sent"])) {
			$query = array();
			$textData = array('name');
			$numData = array('sessionTimeout');
			$boolData = array('deadMode', 'controllerLocalOnly');
			
			// Process the Post:
			foreach ($_POST as $key => $val) {
				
				// If not wanted:
				if (in_array($key, $textData))
					$val = '"' . $val . '"';
				elseif (in_array($key, $boolData))
					$val = $val == "1" ? 1 : 0;
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
		$sys = mysqli_fetch_array(mysqli_query($CONNECTION, 'SELECT * FROM '
		. 'pb_system WHERE id = 1 LIMIT 1'), MYSQLI_ASSOC);
		
		
		return '<h2>System</h2>'
		. '<a href="index.php?action=system&commands=1">&larr; Befehle verwalten</a>'
		. '<a href="index.php?action=system&log=1" class="right">Ereignisprotokoll &rarr;</a>'
		. '<div class="clear"></div>'
		. (isset($msg) ? $msg : '')
		. '<form action="index.php?action=system&sent=1" method="post">'
		. '<table border="0" style="width: 80%;"><tr>'
		. '<td>Systemname: </td>'
		. '<td><input type="text" name="name" value="' . $sys['name'] . '"></td>'
		. '</tr><tr><td>Commands ausf&uuml;hren: </td>'
		. '<td><input type="radio" name="deadMode" value="0"'
		. ($sys["deadMode"] == 0 ? ' checked="checked"' : '') . ' /> Ja | '
		. '<input type="radio" name="deadMode" value="1"'
		. ($sys["deadMode"] == 1 ? ' checked="checked"' : '') . ' /> Nein</td>'
		. '</tr><tr><td>Geschlossene "controller.php": </td>'
		. '<td><input type="radio" name="controllerLocalOnly" value="1"'
		. ($sys["controllerLocalOnly"] == 1 ? ' checked="checked"' : '') . ' /> Ja | '
		. '<input type="radio" name="controllerLocalOnly" value="0"'
		. ($sys["controllerLocalOnly"] == 0 ? ' checked="checked"' : '') . ' /> Nein</td>'
		. '</tr><tr><td>Sitzungshaltbarkeit: </td>'
		. '<td><input type="number" value="' . $sys["sessionTimeout"]
		. '" name="sessionTimeout" style="width: 50px;" /> In Minuten</td>'
		. '</tr></table><input type="submit" value="Speichern" /></form>';
	}
	
	
	static public function user() {
		global $USER, $SYSTEM, $CONNECTION, $SCRIPTS;
		
		// If a user has to be changed:
		if ($USER->data["canManage"] != 1 || isset($_GET["subac"])) {
			
			// Replace the subac with a value:
			if (!isset($_GET["subac"]))
				$_GET["subac"] = "editBasic";
			
			// Get the wanted ID:
			if (!isset($_GET["id"]) || $USER->data["canManage"] != 1)
				$id = $USER->data["userId"];
			else
				$id = is_numeric($_GET["id"]) ? $_GET["id"] : $USER->data["userId"];
			
			
			
			
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
					. (empty($ben->data["serial"]) ? '<span style="color: #800000;">Keine Karte registriert</span>'
					: '#' . $ben->data["serial"]) . '</span>';
					break;
				
				case 'log':
					$heading = 'Benutzerprotokoll';
					$echo = self::history(30, $id);
					break;
				
				
				
				case 'editPermissions':
					
					$heading = 'Zeitplan';
					$echo = '';
					$ben = new user($id);
					
					// If Data has been sent:
					if (isset($_GET["sent"])) {
						
						// Process the $_POSTs
						$adminlist = array('days', 'timeslotBegin', 'timeslotEnd');
						$d = array();
						foreach ($_POST as $type => $valArr) {
							
							// if type not known, just jump:
							if ($type != 'runByDefault'
							&& !(($USER->data['canManage'] == 1 || $USER->data['canSetTimings'] == 1)
							&& in_array($type, $adminlist))) 
								continue;
							
							// Process the different vals for the keys
							foreach ($valArr as $key => $answer) {
								
								// Convert the answer, if necces:
								if ($type == "timeslotBegin" || $type == "timeslotEnd") {
									$explode = explode(":", $answer);
									if (count($explode) == 2 && is_numeric($explode[0])
									&& is_numeric($explode[1]))
										$answer = ($explode[0] * 60) + $explode[1];
									else
										$answer = 0;
									if ($answer < 0) $answer = 0;
									if ($answer > 1440) $answer = 1440;
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
							if (!mysqli_query($CONNECTION, 'UPDATE pb_timings SET ' . implode(", ", $val)
							. ' WHERE timingId = ' . $key . ' LIMIT 1'))
								$msg .= '<p style="color: #800000;"><strong>Fehler:</strong> '
								. mysqli_error($CONNECTION) . '</p>';
						}
						$msg .= '<p style="color: #008000;"><strong>Meldung:</strong> ' . $i . ' Sets wurden bearbeitet!';
						
					}
					
					// Add a new Permissionset:
					if (isset($_GET["addSet"]) && is_numeric($_GET["addSet"])
					&& $USER->data["canManage"] == 1) {
						mysqli_query($CONNECTION, 'INSERT INTO pb_timings (userId, commandId, days, '
							. 'timeslotBegin, timeslotEnd, runByDefault) VALUES (' . $id . ', '
							. $_GET['addSet'] . ', "", 0, 1440, 0)');
					}
					// Delete a Permissionset:
					if (isset($_GET["delSet"]) && is_numeric($_GET["delSet"])
					&& ($USER->data["canManage"] == 1 || $USER->data['canSetTimings'] == 1)) {
						mysqli_query($CONNECTION, 'DELETE FROM pb_timings WHERE timingId = ' . $_GET["delSet"] . ' LIMIT 1');
					}
					
					// Select the permissions:
					$sql = mysqli_query($CONNECTION, 'SELECT timingId, A.commandId, description, days, '
						. 'timeslotBegin, timeslotEnd, runByDefault FROM pb_timings A INNER JOIN '
						. 'pb_commands B ON A.commandId = B.commandId WHERE userId = ' . $id
						. ' ORDER BY timeslotBegin');
					
					// Fetch into an array:
					$vals = array();
					while ($row = mysqli_fetch_array($sql, MYSQLI_ASSOC)) {
						$vals[$row["commandId"]][] = array(
							"name" => $row["description"],
							"pid" => $row["timingId"],
							"timeslotBegin" => $row["timeslotBegin"],
							"timeslotEnd" => $row["timeslotEnd"],
							"days" => $row["days"],
							"runByDefault" => $row["runByDefault"]
						);
					}
					
					$echo .= (isset($msg) ? $msg : '')
					. '<form action="index.php?action=user&subac=editPermissions&id='
					. $id . '&sent=1" method="post"><input type="submit" value="Speichern" class="right" />';
					
					// A list of all possible commands, so it can be added:
					if ($USER->data["canManage"] == 1) {
						$sql = mysqli_query($CONNECTION, 'SELECT commandId, description FROM pb_commands');
						$echo .= '<select><option>Neue Berechtigung</option>';
						while ($row = mysqli_fetch_array($sql, MYSQLI_ASSOC)) {
							// I have to add the User ID too, because js cant get it.
							$echo .= '<option onclick="addSet(' . $id . ', ' . $row["commandId"]
							. ')">' . $row["description"] . '</option>';
						}
						$echo .= '</select>';
					}
					if ($USER->data['canManage'] == 1 || $USER->data['canSetTimings'])
						$SCRIPTS[] = 'editPermissions.js';
					
					$echo .= '<div class="clear"></div>';
					
					// Output the commands
					foreach ($vals as $command => $sets) {
						$echo .= '<div style="border: 1px solid #000; width: calc(50% - 12px); font-size: 16pt; '
						. 'background: linear-gradient(rgba(200, 200, 255, 1), rgba(200, 200, 255, 0) 10px); '
						. 'background-repeat: none; margin: 0px 5px 10px; padding: 6px 0px 0px; '
						. 'display: inline-block; text-align: center; float: left; border-radius: 3px;">'
						. $sets[0]["name"]
						. ($USER->data["canManage"] == 1 ? '<a href="index.php?action=user&subac=editPermissions&id='
						. $id . '&addSet=' . $command . '" class="right"><img src="imgs/add_small.png" alt="Add" '
						. 'style="margin-right: 10px; width: 16pt;" border="0" /></a>' : '')
						. '<div style="border-top: 1px solid #000; font-size: 12pt; margin-top: 6px;">';
						
						// Output the Sets:
						foreach ($sets as $set) {
							// Convert both times into nice times:
							$timeslotBegin = sprintf("%02d", floor($set["timeslotBegin"]/60))
							. ':' . sprintf("%02d", $set["timeslotBegin"] % 60);
							$timeslotEnd = sprintf("%02d", floor($set["timeslotEnd"]/60))
							. ':' . sprintf("%02d", $set["timeslotEnd"] % 60);
							
							
							// Output Set:
							$echo .= '<div style="margin: 5px; background-color: #D0D0FF; padding: 6px; '
							. 'text-align: center; border-radius: 3px; position: relative;">'
							. 'Bevorzugt: <input type="radio" value="1" name="runByDefault[' . $set["pid"] . ']"'
							. ($set["runByDefault"] == 1 ? ' checked="checked"' : '') . ' /> Ja'
							.  ' | <input type="radio" value="0" name="runByDefault[' . $set["pid"] . ']"'
							. ($set["runByDefault"] == 0 ? ' checked="checked"' : '') . ' /> Nein';
							
							if ($USER->data["canManage"] == 1 || $USER->data['canSetTimings']) {
								$echo .= '<div style="display: inline-block;">'
								. self::daySelector("days[" . $set["pid"] . "]", explode(",", $set["days"])) . '</div><br />'
								. 'Von <input type="text" value="' . $timeslotBegin . '" name="timeslotBegin['
								. $set["pid"] . ']" size="6" />'
								. ' bis <input type="text" value="' . $timeslotEnd . '" name="timeslotEnd['
								. $set["pid"] . ']" size="6" />'
								. '<a href="#" onclick="delSet(' . $id . ', ' . $set["pid"]
								. ');" class="right" style="position: absolute; top: 6px; right: 12px;">'
								. '<img src="imgs/delete_small.png" width="16" height="16" alt="Del" /></a>';
							}
							else {
								$nums = array(',', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun');
								$tage = array(', ', '<strong>Mo</strong>', '<strong>Di</strong>',
								'<strong>Mi</strong>', '<strong>Do</strong>', '<strong>Fr</strong>',
								'<strong>Sa</strong>', '<strong>So</strong>');
								$echo .= '<br />Tage: ' . str_replace($nums, $tage, $set["days"])
								. '<br />Von <strong>' . $timeslotBegin . '</strong> bis <strong>' . $timeslotEnd . '</strong>';
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
						mysqli_query($CONNECTION, "DELETE FROM pb_users WHERE userId = " . $id . " LIMIT 1");
						$heading = 'Basiseinstellungen';
						$echo = '<p style="color: red;">Benutzer wurde gel&ouml;scht</p>';
						break;
					}
					
					// Data has been sent
					if (isset($_GET["sent"], $_POST["active"], $_POST["name"],
					$_POST["canUseIFace"]) && !empty($_POST["name"])) {
						
						$d["active"] = $_POST["active"] == 1 ? 1 : 0;
						$d["name"] = $_POST["name"];
						$d["canUseIFace"] = $_POST["canUseIFace"] == 1 ? 1 : 0;
						
						
						// If admin, there are even a lot more opportunities!
						if ($USER->data["canManage"] == 1 && isset($_POST['canSetAlarm'],
						$_POST['canManage'], $_POST['canSeeGeneralLog'], $_POST['canEmptyLog'],
						$_POST['canSetTimings'])) {
							$d["canSetAlarm"] = $_POST["canSetAlarm"] == 1 ? 1 : 0;
							$d["canManage"] = $_POST["canManage"] == 1 ? 1 : 0;
							$d["canSeeGeneralLog"] = $_POST["canSeeGeneralLog"] == 1 ? 1 : 0;
							$d["canEmptyLog"] = $_POST["canEmptyLog"] == 1 ? 1 : 0;
							$d["canSetTimings"] = $_POST["canSetTimings"] == 1 ? 1 : 0;
						}
						
						// If the password has to be changed:
						if (isset($_POST["pwd_helper"])) {
							// Set pwd to empty
							if (!isset($_POST["pwd"]) || empty($_POST["pwd"]))
								$d["pwd"] = '';
							// Set new pwd:
							else {
								$d["pwd"] = password_hash($_POST['pwd'], PASSWORD_DEFAULT);
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
						. ' WHERE userId = ' . $id . ' LIMIT 1'))
							$notice = '<p style="color: #008000;"><strong>Meldung:</strong> '
							. 'Daten erfolgreich gespeichert!</p>';
						else
							$notice = '<p style="color: #800000;"><strong>Fehler:</strong> '
							. 'Es gab einen Speicherfehler!</p>' . mysqli_error($CONNECTION);
						
					}
					
					// Get all Information:
					$ben = new user($id);
					
					// Show settings only for admins:
					if ($USER->data["canManage"] == 1) {
						$adminsettings = '</tr><tr><td>Wecker stellen: </td>'
						. '<td><input type="radio" name="canSetAlarm" value="1"'
						. ($ben->data["canSetAlarm"] == 1 ? ' checked="checked"' : '') . ' /> Ja | '
						. '<input type="radio" name="canSetAlarm" value="0"'
						. ($ben->data["canSetAlarm"] == 0 ? ' checked="checked"' : '') . ' /> Nein</td>'
						. '</tr><tr><td>Admin: </td>'
						. '<td><input type="radio" name="canManage" value="1"'
						. ($ben->data["canManage"] == 1 ? ' checked="checked"' : '') . ' /> Ja | '
						. '<input type="radio" name="canManage" value="0"'
						. ($ben->data["canManage"] == 0 ? ' checked="checked"' : '') . ' /> Nein</td>'
						. '</tr><tr><td>Allgemeinen Log sehen: </td>'
						. '<td><input type="radio" name="canSeeGeneralLog" value="1"'
						. ($ben->data["canSeeGeneralLog"] == 1 ? ' checked="checked"' : '') . ' /> Ja | '
						. '<input type="radio" name="canSeeGeneralLog" value="0"'
						. ($ben->data["canSeeGeneralLog"] == 0 ? ' checked="checked"' : '') . ' /> Nein</td>'
						. '</tr><tr><td>Allgemeinen Log leeren: </td>'
						. '<td><input type="radio" name="canEmptyLog" value="1"'
						. ($ben->data["canEmptyLog"] == 1 ? ' checked="checked"' : '') . ' /> Ja | '
						. '<input type="radio" name="canEmptyLog" value="0"'
						. ($ben->data["canEmptyLog"] == 0 ? ' checked="checked"' : '') . ' /> Nein</td>'
						. '</tr><tr><td>Eigenen Zeitplan: </td>'
						. '<td><input type="radio" name="canSetTimings" value="1"'
						. ($ben->data["canSetTimings"] == 1 ? ' checked="checked"' : '') . ' /> Ja | '
						. '<input type="radio" name="canSetTimings" value="0"'
						. ($ben->data["canSetTimings"] == 0 ? ' checked="checked"' : '') . ' /> Nein</td>';
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
					. '<td><input type="text" name="name" value="' . $ben->data["name"] . '" /></td>'
					. '</tr><tr><td>Passwort: </td>'
					. '<td><input type="password" name="pwd" disabled="disabled" />'
					. '<input type="checkbox" name="pwd_helper" onclick="togglePwd();" /> <span style="color: #'
					. (empty($ben->data["pwd"]) ? '800000;">Nicht ' : '008000;">') . 'gesetzt</span></td>'
					. '</tr><tr><td>Interface: </td>'
					. '<td><input type="radio" name="canUseIFace" value="1"'
					. ($ben->data["canUseIFace"] == 1 ? ' checked="checked"' : '') . ' /> Ja | '
					. '<input type="radio" name="canUseIFace" value="0"'
					. ($ben->data["canUseIFace"] == 0 ? ' checked="checked"' : '') . ' /> Nein</td>'
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
			. ($USER->data["canManage"] == 1 ? '<a href="index.php?action=user">&larr; Zur&uuml;ck '
			. 'zur &Uuml;bersicht</a>' : '')
			. '<div class="clear"></div>' . $echo;
			
		}
		
		if (isset($_GET["addUser"])) {
			// Adds a new User:
			mysqli_query($CONNECTION, 'INSERT INTO pb_users (name, serial, pwd, canSetAlarm, canUseIFace, '
				. 'canManage, canSeeGeneralLog, canEmptyLog, canSetTimings, active) VALUES '
				. '("Newuser", 0, "", 0, 0, 0, 0, 0, 0, 0)');
		}
		
		// Collect all user Information:
		$output = '';
		$bgColor = '#EEEEEE';
		$sql = mysqli_query($CONNECTION, 'SELECT userId, name, canManage, active, canUseIFace FROM pb_users');
		while ($row = mysqli_fetch_array($sql, MYSQLI_ASSOC)) {
			// Select the right background-color:
			if ($row["canManage"] == 1) $bgColor = '#FFAAAA';
			elseif ($bgColor != '#EEEEEE') $bgColor = '#EEEEEE';
			else $bgColor = '#DDDDFF';
			
			// Append to the output
			$output .= '<tr style="background-color: ' . $bgColor
			. ($row["userId"] == $USER->data["userId"] ? '; font-weight: bold' : '')
			. ($row["active"] == 0 ? '; color: #888888' : '') . ';">'
			. '<td style="text-align: center;">#' . $row["userId"] . '</td>'
			. '<td style="padding: 0px 10px;">' . $row["name"] . '</td>'
			. '<td style="text-align: center;"><a href="index.php?action=user&subac=log&id=' . $row["userId"] . '">'
			. '<img src="imgs/log_small.png" alt="Log" border="0" /></a>'
			. ' <a href="index.php?action=user&subac=editBasic&id=' . $row["userId"] . '">'
			. '<img src="imgs/settings_small.png" alt="Einstellungen" border="0" /></a>'
			. ' <a href="index.php?action=user&subac=editPermissions&id=' . $row["userId"] . '">'
			. '<img src="imgs/perm_small.png" alt="Berechtigungen" border="0" /></a>'
			. ' <a href="index.php?action=user&subac=editCard&id=' . $row["userId"] . '">'
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
		
		if ($USER->data['canSetAlarm'] == 0 && $USER->data['canManage'] == 0)
			return self::noRights();
		
		// Collect all sounds:
		$sounds = array_filter(scandir('songs', SCANDIR_SORT_DESCENDING), function ($val) {
			return (preg_match('/^[a-zA-Z0-9_]{3,}\.(wav|WAV)$/', $val) == 1);
		});
		// Output the sounds:
		$soundOutput = '';
		foreach ($sounds as $sound) {
			$soundOutput .= '<tr><td style="vertical-align: center; font-weight: bold;">'
			. '&bull; ' . $sound . ': </td><td><audio controls><source src="songs/'
			. $sound . '" type="audio/wav"></audio></td></tr>';
		}
		
		// Form has been submitted
		if (isset($_GET["sent"])) {
			$descriptions = isset($_POST['description']) ? $_POST['description'] : array();
			$msgs = array();
			
			foreach ($descriptions as $id => $description) {
				if (!isset($_POST['command'][$id], $_POST['wavName'][$id],
				$_POST['time'][$id])) {
					$msgs[] = '<p style="color: #800000;"><strong>Fehler:</strong> '
					. 'Nicht alle Daten &uuml;bertragen.</p>';
					continue;
				}
				$description = htmlspecialchars($description);
				$isBackground = isset($_POST['isBackground'][$id]) ? 1 : 0;
				$command = $_POST['command'][$id];
				$wavName = $_POST['wavName'][$id];
				$time = $_POST['time'][$id];
				$delete = isset($_POST['delete'][$id]) ? true : false;
				$active = isset($_POST['active'][$id]) ? true : false;
				$days = isset($_POST['days'][$id]) ? implode(',', $_POST['days'][$id]) : '';
				
				if (strlen($description) < 3 || preg_match('/[0-9]{1,2}:[0-9]{1,2}/', $time) != 1
				|| (!file_exists('songs/' . $wavName) && $wavName != "")) {
					$msgs[] = '<p style="color: #800000;"><strong>Fehler:</strong> '
					. 'Ung&uuml;tlige Eingabe bei Wecker "' . $description . '": Entweder '
					. 'Bezeichnung zu kurz (min 3), keine g&uuml;ltige Zeitangabe oder '
					. 'die Tondatei existiert nicht.</p>';
					continue;
				}
				
				$timeArr = explode(':', $time);
				$time = ($timeArr[0] * 60) + $timeArr[1];
				
				// Delete the entry, if wanted:
				if ($delete) {
					if (mysqli_query($CONNECTION, 'DELETE FROM pb_alarms WHERE alarmId = '
					. $id . ' LIMIT 1') === true)
						$msgs[] = '<p style="color: #008000;"><strong>Meldung:</strong> "'
						. $description . '" erfolgreich gel&ouml;scht.</p>';
					else
						$msgs[] = '<p style="color: #800000;"><strong>Fehler:</strong> "'
						. $description . '" konnte nicht gel&ouml;scht werden: '
						. mysqli_error($CONNECTION) . '</p>';
					continue;
				}
				
				// Update the entry:
				if (mysqli_query($CONNECTION, 'UPDATE pb_alarms SET description = "' . $description
				. '", days = "' . $days . '", time = ' . $time . ', active = ' . ($active ? 1 : 0)
				. ', wavName = "' . $wavName . '", command = ' . $command
				. ', isBackground = "' . $isBackground . '"'
				. ' WHERE alarmId = ' . $id . ' LIMIT 1') !== true)
					$msgs[] = '<p style="color: #800000;"><strong>Fehler:</strong> "'
					. $description . '" konnte nicht gespeichert werden: '
					. mysqli_error($CONNECTION) . '</p>';
			}
			
			// inform the application via the socket:
			informPinApplication('action=ALARMS_UPDATED:;:' . getNextAlarm());
			
			$msg = empty($msgs) ? '<p style="color: #008000;"><strong>Meldung:</strong> '
			. 'Erfolgreich gespeichert</p>' : implode($msgs);
		}
		
		
		if (isset($_GET['newAlarm']) && empty($sounds))
			$msg = '<p style="color: #800000;"><strong>Fehler:</strong> '
			. 'Kann keien neuen Wecker einrichten, solange es keine Tondateien gibt</p>';
		elseif (isset($_GET['newAlarm'])) {
			if (mysqli_query($CONNECTION, 'INSERT INTO pb_alarms (description, days, time, '
			. 'active, wavName, command, isBackground) VALUES ("Neuer Wecker", "Mon,Tue,Wed,Thu,Fri", 420, '
			. '0, "' . $sounds[0] . '", 0, 0)') === true)
				$msg = '<p style="color: #008000;"><strong>Meldung:</strong> Neuen Wecker erstellt</p>';
			else
				$msg = '<p style="color: #800000;"><strong>Fehler:</strong> '
				. 'Konnte keinen neuen Wecker erstellen: ' . mysqli_error($CONNECTION) . '</p>';
		}
		
		
		// Collect all commands
		$sql = mysqli_query($CONNECTION, 'SELECT commandId, description FROM pb_commands');
		$commands = array();
		while ($row = mysqli_fetch_array($sql, MYSQLI_ASSOC))
			$commands[$row['commandId']] = $row['description'];
		
		// Collect all alarms and output in a table:
		$sql = mysqli_query($CONNECTION, 'SELECT alarmId, description, days, time, active, '
		. 'wavName, command, isBackground FROM pb_alarms');
		$output = '';
		$alternating = false;
		while ($row = mysqli_fetch_array($sql, MYSQLI_ASSOC)) {
			$alternating = !$alternating;
			if ($row['active'] == 0)
				$bgColor = '#FFE0E0';
			else
				$bgColor = $alternating ? '#E0FFE0' : '#DAE0DA';
			$time = sprintf("%02d", floor($row["time"] / 60))
				. ':' . sprintf("%02d", $row["time"] % 60);
			$output .= '<tr style="background-color: ' . $bgColor . '; text-align: center;">'
			. '<td style="padding: 0px 10px;"><input type="text" name="description[' . $row['alarmId']
				. ']" value="' . htmlspecialchars_decode($row['description']) . '" size="10">'
				. '<br>Im Hintergrund*: '
				. '<input type="checkbox" name="isBackground[' . $row['alarmId'] . ']" value="1"'
				. ($row['isBackground'] == 1 ? ' checked' : '') . '>'
				. '<br><span style="color: #800000;">L&ouml;schen: </span>'
				. '<input type="checkbox" name="delete[' . $row['alarmId'] . ']"></td>'
			. '<td style="padding: 0px 10px;"><div style="display: inline-block;">'
				. self::daySelector('days[' . $row['alarmId'] . ']', explode(',', $row['days'])) . '</div>'
				. '<input type="text" name="time[' . $row['alarmId'] . ']" value="'
				. $time . '" size="5"></td>'
			. '<td style="padding: 0px 10px;"><select name="command[' . $row['alarmId'] . ']">'
			. '<optgroup label="Befehl"><option value="0">Nichts machen</option>';
			foreach ($commands as $id => $command)
				$output .= '<option value="' . $id
				. ($id == $row['command'] ? '" selected>' : '">')
				. $command . '</option>';
			$output .= '</optgroup></select><br><br>'
			. '<select name="wavName[' . $row['alarmId'] . ']">'
			. '<option value="">Keinen Song</option>'
			. '<optgroup label="Sound">';
			foreach ($sounds as $sound)
				$output .= '<option value="' . $sound
				. ($sound == $row['wavName'] ? '" selected>' : '">')
				. $sound . '</option>';
			$output .= '</optgroup></select></td>'
			. '<td style="padding: 0px 10px;"><input type="checkbox" name="active[' . $row['alarmId'] . ']"'
			. ($row['active'] == 1 ? ' checked>' : '>')
			. '</td></tr>';
		}
		
		// Output everything
		return '<h2>Wecker</h2>'
		. '<form action="index.php?action=alarm&sent=1" method="post">'
		. '<a href="index.php?action=alarm&newAlarm=1">Neuen Wecker einrichten</a>'
		. '<input type="submit" value="Speichern" class="right">'
		. '<div class="clear"></div>'
		. (isset($msg) ? $msg : '')
		. (empty($output) ? '<div style="font-weight: bold; text-align: center;">Noch keine '
		. 'Wecker konfiguriert</div>' : '<table border="1" style="border-collapse: collapse; '
		. 'margin: 0px; width: 100%;"><tr><th>Beschreibung</th><th>Aktivierungszeit</th>'
		. '<th>Verhalten</th><th>Aktiv</th></tr>'
		. $output . '</table>')
		. '<div class="clear"></div>'
		. '*Im Hintergrund ausgef&uuml;hrte Wecker k&ouml;nnen keinen Ton abspielen!'
		. '<input type="submit" value="Speichern" class="right"></form>'
		. '<div class="clear"></div>'
		. '<div style="border-radius: 7px; margin: 10px 0px 0px; padding: 10px; '
		. 'background-color: #E0E0E0;">'
		. 'Um eine WAV-Datei als Weckton zu verwenden, muss sie in den Order '
		. '<span style="color: #606060;">' . realpath('songs') . '</span> '
		. 'kopiert werden. Nur Sounddateien mit dem Typ .wav oder .WAV sind g&uuml;ltig.'
		. '<table border="0" style="width: 100%;">' . $soundOutput . '</table>'
		. '</div>';
	}
	
	
	
	
	
	// Output the Welcome-Text:
	static public function main() {
		global $USER, $CONNECTION, $SCRIPTS, $SYSTEM;
		$SCRIPTS[] = 'main_page.js';
		
		// Should the user do something:
		if (isset($_GET['method']) && $_GET['method'] == 'loginDesk')
			logInToDesk($USER->data['userId']);
		elseif (isset($_GET['method']) && $_GET['method'] == 'logoutDesk')
			logoutFromDesk();
		
		// Get the current actions:
		$actions = $USER->getCurrentActions();
		$actionLinks = '';
		if (!empty($actions)){
			$sql = mysqli_query($CONNECTION, 'SELECT commandId, description FROM pb_commands WHERE commandId IN ('
			. implode(',', $actions) . ')');
			while ($row = mysqli_fetch_array($sql, MYSQLI_ASSOC)) {
				$actionLinks .= '&nbsp;<button onclick="triggerCommand(' . $row["commandId"]
				. ')">' . $row["description"] . '</button>';
			}
		}
		
		// Output
		if ($SYSTEM['currentlyLoggedIn'] == 0)
			$eingelogged = '<span style="color: #0000FF;">Keiner</span>';
		else
			$eingelogged = '<span style="color: #005000;">'
			. (new user($SYSTEM['currentlyLoggedIn']))->data['name'] . '</span>';
		return '<h2>Eigenverwaltung</h2>'
		. '<fieldset><legend style="font-weight: bold;">Pultverwaltung</legend>'
		. '<b>Zurzeit am Pult angemeldet:</b> ' . $eingelogged . '<br>'
		. '<b>Aktionen:</b> <a href="index.php?method=logoutDesk">Benutzer abmelden</a> | '
		. '<a href="index.php?method=loginDesk">Mich anmelden</a>'
		. '</fieldset><fieldset><legend style="font-weight: bold;">Kommandos ausf&uuml;hren</legend>'
		. $actionLinks . '</fieldset><br>'
		. self::history(10, $USER->data["userId"]);
		
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
		. '<td><input type="checkbox" name="' . $name . '[]" value="Mon"'
		. (in_array('Mon', $arr_selected) ? ' checked="checked"' : '') . ' /></td>'
		. '<td><input type="checkbox" name="' . $name . '[]" value="Tue"'
		. (in_array('Tue', $arr_selected) ? ' checked="checked"' : '') . ' /></td>'
		. '<td><input type="checkbox" name="' . $name . '[]" value="Wed"'
		. (in_array('Wed', $arr_selected) ? ' checked="checked"' : '') . ' /></td>'
		. '<td><input type="checkbox" name="' . $name . '[]" value="Thu"'
		. (in_array('Thu', $arr_selected) ? ' checked="checked"' : '') . ' /></td>'
		. '<td><input type="checkbox" name="' . $name . '[]" value="Fri"'
		. (in_array('Fri', $arr_selected) ? ' checked="checked"' : '') . ' /></td>'
		. '<td><input type="checkbox" name="' . $name . '[]" value="Sat"'
		. (in_array('Sat', $arr_selected) ? ' checked="checked"' : '') . ' /></td>'
		. '<td><input type="checkbox" name="' . $name . '[]" value="Sun"'
		. (in_array('Sun', $arr_selected) ? ' checked="checked"' : '') . ' /></td>'
		. '</tr></table>';
		
	}
	
	// No Rights to call the wanted page:
	static private function noRights() {
		return '<h2>Keine Berechtigung</h2>';
	}
	
	
	static private function history($limiter = 30, $user_overwrite = 0, $sorts = array()) {
		global $CONNECTION;
		
		// Get all history-logs:
		if ($user_overwrite > 0)
			$request = 'SELECT sort, time, content FROM pb_log WHERE userId = ' . $user_overwrite;
		else
			// Last WHERE condition only for simplifying the filters:
			$request = 'SELECT name, sort, time, content FROM pb_log A LEFT JOIN '
			. 'pb_users B ON A.userId = B.userId WHERE A.userId >= 0';
		
		// Set the Limits:
		if (isset($_GET["logpage"]) && is_numeric($_GET["logpage"]) && $_GET["logpage"] > 0) {
			$lowerlimit = ($limiter * $_GET["logpage"]);
		}
		else {
			$lowerlimit = 0;
		}
		
		// Apply the filters:
		$filters = '';
		if (isset($_GET['filterErr']) && $_GET['filterErr'] == 1)
			$filters .= ' AND sort <> \'error\'';
		if (isset($_GET['filterTimed']) && $_GET['filterTimed'] == 1)
			$filters .= ' AND sort <> \'timedAction\'';
		if (isset($_GET['filterInt']) && $_GET['filterInt'] == 1)
			$filters .= ' AND sort <> \'loginToInterface\' AND sort <> \'loginToInterfaceFailed\'';
		if (isset($_GET['filterDesk']) && $_GET['filterDesk'] == 1)
			$filters .= ' AND sort <> \'loginToDesk\' AND sort <> \'loginToDeskFailed\'';
		
		// Run the request:
		$sql = mysqli_query($CONNECTION, $request . $filters . ' ORDER BY logId DESC LIMIT '
		. $lowerlimit . ', ' . $limiter);
		echo mysqli_error($CONNECTION);
		
		// Process the request:
		$output = '';
		$dateBefore = 0;
		while ($row = mysqli_fetch_array($sql, MYSQLI_ASSOC)) {
			
			// If a new day is represented:
			if ($dateBefore != date('z', $row["time"])) {
				$dateBefore = date('z', $row["time"]);
				if ($dateBefore == date('z'))
					$tag = 'Heute';
				elseif ($dateBefore + 1 == date('z'))
					$tag = 'Gestern';
				else
					$tag = formatDate($row["time"]);
				
				$output .= '<tr><th colspan="3" style="background-color: #FFFAAA;">'
				. $tag . '</th></tr>';
			}
			
			// Select output and color by type:
			switch ($row["sort"]) {
				case 'error':
					$bgcolor = '#FF8080';
					$fgcolor = '#000000';
					break;
				case 'timedAction':
					$bgcolor = '#E0E0E0';
					$fgcolor = '#000000';
					break;
				case 'loginToInterface':
					$bgcolor = '#80FF80';
					$fgcolor = '#000080';
					break;
				case 'loginToDesk':
					$bgcolor = '#80FF80';
					$fgcolor = '#000000';
					break;
				case 'loginToInterfaceFailed':
					$bgcolor = '#FF8080';
					$fgcolor = '#000000';
					break;
				case 'loginToDeskFailed':
					$bgcolor = '#FF8080';
					$fgcolor = '#000000';
					break;
				default:
					$bgcolor = '#FF0000';
					$fgcolor = '#FFFFFF';
					break;
			}
			$msg = $row['content'];
			
			// The general output:
			$output .= '<tr style="color: ' . $fgcolor . '; background-color: ' . $bgcolor . ';"><td>'
			. date('H:i:s', $row["time"]) . '</td><td>' . $msg . '</td>'
			. ($user_overwrite == 0 ? '<td>' . (empty($row["name"]) ? 'SYSTEM-SVC' : $row["name"]) . '</td>' : '')
			. '</tr>';
		}
		
		// Make a count (for the buttons)
		$links = '';
		$count = mysqli_fetch_array(mysqli_query($CONNECTION, 'SELECT COUNT(*) as zaehler FROM pb_log'
		. ($user_overwrite == 0 ? '' : ' WHERE userId = ' . $user_overwrite)), MYSQLI_ASSOC);
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
		. '</table> Ausfiltern: '
		. '<a href="' . (isset($_GET['filterErr']) && $_GET['filterErr'] == 1
			? self::addParamLink('filterErr', '0') . '" style="color: #800000;'
			: self::addParamLink('filterErr', '1')) . '">Systemfehler</a> | '
		. '<a href="' . (isset($_GET['filterTimed']) && $_GET['filterTimed'] == 1
			? self::addParamLink('filterTimed', '0') . '" style="color: #800000;'
			: self::addParamLink('filterTimed', '1')) . '">Getimte Aktionen</a> | '
		. '<a href="' . (isset($_GET['filterInt']) && $_GET['filterInt'] == 1
			? self::addParamLink('filterInt', '0') . '" style="color: #800000;'
			: self::addParamLink('filterInt', '1')) . '">Interface</a> | '
		. '<a href="' . (isset($_GET['filterDesk']) && $_GET['filterDesk'] == 1
			? self::addParamLink('filterDesk', '0') . '" style="color: #800000;'
			: self::addParamLink('filterDesk', '1')) . '">Pult</a>'
		. '<br>' . $links;
		
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