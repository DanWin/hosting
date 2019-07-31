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
echo '<meta name="author" content="Daniel Winzen">';
echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
echo '<link rel="stylesheet" href="w3.css">';
echo '<link rel="canonical" href="' . CANONICAL_URL . $_SERVER['SCRIPT_NAME'] . '">';
echo '</head>';
echo '<style>';
echo 'body { background-color: lightblue;}';
echo 'h1 {color: white;text-align: center;}';
echo 'p {font-family: verdana;font-size: 2vw;}';
echo 'btn {font-family: verdana;font-size: 1.5vw;}';
echo '</style>';
echo '<body>';
echo '<div class="w3-container w3-margin-left">';
echo '<div class="w3-container w3-margin-right">';
echo '<div class="w3-container w3-teal">';
echo '<h1>Hosting - List of hosted sites</h1>';
echo '</div>';
echo '<div class="w3-row"><p><a href="index.php" class="w3-third w3-button w3-teal">Home</a><a href="register.php" class="w3-third w3-button w3-teal">Register</a><a href="login.php" class="w3-third w3-button w3-teal">Login</a><a href="list.php" class="w3-third w3-button w3-teal">List of hosted sites</a><a href="faq.php" class="w3-third w3-button w3-teal">FAQ</a></p></div>';
$stmt=$db->query('SELECT COUNT(*) FROM users WHERE public=1;');
$count=$stmt->fetch(PDO::FETCH_NUM);
$stmt=$db->query('SELECT COUNT(*) FROM users WHERE public=0;');
$hidden=$stmt->fetch(PDO::FETCH_NUM);
echo "<p>Here is a list of $count[0] public hosted sites ($hidden[0] sites hidden):</p>";
echo '<li><h1>Onion links</h1></li>';
$stmt=$db->query('SELECT onions.onion FROM users INNER JOIN onions ON (onions.user_id=users.id) WHERE users.public=1 ORDER BY onions.onion;');
while($tmp=$stmt->fetch(PDO::FETCH_NUM)){
echo "<btn><a href=\"http://$tmp[0].onion\" target=\"_blank\" class=w3-btn >$tmp[0].onion</a></btn>";
}
echo '</body></html>';
