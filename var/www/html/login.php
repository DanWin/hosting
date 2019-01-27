<?php
include('../common.php');
try{
	$db=new PDO('mysql:host=' . DBHOST . ';dbname=' . DBNAME, DBUSER, DBPASS, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_WARNING, PDO::ATTR_PERSISTENT=>PERSISTENT]);
}catch(PDOException $e){
	die('No Connection to MySQL database!');
}
header('Content-Type: text/html; charset=UTF-8');
session_start();
if(!empty($_SESSION['hosting_username'])){
	header('Location: home.php');
	exit;
}
$msg='';
$username='';
if($_SERVER['REQUEST_METHOD']==='POST'){
	$ok=true;
	if($error=check_captcha_error()){
		$msg.="<p style=\"color:red;\">$error</p>";
		$ok=false;
	}elseif(!isset($_POST['username']) || $_POST['username']===''){
		$msg.='<p style="color:red;">Error: username may not be empty.</p>';
		$ok=false;
	}else{
		$stmt=$db->prepare('SELECT username, password, id FROM users WHERE username=?;');
		$stmt->execute([$_POST['username']]);
		$tmp=[];
		if(($tmp=$stmt->fetch(PDO::FETCH_NUM))===false && preg_match('/^([2-7a-z]{16}).onion$/', $_POST['username'], $match)){
			$stmt=$db->prepare('SELECT users.username, users.password, users.id FROM users INNER JOIN onions ON (onions.user_id=users.id) WHERE onions.onion=?;');
			$stmt->execute([$match[1]]);
			$tmp=$stmt->fetch(PDO::FETCH_NUM);
		}
		if($tmp){
			$username=$tmp[0];
			$password=$tmp[1];
			$stmt=$db->prepare('SELECT new_account.approved FROM new_account INNER JOIN users ON (users.id=new_account.user_id) WHERE users.id=?;');
			$stmt->execute([$tmp[2]]);
			if($tmp=$stmt->fetch(PDO::FETCH_NUM)){
				if(REQUIRE_APPROVAL && !$tmp[0]){
					$msg.='<p style="color:red;">Error: Your account is pending admin approval. Please try again later.</p>';
				}else{
					$msg.='<p style="color:red;">Error: Your account is pending creation. Please try again in a minute.</p>';
				}
				$ok=false;
			}elseif(!isset($_POST['pass']) || !password_verify($_POST['pass'], $password)){
				$msg.='<p style="color:red;">Error: wrong password.</p>';
				$ok=false;
			}
		}else{
			$msg.='<p style="color:red;">Error: username was not found. If you forgot it, you can enter youraccount.onion instead.</p>';
			$ok=false;
		}
	}
	if($ok){
		$_SESSION['hosting_username']=$username;
		$_SESSION['csrf_token']=sha1(uniqid());
		session_write_close();
		header('Location: home.php');
		exit;
	}
}
echo '<!DOCTYPE html><html><head>';
echo '<title>Daniel\'s Hosting - Login</title>';
echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
echo '<meta name="author" content="Daniel Winzen">';
echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
echo '</head><body>';
echo '<h1>Hosting - Login</h1>';
echo '<p><a href="index.php">Info</a> | <a href="register.php">Register</a> | Login | <a href="list.php">List of hosted sites</a> | <a href="faq.php">FAQ</a></p>';
echo $msg;
echo '<form method="POST" action="login.php"><table>';
echo '<tr><td>Username</td><td><input type="text" name="username" value="';
if(isset($_POST['username'])){
	echo htmlspecialchars($_POST['username']);
}
echo '" required autofocus></td></tr>';
echo '<tr><td>Password</td><td><input type="password" name="pass" required></td></tr>';
send_captcha();
echo '<tr><td colspan="2"><input type="submit" value="Login"></td></tr>';
echo '</table></form>';
echo '<p>If you disabled cookies, please re-enable them. You can\'t log in without!</p>';
echo '</body></html>';
