<?php
include('../common.php');
try{
	$db=new PDO('mysql:host=' . DBHOST . ';dbname=' . DBNAME, DBUSER, DBPASS, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_WARNING, PDO::ATTR_PERSISTENT=>PERSISTENT]);
}catch(PDOException $e){
	die('No Connection to MySQL database!');
}
session_start();
$user=check_login();
$msg='';
if($_SERVER['REQUEST_METHOD']==='POST'){
	if(!isset($_POST['pass']) || !password_verify($_POST['pass'], $user['password'])){
		$msg.='<p style="color:red;">Wrong password.</p>';
	}else{
		$stmt=$db->prepare('INSERT INTO del_account (onion) VALUES (?);');
		$stmt->execute([$user['onion']]);
		session_destroy();
		header('Location: login.php');
		exit;
	}
}
header('Content-Type: text/html; charset=UTF-8');
echo '<!DOCTYPE html><html><head>';
echo '<title>Daniel\'s Hosting - Delete account</title>';
echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
echo '<meta name=viewport content="width=device-width, initial-scale=1">';
echo '</head><body>';
echo '<p>This will delete your account and all data asociated with it. It can\'t be un-done. Are you sure?</p>';
echo $msg;
echo '<form method="POST" action="delete.php"><table>';
echo '<tr><td>Enter your account password to confirm</td><td><input type="password" name="pass" required autofocus></td></tr>';
echo '<tr><td colspan="2"><input type="submit" value="Delete"></td></tr>';
echo '</table></form>';
echo '<p><a href="home.php">No, don\'t delete.</a></p>';
echo '</body></html>';
?>
