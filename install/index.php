<!-- Installationsmanager-Version: 1.1 -->
<?php
session_start();
require_once 'install_properties.php';

$clearnames = array(
	'abort' => 'Abort installation',
	'intro' => 'Introduction',
	'license' => 'License agreement',
	'msg' => 'Software information',
	'database' => 'Database settings',
	'afterdb' => 'Database installation',
	'userinfo' => 'Userinformation',
	'finish' => 'Finishing installation'
);
// Get the steps:
$steps = array('abort', 'intro');
if (!empty($conf['license_shortdescription']))
	$steps[] = 'license';
if (!empty($conf['install_message']))
	$steps[] = 'msg';
$steps[] = 'database';
$steps[] = 'afterdb';
if ($conf['ask_user_info'])
	$steps[] = 'userinfo';
$steps[] = 'finish';

// Setup session, if not done yet.
if (!isset($_SESSION['installer']))
	$_SESSION['installer'] = array(
		'step' => '1',
		'accept' => '',
		'sql_host' => 'localhost',
		'sql_user' => '',
		'sql_pwd' => '',
		'sql_db' => '',
		'sql_prefix' => '',
		'db_setup_complete' => false,
		'username' => 'admin'
	);

	

// Process the buttons:
if (isset($_POST['abort']))
	$_SESSION['installer']['step'] = 0;

elseif (isset($_POST['back']))
	$_SESSION['installer']['step'] = $_SESSION['installer']['step'] - 1;

elseif (isset($_POST['forward'])) {
	switch ($steps[$_SESSION['installer']['step']]) {
		case 'intro':
			$_SESSION['installer']['step']++;
			break;
		case 'license':
			if (isset($_POST['accept'])) {
				$_SESSION['installer']['step']++;
				$_SESSION['installer']['accept'] = ' checked';
			}
			else
				$error = '<div class="error">You have to agree to the license.</div>';
			break;
		case 'msg':
			$_SESSION['installer']['step']++;
			break;
		case 'database':
			$_SESSION['installer']['sql_host'] = $_POST['host'];
			$_SESSION['installer']['sql_user'] = $_POST['user'];
			$_SESSION['installer']['sql_pwd'] = $_POST['pwd'];
			$_SESSION['installer']['sql_db'] = $_POST['db'];
			$_SESSION['installer']['sql_prefix'] = isset($_POST['prefix']) ? $_POST['prefix'] : '';
			$connection = @mysqli_connect($_POST['host'], $_POST['user'], $_POST['pwd'], $_POST['db']);
			if (!empty(mysqli_connect_errno()))
				$error = '<div class="error">Could not connect to database. '
				. 'Please check your settings.</div>';
			elseif (empty($_POST['prefix']) && !$conf['db_fixed_prefix'])
				$error = '<div class="error">You must set a prefix.</div>';
			else
				$_SESSION['installer']['step']++;
			break;
		case 'afterdb':
			if ($_SESSION['installer']['db_setup_complete'])
				$_SESSION['installer']['step']++;
			break;
		case 'userinfo':
			$_SESSION['installer']['username'] = $_POST['name'];
			$connection = mysqli_connect($_SESSION['installer']['sql_host'],
				$_SESSION['installer']['sql_user'], $_SESSION['installer']['sql_pwd'],
				$_SESSION['installer']['sql_db']);
			if (empty($_POST['name']) || empty($_POST['pwd']))
				$error = '<div class="error">Both fields had to be filled.</div>';
			elseif (strlen($_POST['name']) < 4)
				$error = '<div class="error">The username must be at least 3 characters long</div>';
			elseif (strlen($_POST['pwd']) < 9)
				$error = '<div class="error">The password must consist of at least 8 characters</div>';
			elseif (!setFirstUser($connection, $_POST['name'], $_POST['pwd']))
				$error = '<div class="error">There was an error storing the information.</div>';
			else
				$_SESSION['installer']['step']++;
			break;
	}
}

	
	

// Run the steps:
switch ($steps[$_SESSION['installer']['step']]) {
	case 'intro':
		$button_1 = ' disabled';
		$button_2 = ' disabled';
		$button_3 = '';
		$content = '<h1>Installation</h1>'
		. 'Welcome to the installationmanager for ' . $conf['title'] . '. This installation is '
		. 'devided into ' . (count($steps) - 1) . ' steps.<ol>';
		foreach ($steps as $step) {
			if ($step == 'abort') continue;
			$content .= '<li>' . $clearnames[$step] . '</li>';
		}
		$content .= '</ol>Have fun and success using my software.<br>Jonathan Lusky';
		
		if (!is_writeable('../')) {
			$button_3 = ' disabled';
			$content .= '<p style="color: red; font-weight: bold;">Cannot write config-file. Permissions?</p>';
		}
		
		if (file_exists($conf['config_file_path'])) {
			$button_3 = ' disabled';
			$content .= '<p style="color: red; font-weight: bold;">Program already installed.</p>';
		}
		break;
	case 'license':
		$button_1 = '';
		$button_2 = '';
		$button_3 = '';
		$content = '<h1>License agreements</h1>'
			. (isset($error) ? $error : '')
			. 'Before continuing with the installation, please notice that this product is '
			. 'licensed under the following license:<br><br>'
			. $conf['license_shortdescription']
			. (empty($conf['license_full']) ? '' : '<br><br>Click here for the full license: '
			. '<a href="' . $conf['license_full'] . '">Full License</a>') . '<br><br>'
			. '<input type="checkbox" name="accept" value="1"'
			. $_SESSION['installer']['accept'] . '> I accept the terms of agreement.';
		break;
	case 'msg':
		$button_1 = '';
		$button_2 = '';
		$button_3 = '';
		$content = '<h1>Installation message</h1>' . $conf['install_message'];
		break;
	case 'database':
		$button_1 = '';
		$button_2 = '';
		$button_3 = '';
		$content = '<h1>Database settings</h1>'
		. (isset($error) ? $error : '')
		. '<table border="0"><tr>'
		. '<td>MySQL-Host: </td>'
		. '<td><input type="text" name="host" value="' . $_SESSION['installer']['sql_host'] . '"></td>'
		. '</tr><tr>'
		. '<td>MySQL-Username: </td>'
		. '<td><input type="text" name="user" value="' . $_SESSION['installer']['sql_user'] . '"></td>'
		. '</tr><tr>'
		. '<td>MySQL-Password: </td>'
		. '<td><input type="password" name="pwd" value="' . $_SESSION['installer']['sql_pwd'] . '"></td>'
		. '</tr><tr>'
		. '<td>MySQL-Database: </td>'
		. '<td><input type="text" name="db" value="' . $_SESSION['installer']['sql_db'] . '"></td>'
		. ($conf['db_fixed_prefix'] ? '' : '</tr><tr>'
		. '<td>Table prefix: </td>'
		. '<td><input type="text" name="prefix" value="' . $_SESSION['installer']['sql_prefix'] . '"></td>')
		. '</tr></table>';
		break;
	case 'afterdb':
		$button_1 = '';
		$button_2 = '';
		$button_3 = '';
		$content = '<h1>Database installation</h1><ul>';
		require_once 'queries.php';
		$error = false;
		$connection = mysqli_connect($_SESSION['installer']['sql_host'],
			$_SESSION['installer']['sql_user'], $_SESSION['installer']['sql_pwd'],
			$_SESSION['installer']['sql_db']);
		foreach ($queries as $description => $query) {
			@mysqli_query($connection, $query);
			if (empty(mysqli_errno($connection)))
				$content .= '<li><span style="color: #009000; font-weight: bold;">'
				. $description . ' was successfull</span></li>';
			else {
				$content .= '<li><span style="color: #FF0000; font-weight: bold;">'
				. $description . ' failed</span></li>';
				$error = true;
			}
		}
		$filehandler = @fopen($conf['config_file_path'], 'w+');
		if ($filehandler === false) {
			$content .= '<li><span style="color: #FF0000; font-weight: bold;">'
			. 'Could not open file. Writing permissions?</span></li>';
			$error = true;
		}
		else {
			if (fwrite($filehandler, str_replace(array('DBHOST', 'DBUSER', 'DBPWD', 'DBNAME', 'DBPREFIX'),
				array("'" . $_SESSION['installer']['sql_host'] . "'"
				, "'" . $_SESSION['installer']['sql_user'] . "'"
				, "'" . $_SESSION['installer']['sql_pwd'] . "'"
				, "'" . $_SESSION['installer']['sql_db'] . "'"
				, "'" . $_SESSION['installer']['sql_prefix'] . "'"), file_get_contents('config_draft.txt'))))
				$content .= '<li><span style="color: #009000; font-weight: bold;">'
				. 'Successfully created config-file.</span></li>';
			else {
				$content .= '<li><span style="color: #FF0000; font-weight: bold;">'
				. 'Could not write config file. Writing permissions?</span></li>';
				$error = true;
			}
		}
		$content .= '</ul><a href="">Retry</a>';
		if (!$error)
			$_SESSION['installer']['db_setup_complete'] = true;
		break;
	case 'userinfo':
		$button_1 = '';
		$button_2 = '';
		$button_3 = '';
		$content = '<h1>Userinformation</h1>'
		. (isset($error) ? $error : '')
		. (empty($conf['user_information']) ? '' : '<p>' . $conf['user_information'] . '</p>')
		. '<table border="0"><tr>'
		. '<td>Username: </td>'
		. '<td><input type="text" name="name" value="' . $_SESSION['installer']['username'] . '"></td>'
		. '</tr><tr>'
		. '<td>Passwort: </td>'
		. '<td><input type="password" name="pwd">'
		. '</tr></table>';
		break;
	case 'finish':
		$button_1 = ' disabled';
		$button_2 = ' disabled';
		$button_3 = ' disabled';
		session_destroy();
		$content = '<h1>Installation finished</h1>'
		. 'That was it, the software is installed and ready for use.<br><br>'
		. '<b>Please remove this installation folder (important)</b>. Thanks for using my software.';
		break;
	default:
		$button_1 = ' disabled';
		$button_2 = ' disabled';
		$button_3 = ' disabled';
		session_destroy();
		$content = '<h1>Installation aborted</h1><b>Installation has been aborted</b>';
		break;
}
?>


<!DOCTYPE html>
	
<html>
<head>
	<meta charset="utf-8">
	<title>Installation <?php echo $conf['title']; ?></title>
	<link rel="stylesheet" href="styles.css" type="text/css">
</head>
<body><form action="" method="post"><input type="submit" name="forward" value="1" id="hidden-button"> 
	<div id="main">
		
		<div id="header">
			<div class="right">
				<input type="submit" name="abort" value="Abort installation"<?php echo $button_1 ?>>
			</div>
			Installation of "<span style="color: #007000;"><?php echo $conf['title']; ?></span>"
		</div>
		
		<div class="content"> 
			<?php echo $content; ?>
		</div>
		
		<div id="footer">
			<div class="right">
				<input type="submit" name="back" value="Back"<?php echo $button_2 ?>>&nbsp;
				<input type="submit" name="forward" value="Continue"<?php echo $button_3 ?>>
			</div>
			Step <b><?php echo $_SESSION['installer']['step']; ?><b> / 
			<?php echo (count($steps) - 1) . ' - '
			. $clearnames[$steps[$_SESSION['installer']['step']]]; ?>
		</div>
	</div>
</form></body>
</html>
