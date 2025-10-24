<?php
require('../common.php');
header('Content-Type: text/html; charset=UTF-8');
session_start();
if(!empty($_SESSION['hosting_username'])){
	header('Location: home.php');
	exit;
}
print_header(_('Register'), '#custom_onion:not(checked)+#private_key{display:none;}#custom_onion:checked+#private_key{display:block;}');
?>
<h1><?php echo _('Hosting - Register'); ?></h1>
<?php
main_menu('register.php');
if($_SERVER['REQUEST_METHOD']==='POST'){
	$db = get_db_instance();
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
		echo '<p role="alert" style="color:red">'.$error.'</p>';
		$ok=false;
	}elseif(empty($_POST['pass'])){
		echo '<p role="alert" style="color:red">'._('Error: password empty.').'</p>';
		$ok=false;
	}elseif(empty($_POST['passconfirm']) || $_POST['pass']!==$_POST['passconfirm']){
		echo '<p role="alert" style="color:red">'._('Error: password confirmation does not match.').'</p>';
		$ok=false;
	}elseif(empty($_POST['username'])){
		echo '<p role="alert" style="color:red">'._('Error: username empty.').'</p>';
		$ok=false;
	}elseif(preg_match('/[^a-z0-9\-_.]/', $_POST['username'])){
		echo '<p role="alert" style="color:red">'._('Error: username may only contain characters that are in the rage of a-z (lower case) - . _ and 0-9.').'</p>';
		$ok=false;
	}elseif(strlen($_POST['username'])>50){
		echo '<p role="alert" style="color:red">'._('Error: username may not be longer than 50 characters.').'</p>';
		$ok=false;
	}else{
		$stmt=$db->prepare('SELECT null FROM users WHERE username=?;');
		$stmt->execute([$_POST['username']]);
		if($stmt->fetch(PDO::FETCH_NUM)){
			echo '<p role="alert" style="color:red">'._('Error: this username is already registered.').'</p>';
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
				echo '<p role="alert" style="color:red">'.$data['message'].'</p>';
				$ok = false;
			} else {
				$check=$db->prepare('SELECT null FROM onions WHERE onion=?;');
				$check->execute([$onion]);
				if($check->fetch(PDO::FETCH_NUM)){
					echo '<p role="alert" style="color:red">'._('Error onion already exists.').'</p>';
					$ok = false;
				}
			}
		}else{
			if(isset($_REQUEST['onion_type']) && in_array($_REQUEST['onion_type'], [3])){
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
		echo '<p role="alert" style="color:red">'._('To prevent abuse a site can only be registered every 60 seconds, but one has already been registered within the last 60 seconds. Please try again.').'</p>';
		$ok=false;
	}elseif($ok){
		$mysql_user = add_mysql_user($_POST['pass']);
		$stmt=$db->prepare('INSERT INTO users (username, system_account, password, dateadded, public, php, autoindex, mysql_user, instance) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?);');
		$stmt->execute([$_POST['username'], substr("$onion.onion", 0, 32), $hash, time(), $public_list, $php, $autoindex, $mysql_user, get_new_tor_instance('system')]);
		$user_id = $db->lastInsertId();
		$stmt = $db->prepare('INSERT INTO disk_quota (user_id, quota_size, quota_files) VALUES (?, ?, ?);');
		$stmt->execute([$user_id, DEFAULT_QUOTA_SIZE, DEFAULT_QUOTA_FILES]);
		add_user_onion($user_id, $onion, $priv_key, $onion_version);
		add_user_db($user_id);
		$stmt=$db->prepare('INSERT INTO new_account (user_id, password) VALUES (?, ?);');
		$stmt->execute([$user_id, get_system_hash($_POST['pass'])]);
		if(EMAIL_TO!==''){
			$title="A new hidden service $onion has been created";
			$msg="A new hidden service http://$onion.onion has been created";
			$headers="From: www-data <www-data>\r\nContent-Type: text/plain; charset=UTF-8\r\n";
			mail(EMAIL_TO, $title, $msg, $headers);
		}
		echo '<p role="alert" style="color:green">'.sprintf(_('Your onion domain %s has successfully been created. Please wait up to one minute until the changes have been processed. You can then <a href="login.php">login</a>.'), "<a href=\"http://$onion.onion\" target=\"_blank\">$onion.onion</a>").'</p>';
	}
}
?>
<form method="POST" action="register.php"><table>
<tr><td><?php echo _('Username'); ?></td><td><input type="text" name="username" value="<?php
echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '';
?>" required autofocus></td></tr>
<tr><td><?php echo _('Password'); ?></td><td><input type="password" name="pass" required></td></tr>
<tr><td><?php echo _('Confirm password'); ?></td><td><input type="password" name="passconfirm" required></td></tr>
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
<tr><td><?php echo _('PHP version'); ?></td><td><select name="php">
<option value="0"><?php echo _('None'); ?></option>
<?php
foreach(PHP_VERSIONS as $key => $version){
	echo "<option value=\"$key\"";
	echo ((isset($_POST['php']) && $_POST['php']==$key) || (!isset($_POST['php']) && $version===DEFAULT_PHP_VERSION)) ? ' selected' : '';
	echo ">PHP $version</option>";
}
?>
</select></td></tr>
<tr><td colspan=2><label><input type="checkbox" name="public" value="1"<?php echo $public_list; ?>><?php echo _('Publish site on list of hosted sites'); ?></label></td></tr>
<tr><td colspan=2><label><input type="checkbox" name="autoindex" value="1"<?php echo $autoindex; ?>><?php echo _('Enable autoindex (listing of files)'); ?></label></td></tr>
<tr><td colspan=2><?php echo _('Type of hidden service:'); ?><br>
<label><input type="radio" name="onion_type" value="3"<?php echo (!isset($_POST['onion_type']) || $_POST['onion_type']==3) ? ' checked' : ''; ?>><?php echo _('Random v3 Address'); ?></label>
<label><input id="custom_onion" type="radio" name="onion_type" value="custom"<?php echo isset($_POST['onion_type']) && $_POST['onion_type']==='custom' ? ' checked' : ''; ?>><?php echo _('Custom private key'); ?>
<textarea id="private_key" name="private_key" rows="5" cols="28">
<?php echo isset($_REQUEST['private_key']) ? htmlspecialchars($_REQUEST['private_key']) : ''; ?>
</textarea>
</label></td></tr>
<tr><td colspan="2"><label><input type="checkbox" name="accept_privacy" required><?php printf(_('I have read and agreed to the <a href="%s" target="_blank">Privacy Policy</a>'), PRIVACY_URL); ?></label><br></td></tr>
<tr><td colspan="2"><button type="submit"><?php echo _('Register'); ?></button></td></tr>
</table></form>
</body></html>
