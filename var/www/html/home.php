<?php
include('../common.php');
try{
	$db=new PDO('mysql:host=' . DBHOST . ';dbname=' . DBNAME, DBUSER, DBPASS, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_WARNING, PDO::ATTR_PERSISTENT=>PERSISTENT]);
}catch(PDOException $e){
	die('No Connection to MySQL database!');
}
session_start();
$user=check_login();
header('Content-Type: text/html; charset=UTF-8');
echo '<!DOCTYPE html><html><head>';
echo '<title>Daniel\'s Hosting - Dashboard</title>';
echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
echo '<meta name=viewport content="width=device-width, initial-scale=1">';
echo '</head><body>';
echo "<p>Logged in as $user[username] <a href=\"logout.php\">Logout</a> | <a href=\"password.php\">Change passwords</a> | <a target=\"_blank\" href=\"files.php\">FileManager</a> | <a href=\"delete.php\">Delete account</a>
</p>";
$mail=0;
if(file_exists("/home/$user[onion].onion/Maildir/new/")){
	$mail=count(scandir("/home/$user[onion].onion/Maildir/new/"))-2;
}
echo "<p>Enter system account password to check your $user[onion].onion@" . ADDRESS . " mail ($mail new):</td><td><form action=\"squirrelmail/src/redirect.php\" method=\"post\" target=\"_blank\"><input type=\"hidden\" name=\"login_username\" value=\"$user[onion].onion\"><input type=\"password\" name=\"secretkey\"><input type=\"submit\" value=\"Login to webmail\"></form></p>";
echo '<h3>Domain</h3>';
echo '<table border="1">';
echo '<tr><th>Onion</th><th>Private key</th></tr>';
echo "<tr><td><a href=\"http://$user[onion].onion\" target=\"_blank\">$user[onion].onion</a></td><td>";
if(isset($_REQUEST['show_priv'])){
	echo "<pre>$user[private_key]</pre>";
}else{
	echo '<a href="home.php?show_priv=1">Show private key</a>';
}
echo '</td></tr>';
echo '</table>';
echo '<h3>MySQL Database</h3>';
echo '<table border="1">';
echo '<tr><th>Database</th><th>Host</th><th>User</th></tr>';
echo "<tr><td>$user[onion]</td><td>localhost</td><td>$user[onion].onion</td></tr>";
echo '</table>';
echo '<p><a href="password.php?type=sql">Change MySQL password</a></p>';
echo '<p>You can use <a href="/phpmyadmin/" target="_blank">PHPMyAdmin</a> and <a href="/adminer/" target="_blank">Adminer</a> for web based database administration.</p>';
echo '<h3>System Account</h3>';
echo '<table border="1">';
echo '<tr><th>Username</th><th>Host</th><th>FTP Port</th><th>SFTP Port</th><th>POP3 Port</th><th>IMAP Port</th><th>SMTP port</th></tr>';
foreach(SERVERS as $server=>$tmp){
	echo "<tr><td>$user[onion].onion</td><td>$server</td><td>$tmp[ftp]</td><td>$tmp[sftp]</td><td>$tmp[pop3]</td><td>$tmp[imap]</td><td>$tmp[smtp]</td></tr>";
}
echo '</table>';
echo '<p><a href="password.php?type=sys">Change system account password</a></p>';
echo '<p>You can use the <a target="_blank" href="files.php">FileManager</a> for web based file management.</p>';
echo '<h3>Logs</h3>';
echo '<table border="1">';
echo '<tr><th>Date</th><th>access.log</th><th>error.log</th></tr>';
echo '<tr><td>Today</td><td><a href="log.php?type=access&amp;old=0" target="_blank">access.log</log></td><td><a href="log.php?type=error&amp;old=0" target="_blank">error.log</a></td></tr>';
echo '<tr><td>Yesterday</td><td><a href="log.php?type=access&amp;old=1" target="_blank">access.log</log></td><td><a href="log.php?type=error&amp;old=1" target="_blank">error.log</a></td></tr>';
echo '</table>';
echo '</body></html>';
?>
