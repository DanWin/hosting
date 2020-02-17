<?php
require('../common.php');
$db = get_db_instance();
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
print_header('Delete account');
?>
<p>This will delete your account and all data asociated with it. It can't be un-done. Are you sure?</p>
<?php echo $msg; ?>
<form method="POST" action="delete.php"><input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>"><table>
<tr><td>Enter your account password to confirm</td><td><input type="password" name="pass" required autofocus></td></tr>
<tr><td colspan="2"><input type="submit" value="Delete"></td></tr>
</table></form>
<p><a href="home.php">No, don't delete.</a></p>
</body></html>
