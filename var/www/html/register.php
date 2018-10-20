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
echo '<!DOCTYPE html><html><head>';
echo '<title>Daniel\'s Hosting - Register</title>';
echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
echo '<meta name="author" content="Daniel Winzen">';
echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
echo '</head><body>';
echo '<h1>Hosting - Register</h1>';
echo '<p><a href="index.php">Info</a> | Register | <a href="login.php">Login</a> | <a href="list.php">List of hosted sites</a> | <a href="faq.php">FAQ</a></p>';
if($_SERVER['REQUEST_METHOD']==='POST'){
	$ok=true;
	$onion='';
	$public=0;
	$php=0;
	$autoindex=0;
	$hash='';
	$priv_key='';
	if($error=check_captcha_error()){
		echo "<p style=\"color:red;\">$error</p>";
		$ok=false;
	}elseif(empty($_POST['pass'])){
		echo '<p style="color:red;">Error: password empty.</p>';
		$ok=false;
	}elseif(empty($_POST['passconfirm']) || $_POST['pass']!==$_POST['passconfirm']){
		echo '<p style="color:red;">Error: password confirmation does not match.</p>';
		$ok=false;
	}elseif(empty($_POST['username'])){
		echo '<p style="color:red;">Error: username empty.</p>';
		$ok=false;
	}elseif(preg_match('/[^a-z0-9\-_\.]/', $_POST['username'])){
		echo '<p style="color:red;">Error: username may only contain characters that are in the rage of a-z (lower case) - . _ and 0-9.</p>';
		$ok=false;
	}elseif(strlen($_POST['username'])>50){
		echo '<p style="color:red;">Error: username may not be longer than 50 characters.</p>';
		$ok=false;
	}else{
		$stmt=$db->prepare('SELECT null FROM users WHERE username=?;');
		$stmt->execute([$_POST['username']]);
		if($stmt->fetch(PDO::FETCH_NUM)){
			echo '<p style="color:red;">Error: this username is already registered.</p>';
			$ok=false;
		}
	}
	if($ok){
		$check=$db->prepare('SELECT null FROM onions WHERE onion=?;');
		if(isset($_REQUEST['private_key']) && !empty(trim($_REQUEST['private_key']))){
			$priv_key=trim($_REQUEST['private_key']);
			if(($pkey=openssl_pkey_get_private($priv_key))!==false){
				$details=openssl_pkey_get_details($pkey);
				if($details['bits']!==1024){
					echo '<p style="color:red;">Error: private key not of bitsize 1024.</p>';
					$ok=false;
				}else{
					$onion=get_onion($pkey);
					$check->execute([$onion]);
					if($check->fetch(PDO::FETCH_NUM)){
						echo '<p style="color:red;">Error onion already exists.</p>';
						$ok=false;
					}
				}
				openssl_pkey_free($pkey);
			}else{
				echo '<p style="color:red;">Error: private key invalid.</p>';
				$ok=false;
			}
		}else{
			do{
				$pkey=openssl_pkey_new(['private_key_bits'=>1024, 'private_key_type'=>OPENSSL_KEYTYPE_RSA]);
				openssl_pkey_export($pkey, $priv_key);
				$onion=get_onion($pkey);
				openssl_pkey_free($pkey);
				$check->execute([$onion]);
			}while($check->fetch(PDO::FETCH_NUM));
		}
		if(isset($_POST['public']) && $_POST['public']==1){
			$public=1;
		}
		if(isset($_POST['php']) && in_array($_POST['php'], [1, 2, 3])){
			$php=$_POST['php'];
		}
		if(isset($_POST['autoindex']) && $_POST['autoindex']==1){
			$autoindex=1;
		}
		$priv_key=trim(str_replace("\r", '', $priv_key));
		$hash=password_hash($_POST['pass'], PASSWORD_DEFAULT);
	}
	$check=$db->prepare('SELECT null FROM users WHERE dateadded>?;');
	$check->execute([time()-60]);
	if($ok && $check->fetch(PDO::FETCH_NUM)){
		echo '<p style="color:red;">To prevent abuse a site can only be registered every 60 seconds, but one has already been registered within the last 60 seconds. Please try again.</p>';
		$ok=false;
	}elseif($ok){
		$stmt=$db->prepare('INSERT INTO users (username, system_account, password, dateadded, public, php, autoindex, mysql_user) VALUES (?, ?, ?, ?, ?, ?, ?, ?);');
		$stmt->execute([$_POST['username'], "$onion.onion", $hash, time(), $public, $php, $autoindex, "$onion.onion"]);
		$stmt=$db->prepare('SELECT id FROM users WHERE username=?;');
		$stmt->execute([$_POST['username']]);
		$user_id=$stmt->fetch(PDO::FETCH_NUM)[0];
		$stmt=$db->prepare('INSERT INTO mysql_databases (user_id, mysql_database) VALUES (?, ?);');
		$stmt->execute([$user_id, $onion]);
		$stmt=$db->prepare('INSERT INTO onions (user_id, onion, private_key, version) VALUES (?, ?, ?, ?);');
		$stmt->execute([$user_id, $onion, $priv_key, 2]);
		$create_user=$db->prepare("CREATE USER '$onion.onion'@'%' IDENTIFIED BY ?;");
		$create_user->execute([$_POST['pass']]);
		$db->exec("CREATE DATABASE IF NOT EXISTS `$onion`;");
		$db->exec("GRANT ALL PRIVILEGES ON `$onion`.* TO '$onion.onion'@'%';");
		$db->exec('FLUSH PRIVILEGES;');
		$stmt=$db->prepare('INSERT INTO new_account (user_id, password) VALUES (?, ?);');
		$stmt->execute([$user_id, get_system_hash($_POST['pass'])]);
		if(EMAIL_TO!==''){
			$title="A new hidden service $onion has been created";
			$msg="A new hidden service http://$onion.onion has been created";
			$headers="From: www-data <www-data>\r\nContent-Type: text/plain; charset=UTF-8\r\n";
			mail(EMAIL_TO, $title, $msg, $headers);
		}
		echo "<p style=\"color:green;\">Your onion domain <a href=\"http://$onion.onion\" target=\"_blank\">$onion.onion</a> has successfully been created. Please wait up to one minute until the changes have been processed. You can then login <a href=\"login.php\">here</a>.</p>";
	}
}
echo '<form method="POST" action="register.php"><table>';
echo '<tr><td>Username</td><td><input type="text" name="username" value="';
if(isset($_POST['username'])){
	echo htmlspecialchars($_POST['username']);
}
echo '" required autofocus></td></tr>';
echo '<tr><td>Password</td><td><input type="password" name="pass" required></td></tr>';
echo '<tr><td>Confirm password</td><td><input type="password" name="passconfirm" required></td></tr>';
send_captcha();
if($_SERVER['REQUEST_METHOD']!=='POST' || (isset($_POST['public']) && $_POST['public']==1)){
	$public=' checked';
}else{
	$public='';
}
if(isset($_POST['autoindex']) && $_POST['autoindex']==1){
	$autoindex=' checked';
}else{
	$autoindex='';
}
$nophp='';
$php70='';
$php71='';
$php72='';
if(isset($_POST['php']) && $_POST['php']==0){
	$nophp=' selected';
}elseif(isset($_POST['php']) && $_POST['php']==1){
	$php70=' selected';
}elseif(isset($_POST['php']) && $_POST['php']==2){
	$php71=' selected';
}else{
	$php72=' selected';
}
echo '<tr><td>PHP version</td><td><select name="php"><option value="0"'.$nophp.'>None</option><option value="1" '.$php70.'>PHP 7.0</option><option value="2"'.$php71.'>PHP 7.1</option><option value="3"'.$php72.'>PHP 7.2</option></select></td></tr>';
echo '<tr><td colspan=2><label><input type="checkbox" name="public" value="1"'.$public.'>Publish site on list of hosted sites</label></td></tr>';
echo '<tr><td colspan=2><label><input type="checkbox" name="autoindex" value="1"'.$autoindex.'>Enable autoindex (listing of files)</label></td></tr>';
echo '<tr><td>Custom private key<br>(optional)</td><td><textarea name="private_key" rows="5" cols="28">';
if(isset($_REQUEST['private_key'])){
	echo htmlspecialchars($_REQUEST['private_key']);
}
echo '</textarea></td></tr>';
echo '<tr><td colspan="2"><label><input type="checkbox" name="accept_privacy" required>I have read and agreed to the <a href="/privacy.php" target="_blank">Privacy Policy</a></label><br></td></tr>';
echo '<tr><td colspan="2"><input type="submit" value="Register"></td></tr>';
echo '</table></form>';
echo '</body></html>';
