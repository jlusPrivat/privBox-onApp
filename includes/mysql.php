<?php
$mysql = array(
	"host" => 'localhost',
	"user" => 'testuser',
	"pwd" => 'testaa',
	"db" => 'test'
);

// And connect to the DB:
$CONNECTION = mysqli_connect($mysql["host"], $mysql["user"], $mysql["pwd"], $mysql["db"])
OR die("Verbindung gescheitert!");

// Reset the Data, so nobody else can get access!
$mysql = array();


// Download the whole System
$SYSTEM = mysqli_fetch_array(mysqli_query($CONNECTION, "SELECT * FROM pb_system WHERE id = 1 LIMIT 1"), MYSQLI_ASSOC);
?>
