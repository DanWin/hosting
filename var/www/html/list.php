<?php
header('Content-Type: text/html; charset=UTF-8');
include_once('../common.php');
try{
	$db=new PDO('mysql:host=' . DBHOST . ';dbname=' . DBNAME, DBUSER, DBPASS, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_WARNING, PDO::ATTR_PERSISTENT=>PERSISTENT]);
}catch(PDOException $e){
	die('No Connection to MySQL database!');
}
echo '<!DOCTYPE html><html><head>';
echo '<title>Daniel\'s Hosting - List of hosted sites</title>';
echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
echo '<meta name=viewport content="width=device-width, initial-scale=1">';
echo '</head><body>';
echo '<p><a href="index.php">Info</a> | <a href="register.php">Register</a> | <a href="login.php">Login</a> | List of hosted sites | <a href="faq.php">FAQ</a></p>';
$stmt=$db->query('SELECT COUNT(*) FROM users WHERE public=1;');
$count=$stmt->fetch(PDO::FETCH_NUM);
$stmt=$db->query('SELECT COUNT(*) FROM users WHERE public=0;');
$hidden=$stmt->fetch(PDO::FETCH_NUM);
echo "<p>Here a list of $count[0] public hosted sites ($hidden[0] sites hidden):</p>";
echo '<table border="1">';
echo '<tr><td>Onion link</td></tr>';
$stmt=$db->query('SELECT username, onion FROM users WHERE public=1 ORDER BY onion;');
while($tmp=$stmt->fetch(PDO::FETCH_NUM)){
	echo "<tr><td><a href=\"http://$tmp[1].onion\" target=\"_blank\">$tmp[1].onion</a></td></tr>";
}
echo '</table>';
echo '</body></html>';
