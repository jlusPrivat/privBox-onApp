<?php
/*
This is only opened by the local schedule holder Software.
It has the following features, all sent via GET:

Always returns:
- outPins=(csv)
- buttonPins=(csv)
- switchPins=(csv)
- virtualLatchPins=(csv)
- onPins=(csv)
- offPins=(csv)
- nextAlarmId=(id or empty)
- nextAlarmName=(name or empty)
- nextAlarmTriggerIn=(time or 0)
- nextAlarmSound=(string or empty)
All seperated by :;:

- action=START_UP: Maps all output pins to MODE out and resets the pins to the default value.
- action=RELOAD_DATA: like StartUp, just without logging as startup
- action=TRIGGER_ALARM&id=... Trigger the command for the alarm
	this command also gives triggerAlarm=yes, if alarm is really to be triggered
- action=TRIGGER_PIN&gpio=...&PorR=...[P for Push || R for Release]
- action=CARD_SCANNED&serial=... Where serial must be numeric.

*/

require_once 'includes/core.php';
if ($_SERVER['REMOTE_ADDR'] != '127.0.0.1' && $SYSTEM['controllerLocalOnly'] == 1)
	die('Unsifficient permissions to access non-locally.');

if (!isset($_GET['action']))
	die('You need to declare the GET-Param "action":<br>'
		. 'startUp, reloadData, buttonPushed, cardScanned');
	
// prohibit the use of the socket:
$SYSTEM["deadMode"] = 1;
	

switch ($_GET['action']) {
	case 'START_UP':
		// reset all pins in the db
		mysqli_query($CONNECTION, 'UPDATE a SET a.switchState = b.defaultOnStartup FROM pb_pins a
					INNER JOIN pb_pins b ON a.pinId = b.pinId');
		writeLog('timedAction', 'StartUp Sequence complete');
		die(getReloadData());
	break;

	case 'RELOAD_DATA':
		// do actually nothing except for echoing the default stuff
		die(getReloadData());
	break;

	case 'TRIGGER_ALARM':
		if (!isset($_GET['id'])) {
			writeLog('error', 'Controller did trigger Alarm with no ID');
			die('FAIL');
		}
		
		// Get the alarmSwitch:
		$switch = mysqli_fetch_array(mysqli_query($CONNECTION, 'SELECT pinId FROM pb_pins '
			. 'WHERE FIND_IN_SET(\'alarmIn\', special)>0 AND switchState = 0 LIMIT 1'), MYSQLI_ASSOC);
		if (!empty($switch))
			die(getReloadData());
		
		// Get the Alarm Clock:
		$alarm = mysqli_fetch_array(mysqli_query($CONNECTION, 'SELECT description, days, time, '
			. 'active, wavName, command FROM pb_alarms WHERE alarmId = ' . $_GET['id']));
		if (empty($alarm))
			die(getReloadData());
		
		// Check, if it is still an allowed day:
		if (!in_array(date('D'), explode(',', $alarm['days'])))
			die(getReloadData());
		
		// Check, if it still within a time frame of 30 Seconds:
		$timeDifference = date('s') + 60*date('i') + 3600*date('G') - $alarm['time'] * 60;
		if ($timeDifference > 30 || $timeDifference < -30)
			die(getReloadData());
		
		// Ok, do the Alarm stuff
		callCommand($alarm['command']);
		writeLog('timedAction', 'Alarm Clock: Triggered ' . $alarm['description']);
		die('triggerAlarm=yes:;:' . getReloadData());
	break;
	
	// If a Pin had been triggered:
	case 'TRIGGER_PIN':
		if (!isset($_GET['gpio'], $_GET['PorR']) ||
		$_GET['PorR'] != 'P' && $_GET['PorR'] != 'R') {
			writeLog('error', 'Called controller.php?triggerPin without valid params');
			die('FAIL');
		}
		
		// Get the Pin:
		$pin = mysqli_fetch_array(mysqli_query($CONNECTION, 'SELECT onPushed, onReleased, sort, '
			. 'special, switchState FROM pb_pins WHERE gpio = ' . $_GET['gpio']), MYSQLI_ASSOC);
		if (empty($pin)) {
			writeLog('error', 'Pin not found but triggered: ' . $_GET['gpio']);
			die('FAIL');
		}
		
		$switchState = $pin['switchState'] == 1 ? true : false;
		// Handle the pin differently judging by the type:
		if ($pin['sort'] == 'inButton')
			$switchState = $_GET['PorR'] == 'P' ? true : false;
		elseif ($pin['sort'] == 'inVirtualLatch' && $_GET['PorR'] == 'P')
			$switchState = !$switchState;
		elseif ($pin['sort'] == 'inSwitch')
			$switchState = $_GET['PorR'] == 'P' ? true : false;
		if ($pin['sort'] != 'inButton')
			mysqli_query($CONNECTION, 'UPDATE pb_pins SET switchState = '
				. ($switchState ? '1' : '0') . ' WHERE gpio = ' . $_GET['gpio']);
		
		// Finally check, if it means to log the user out of the desk
		if ($pin['special'] == 'logoutIn')
			logoutFromDesk();
		
		die(getReloadData());
	break;

	
	case 'CARD_SCANNED':
		if (!isset($_GET['serial']) || !is_numeric($_GET['serial'])) {
			writeLog('error', 'No valid serial given');
			die('FAIL');
		}
		
		// If it is used for the system (webIFace):
		if ($SYSTEM['getCertainCard'] == $_GET['serial']) {
			systemRewrite('getCertainCard', 0);
			switchLoginWebOut(false);
			die(getReloadData());
		}
		if ($SYSTEM['getAnyCard'] == 0) {
			systemRewrite('getAnyCard', $_GET['serial']);
			switchLoginWebOut(false);
			die(getReloadData());
		}
		
		// Get the User ID:
		$udata = mysqli_fetch_array(mysqli_query($CONNECTION, 'SELECT userId FROM '
			. 'pb_users WHERE serial = ' . $_GET['serial'] . ' LIMIT 1'), MYSQLI_ASSOC);
		
		if (empty($udata))
			die('FAIL');
		
		logInToDesk($udata['userId']);
		die(getReloadData());
	break;

	// Error, because action is unknown
	default:
		writeLog('error', 'Unknown caller in controller.php: ' . $_GET['action']);
		die('FAIL');
	break;
}



/*Always returns:
- outPins=(csv)
- buttonPins=(csv)
- switchPins=(csv)
- virtualLatchPins=(csv)
- onPins=(csv)
- offPins=(csv)
- nextAlarmId=(id or empty)
- nextAlarmName=(name or empty)
- nextAlarmTriggerIn=(time or 0)
- nextAlarmSound=(string or empty)*/
function getReloadData() {
	global $CONNECTION;
	$returner = '';
	
	// first the pins:
	$outPins = Array();
	$buttonPins = Array();
	$switchPins = Array();
	$virtualLatchPins = Array();
	$onPins = Array();
	$offPins = Array();
	
	$pins = mysqli_query($CONNECTION, 'SELECT * FROM pb_pins');
	while ($pin = mysqli_fetch_array($pins, MYSQLI_ASSOC)) {
		switch ($pin['sort']) {
			case 'inButton': $buttonPins[] = $pin['gpio']; break;
			case 'inVirtualLatch': $virtualLatchPins[] = $pin['gpio']; break;
			case 'inSwitch': $switchPins[] = $pin['gpio']; break;
			case 'output': $outPins[] = $pin['gpio']; break;
			default: writeLog('error', 'Unknown sort of pinId ' . $pin['pinId']); break;
		}
		switch ($pin['switchState']) {
			case 0: $offPins[] = $pin['gpio']; break;
			case 1: $onPins[] = $pin['gpio']; break;
		}
	}
	$returner .= 'outPins=' . implode(',', $outPins)
	. ':;:buttonPins=' . implode(',', $buttonPins)
	. ':;:switchPins=' . implode(',', $switchPins)
	. ':;:virtualLatchPins=' . implode(',', $virtualLatchPins)
	. ':;:onPins=' . implode(',', $onPins)
	. ':;:offPins=' . implode(',', $offPins) . ':;:';
	
	
	// then do everything with the alarms
	$returner .= getNextAlarm();
	
	return $returner;
}
?>
