<?php
require_once "includes/core.php";

/*
Requirements:
- WiringPi Library
- WakeOnLan
- Autostart to this page
- Apache, MySQL, PHP -> Start all as Service
- php.ini set execution-timelimit = 40 seconds
*/

// Only open for localhost
if ($SYSTEM["public_server_interface"] == 0 && $_SERVER["REMOTE_ADDR"] != "127.0.0.1")
	die("Unauthorisierter Zugriff");

// If a key has been read:
if (isset($_POST["key"])) {
	$key = $_POST["key"];
	
	// No valid key: Length or numeric
	if (strlen($key) != $SYSTEM["key_length"] || !is_numeric($key))
		exit;
	
	// If there is a pending Webinterface-request:
	if ($SYSTEM["active_timestamp"] + 30 > time()) {
		
		// If a certain card is requested:
		if ($SYSTEM["active_certain_key"] == $key) {
			// Seems like the user wants to log in:
			systemRewrite("active_certain_key", "0");
			exit;
		}
		
		// If any card is requested:
		if ($SYSTEM["active_any_key"] == "0") {
			systemRewrite("active_any_key", $_POST["key"]);
			exit;
		}
		
	}
	
	// check for the user:
	$id = mysqli_fetch_array(mysqli_query($CONNECTION, 'SELECT id FROM pb_users WHERE card_id = "' . $key . '" LIMIT 1'), MYSQLI_ASSOC);
	$benutzer = new user($id["id"]);
	
	// If in Lockdown and the user is not an admin: Cancel
	if ($SYSTEM["lockdown"] && $benutzer->data["admin"] != 1)
		exit;
	
	// If the card is dedicaded to switch the alarm:
	if ($benutzer->data["deactivate_alarm"] == 1) {
		writeLog(4, false, $benutzer->data["id"]);
		if ($SYSTEM["alarm_once_timeout"] == 1)
			systemRewrite("alarm_once_timeout", 0);
		else
			systemRewrite("alarm_once_timeout", 1);
		exit;
	}
	
	// Here comes the hardware part: Call the commands:
	$helper = $benutzer->getCurrentActions(true);
	
	if ($helper === false) {
		// Per_count was used up or card not active:
		writeLog(2, false, $benutzer->data["id"]);
		exit;
	}
	
	
	foreach ($helper as $command_id) {
		callCommand($command_id);
	}
	
	// And log the success
	writeLog(2, true, $benutzer->data["id"]);
	exit;
}


// If the alarm has to go off
if (isset($_POST["alarm"]) && $_POST["alarm"] == 1) {
	
	// if the alarm is deactivated permanently:
	if ($SYSTEM["alarm_active"] == 0)
		exit;
	
	// if the alarm is not today:
	$alarm_days = explode(",", $SYSTEM["alarm_days"]);
	if (!in_array(date('N'), $alarm_days))
		exit;
	
	// If the alarm is deactivated once:
	if ($SYSTEM["alarm_once_timeout"] == 1) {
		systemRewrite("alarm_once_timeout", 0);
		exit;
	}
	
	// Everthing is fine:
	/*$alarm_commands = explode(",", $SYSTEM["alarm_commands"]);
	foreach ($alarm_commands as $command) {
		callCommand($command);
	}*/
	callCommand($SYSTEM["alarm_commands"]);
	writeLog(4, true);
	die("OK");
	
}



// Autostart the needed Commands
$autostarts = mysqli_query($CONNECTION, "SELECT id FROM pb_commands WHERE autostart = 1");
while ($command = mysqli_fetch_array($autostarts, MYSQLI_ASSOC)) {
	callCommand($command["id"]);
}

// Get the Time:
$to_alarm = (($SYSTEM["alarm_time"] * 60) - ((date('G') * 3600) + (date('i') * 60) + date('s')));
if ($to_alarm < 0)
	$to_alarm = $to_alarm + 86400; // add one day in sec

// If the alarm is deactivated (Sorry for the calculations beforehand)
if ($SYSTEM["alarm_active"] == 0)
	$to_alarm = 0;

$include_js_vars = 'var key_length = ' . $SYSTEM["key_length"] . ';
var browser_reload = ' . ($SYSTEM["browser_reload"] * 60000) . ';
var to_alarm = ' . ($to_alarm * 1000) . ';';


// Write into the log:
if (!isset($_GET["reload"]))
	writeLog(0, true);
else
	writeLog(1, true);
?>


<!DOCTYPE html>

<html>
	<head>
		<meta charset="UTF-8" />
		<title>Internal System</title>
		<script src="js/jquery.js" type="text/javascript"></script>
		<script type="text/javascript"><?php echo $include_js_vars; ?>
		var timer;
		$(document).ready(function(){
			
			// Set the timeout for the reload
			if (browser_reload) {
				window.setTimeout(function() {
					window.location.assign("http://127.0.0.1/controller.php?reload=1");
				}, browser_reload);
			}
			
			// is the audio-element available?
			if ($('audio').length && to_alarm) {
				// Set the timeout for the alarm clock:
				window.setTimeout(function() {
					$.post("controller.php", {"alarm": 1}, function(data) {
						// Play the audio
						if (data == "OK")
							$('audio').get(0).play();
					});
				}, to_alarm);
			}
			
		});
		
		
		// Function, that is called every time a change has been made
		function submitCard() {
			
			var inhalt = $('#key').val();
			if (inhalt.length == key_length) {
				// Key complete and can be sent:
				$.post("controller.php", {"key": inhalt});
			}
			
			// And clear it, after there was 1 second, where no input was made:
			if (timer)
				clearTimeout(timer);
			timer = setTimeout(function() {$('#key').val("");}, 1000);
			
		}
		</script>
	</head>
	<body>
		<input type="text" id="key" oninput="submitCard();" autofocus />
		<?php
		if (file_exists("wecker.mp3"))
			echo '<audio src="wecker.mp3" preload="auto">Not supported</audio>';
		?>
	</body>
</html>