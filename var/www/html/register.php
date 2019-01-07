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
?>
<!DOCTYPE html><html><head>
<title>Daniel's Hosting - Register</title>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<meta name="author" content="Daniel Winzen">
<meta name="viewport" content="width=device-width, initial-scale=1">
<style type="text/css">#custom_onion:not(checked)+#private_key{display:none;}#custom_onion:checked+#private_key{display:block;}</style>
</head><body>
<h1>Hosting - Register</h1>
<p><a href="index.php">Info</a> | Register | <a href="login.php">Login</a> | <a href="list.php">List of hosted sites</a> | <a href="faq.php">FAQ</a></p>
<?php
if($_SERVER['REQUEST_METHOD']==='POST'){
	$ok=true;
	$onion='';
	$onion_version=3;
	$public_list=0;
	$php=0;
	$autoindex=0;
	$hash='';
	$priv_key='';
	if(isset($_POST['public']) && $_POST['public']==1){
		$public_list=1;
	}
	if(isset($_POST['php']) && array_key_exists($_POST['php'], PHP_VERSIONS)){
		$php = $_POST['php'];
	}
	if(isset($_POST['autoindex']) && $_POST['autoindex']==1){
		$autoindex=1;
	}
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
		if(isset($_REQUEST['onion_type']) && $_REQUEST['onion_type']==='custom' && isset($_REQUEST['private_key']) && !empty(trim($_REQUEST['private_key']))){
			$priv_key = trim($_REQUEST['private_key']);
			$data = private_key_to_onion($priv_key);
			$onion = $data['onion'];
			$onion_version = $data['version'];
			if(!$data['ok']){
				echo "<p style=\"color:red;\">$data[message]</p>";
				$ok = false;
			} else {
				$check=$db->prepare('SELECT null FROM onions WHERE onion=?;');
				$check->execute([$onion]);
				if($check->fetch(PDO::FETCH_NUM)){
					echo '<p style="color:red;">Error onion already exists.</p>';
					$ok = false;
				}
			}
		}else{
			if(isset($_REQUEST['onion_type']) && in_array($_REQUEST['onion_type'], [2, 3])){
				$onion_version = $_REQUEST['onion_type'];
			}
			$check=$db->prepare('SELECT null FROM onions WHERE onion=?;');
			do{
				$data = generate_new_onion($onion_version);
				$priv_key = $data['priv_key'];
				$onion = $data['onion'];
				$onion_version = $data['version'];
				$check->execute([$onion]);
			}while($check->fetch(PDO::FETCH_NUM));
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
		$mysql_user = add_mysql_user($db, $_POST['pass']);
		$stmt=$db->prepare('INSERT INTO users (username, system_account, password, dateadded, public, php, autoindex, mysql_user) VALUES (?, ?, ?, ?, ?, ?, ?, ?);');
		$stmt->execute([$_POST['username'], substr("$onion.onion", 0, 32), $hash, time(), $public_list, $php, $autoindex, $mysql_user]);
		$user_id = $db->lastInsertId();
		$stmt=$db->prepare('INSERT INTO onions (user_id, onion, private_key, version) VALUES (?, ?, ?, ?);');
		$stmt->execute([$user_id, $onion, $priv_key, $onion_version]);
		add_user_db($db, $user_id);
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
?>
<form method="POST" action="register.php"><table>
<tr><td>Username</td><td><input type="text" name="username" value="<?php
echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '';
?>" required autofocus></td></tr>
<tr><td>Password</td><td><input type="password" name="pass" required></td></tr>
<tr><td>Confirm password</td><td><input type="password" name="passconfirm" required></td></tr>
<?php
send_captcha();
if($_SERVER['REQUEST_METHOD']!=='POST' || (isset($public_list) && $public_list==1)){
	$public_list=' checked';
}else{
	$public_list='';
}
if(isset($autoindex) && $autoindex==1){
	$autoindex=' checked';
}else{
	$autoindex='';
}
?>
<tr><td>PHP version</td><td><select name="php">
<option value="0">None</option>
<?php
foreach(PHP_VERSIONS as $key => $version){
	echo "<option value=\"$key\"";
	echo ((isset($_POST['php']) && $_POST['php']==$key) || (!isset($_POST['php']) && $version===DEFAULT_PHP_VERSION)) ? ' selected' : '';
	echo ">PHP $version</option>";
}
?>
</select></td></tr>
<tr><td colspan=2><label><input type="checkbox" name="public" value="1"<?php echo $public_list; ?>>Publish site on list of hosted sites</label></td></tr>
<tr><td colspan=2><label><input type="checkbox" name="autoindex" value="1"<?php echo $autoindex; ?>>Enable autoindex (listing of files)</label></td></tr>
<tr><td colspan=2>Type of hidden service:<br>
<label><input type="radio" name="onion_type" value="3"<?php echo (!isset($_POST['onion_type']) || isset($_POST['onion_type']) && $_POST['onion_type']==3) ? ' checked' : ''; ?>>Random v3 Address</label>
<label><input type="radio" name="onion_type" value="2"<?php echo isset($_POST['onion_type']) && $_POST['onion_type']==2 ? ' checked' : ''; ?>>Random v2 Address</label>
<label><input id="custom_onion" type="radio" name="onion_type" value="custom"<?php echo isset($_POST['onion_type']) && $_POST['onion_type']==='custom' ? ' checked' : ''; ?>>Custom private key
<textarea id="private_key" name="private_key" rows="5" cols="28">
<?php echo isset($_REQUEST['private_key']) ? htmlspecialchars($_REQUEST['private_key']) : ''; ?>
</textarea>
</label></td></tr>
<tr><td colspan="2"><label><input type="checkbox" name="accept_privacy" required>I have read and agreed to the <a href="https://danwin1210.me/privacy.php" target="_blank">Privacy Policy</a></label><br></td></tr>
<tr><td colspan="2"><input type="submit" value="Register"></td></tr>
</table></form>
</body></html>
