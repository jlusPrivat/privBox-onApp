<?php
if (!file_exists('includes/mysql.php')) {
	header('Location: install/index.php');
	exit;
}
if (file_exists('install/index.php'))
	die('Delete the installation directory first.');
	

require_once "includes/core.php";

/* Session-Variables:
id; last_time; authed_pwd_for; authed_card_for*/

// Preset the global Variables (Except for $CONNECTION, $SYSTEM and $USER)
$SCRIPTS = array('jquery.js');


if (isset($USER)) {
	// Defne the menu:
	$menu_arr = array(
		'main' => 'Eigenverwaltung',
		'user' => 'Benutzermanagement',
		'alarm' => 'Wecker',
		'system' => 'System',
		'logout' => 'Logout'
	);
	$menu = '';
	foreach ($menu_arr as $key => $val) {
		$menu .= '<li><a href="index.php?action=' . $key . '"'
		. ((isset($_GET["action"]) && $_GET["action"] == $key) || (!isset($_GET["action"]) && $key == "main") ? ' class="active"' : '')
		. '>' . $val . '</a></li>';
	}
	
	// Set the Menu and basic Design:
	$STYLE = 'basic.css';
	$ECHO = '<div id="top"><div id="header"><h1>PrivBox Control</h1></div><div id="topmenu"><ul>'
	. '<li><a href="http://www.jlus.de" target="_blank">Entwicklerhomepage</a></li>'
	. '<li><a href="http://www.secucom.org" target="_blank">Secucom Webinterface</a></li>'
	. '<li><a href="index.php?action=logout">Logout</a></li>'
	. '</ul></div><div class="clear"></div></div>'
	. '<div id="contentwrap"><div class="cright">';
	
	// Call the wanted page
	if (isset($_GET["action"]) && is_callable('pages::' . $_GET["action"])) {
		$ECHO .= call_user_func("pages::" . $_GET["action"]);
	}
	else
		$ECHO .= pages::main();
	
	$ECHO .= '</div><div class="cleft"><ul>'
	. $menu
	. '</ul></div><div class="clear"></div></div><div id="footer"><div class="left">Copyright &copy; '
	. '2017 <a href="http://www.jlus.de" target="_blank">Jonathan Lusky</a></div><div class="right"><strong>'
	. $USER->data["username"] . '</strong> [#' . $USER->data["id"] . ']</div><div class="clear"></div></div>';
}



// User is not logged in:
else {
	// Download all possible user:
	$users = '';
	$sql = mysqli_query($CONNECTION, 'SELECT id, username, pwd, admin FROM pb_users WHERE iface = 1 AND active = 1' . ($SYSTEM["lockdown"] == 1 ? ' AND admin = 1' : ''));
	while ($row = mysqli_fetch_array($sql, MYSQLI_ASSOC)) {
		$users .= '<div id="user_' . $row["id"] . '" class="user' . ($row["admin"] == 1 ? ' admin' : '')
		. (empty($row["pwd"]) ? '' : ' pwd') . '"><img src="imgs/' . (is_file('imgs/' . $row["id"] . '.png') ? $row["id"] : 'default')
		. '.png" alt="Bild" />' . $row["username"] . '</div>';
	}
	
	// User is not logged in:
	$STYLE = 'login.css';
	$SCRIPTS[] = 'login.js';
	$ECHO = '<div id="main">' . $users . '</div><div id="fader">&nbsp;</div><div id="prompt"><div id="border_active">&nbsp;</div>'
	. '<div><div id="pwd">Passwort</div><div id="keycard">Karte</div><div id="continue" onclick="location.reload();">Weiter</div>'
	. '<div id="countdown">30</div></div>'
	. '<div style="margin: 50px 0px 0px; text-align: center; clear: both;"><form action="#" onsubmit="return sendPwd();">'
	. '<input type="password" placeholder="Passworteingabe" id="pwdField" /></form></div>'
	. '</div>';
}
?>


<!DOCTYPE html>

<html>
	<head>
		<meta charset="utf-8" />
		<title>PrivBox Webinterface</title>
		<link rel="stylesheet" href="<?php echo $STYLE; ?>" type="text/css" />
		<?php foreach ($SCRIPTS as $script) {echo '<script src="js/' . $script . '" type="text/javascript"></script>' . PHP_EOL;} ?>
	</head>
	<body>
		<?php echo $ECHO; ?>
	</body>
</html>
