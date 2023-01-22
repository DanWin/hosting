<?php
require('../common.php');
$db = get_db_instance();
$user=check_login();
if(!isset($_REQUEST['type'])){
	$_REQUEST['type']='acc';
}
$msg='';
if($_SERVER['REQUEST_METHOD']==='POST'){
	if($error=check_csrf_error()){
		$msg.='<p role="alert" style="color:red">'.$error.'</p>';
	}
	if(!isset($_POST['pass']) || !password_verify($_POST['pass'], $user['password'])){
		$msg.='<p role="alert" style="color:red">'._('Wrong password!').'</p>';
	}elseif(!isset($_POST['confirm']) || !isset($_POST['newpass']) || $_POST['newpass']!==$_POST['confirm']){
		$msg.='<p role="alert" style="color:red">'._('Wrong password!').'</p>';
	}else{
		if($_REQUEST['type']==='acc'){
			$hash=password_hash($_POST['newpass'], PASSWORD_DEFAULT);
			$stmt=$db->prepare('UPDATE users SET password=? WHERE id=?;');
			$stmt->execute([$hash, $user['id']]);
			$msg.='<p role="alert" style="color:green">'._('Successfully changed account password.').'</p>';
		}elseif($_REQUEST['type']==='sys'){
			$stmt=$db->prepare('INSERT INTO pass_change (user_id, password) VALUES (?, ?);');
			$hash=get_system_hash($_POST['newpass']);
			$stmt->execute([$user['id'], $hash]);
			$msg.='<p role="alert" style="color:green">'._('Successfully changed system account password, change will take effect within the next minute.').'</p>';
		}elseif($_REQUEST['type']==='sql'){
			$stmt=$db->prepare("SET PASSWORD FOR '$user[mysql_user]'@'%'=PASSWORD(?);");
			$stmt->execute([$_POST['newpass']]);
			$db->exec('FLUSH PRIVILEGES;');
			$msg.='<p role="alert" style="color:green">'._('Successfully changed sql password.').'</p>';
		}else{
			$msg.='<p role="alert" style="color:red">'._("Couldn't update password: Unknown reset type.").'</p>';
		}
	}
}
header('Content-Type: text/html; charset=UTF-8');
print_header(_('Change password'));
echo $msg;
echo '<form method="POST" action="password.php"><input type="hidden" name="csrf_token" value="'.$_SESSION['csrf_token'].'"><table>';
echo '<tr><td>'._('Reset type:').'</td><td><select name="type">';
echo '<option value="acc"';
if($_REQUEST['type']==='acc'){
	echo ' selected';
}
echo '>'._('Account').'</option>';
echo '<option value="sys"';
if($_REQUEST['type']==='sys'){
	echo ' selected';
}
echo '>'._('System account').'</option>';
echo '<option value="sql"';
if($_REQUEST['type']==='sql'){
	echo ' selected';
}
echo '>'._('MySQL').'</option>';
echo '</select></td></tr>';
echo '<tr><td>'._('Account password:').'</td><td><input type="password" name="pass" required autofocus></td></tr>';
echo '<tr><td>'._('New password:').'</td><td><input type="password" name="newpass" required></td></tr>';
echo '<tr><td>'._('Confirm password:').'</td><td><input type="password" name="confirm" required></td></tr>';
echo '<tr><td colspan="2"><button type="submit">'._('Reset').'</button></td></tr>';
echo '</table></form>';
echo '<p><a href="home.php">'._('Go back to dashboard').'</a></p>';
echo '</body></html>';
