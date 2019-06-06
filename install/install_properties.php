<?php
$conf = array(
	// The Software Title
	'title' => 'PrivBox',
	
	// Do you want to show a License? (empty for no, path for license)
	'license_full' => '',
	'license_shortdescription' => '<a rel="license" href="http://creativecommons.org/licenses/by-nc-sa/4.0/">'
		. '<img alt="Creative Commons License" style="border-width:0" '
		. 'src="https://i.creativecommons.org/l/by-nc-sa/4.0/88x31.png" /></a><br />This '
		. 'work is licensed under a <a rel="license" href="http://creativecommons.org/licenses/by-nc-sa/4.0/">'
		. 'Creative Commons Attribution-NonCommercial-ShareAlike 4.0 International License</a>.',
	
	// Installation Messages like required server settings and stuff
	'install_message' => '<p><b>Projektnummer:</b> 6</p>'
	. '<p><b>Hintergrund:</b> Ich wollte meine Zimmerelektronik automatisieren (L&uuml;ftungsanlange, Computer, '
	. 'Peripherie, NAS, …) und habe zu diesem Zweck dieses System als Teilsystem entwickelt.</p>'
	. '<p><b>Beschreibung:</b> Um dieses System zu implementieren erfordert es ein wenig Kenntnisse im '
	. 'elektrotechnischen Bereich und eine Steuerungseinheit (z.B. die GPIOs von einer Raspberry Pi sind '
	. 'hervorragend geeignet). Es kann mit einem Kartenleser angesteuert werden und erm&ouml;glicht den Zugriff '
	. 'auf vordefinierte Befehle und Funktionen, die entweder automatisch ausgef&uuml;hrt werden sollen, oder dem '
	. 'Benutzer die M&ouml;glichkeit bieten diese zu bestimmten Zeiten auszuführen.</p>',
		
	// If there is a fixed prefix given already, set here true
	'db_fixed_prefix' => true,
	
	// The path of the config file
	'config_file_path' => '../includes/mysql.php',
	
	// Ask for first Userinformations:
	'ask_user_info' => true,
	'user_information' => 'Um sich das erste mal einzuloggen m&uuml;ssen Sie in der MySQL-Tabelle nach Anklicken Ihres Benutzers '
	. 'innerhalb von 30 Sekunden pb_system.active_certain_key = 0 setzen.'
);



// This function is run after everything has been checked (no termination required, only saving)
// Return true, if it was successfull, return false, if not
// $connection for mysqli is provided
// $username and $pwd provided
function setFirstUser($connection, $username, $pwd) {
	$sql = 'INSERT INTO `pb_users` (`id`, `username`, `card_id`, `pwd`, `salt`, `notify`, `admin`, `active`, `alarm_clock`, `deactivate_alarm`, `iface`, `log`, `per_quantif`, `per_count`) VALUES
(1, \'' . $username . '\', \'00001\', \'' . md5($pwd . '12345') . '\', 12345, 0, 1, 1, 0, 0, 1, 0, 0, 0)';
	if (!@mysqli_query($connection, $sql))
		return false;
	else
		return true;
}

?>
