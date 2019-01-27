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
	if($error=check_csrf_error()){
		$msg.='<p style="color:red;">'.$error.'</p>';
	}elseif(!isset($_POST['pass']) || !password_verify($_POST['pass'], $user['password'])){
		$msg.='<p style="color:red;">Wrong password.</p>';
	}else{
		$stmt=$db->prepare('UPDATE users SET todelete=1 WHERE id=?;');
		$stmt->execute([$user['id']]);
		session_destroy();
		header('Location: login.php');
		exit;
	}
}
header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html><html><head>
<title>Daniel's Hosting - Delete account</title>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<meta name="author" content="Daniel Winzen">
<meta name="viewport" content="width=device-width, initial-scale=1">
</head><body>
<p>This will delete your account and all data asociated with it. It can't be un-done. Are you sure?</p>
<?php echo $msg; ?>
<form method="POST" action="delete.php"><input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>"><table>
<tr><td>Enter your account password to confirm</td><td><input type="password" name="pass" required autofocus></td></tr>
<tr><td colspan="2"><input type="submit" value="Delete"></td></tr>
</table></form>
<p><a href="home.php">No, don't delete.</a></p>
</body></html>
