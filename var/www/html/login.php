<?php
require('../common.php');
header('Content-Type: text/html; charset=UTF-8');
session_start();
if(!empty($_SESSION['hosting_username']) && empty($_SESSION['2fa_code'])){
	header('Location: home.php');
	exit;
}
$msg='';
$username='';
$pgp_key='';
$tfa=0;
if(!empty($_SESSION['hosting_username'])){
	$tfa = $_SESSION['tfa'];
	$pgp_key = $_SESSION['pgp_key'];
}
if($_SERVER['REQUEST_METHOD']==='POST'){
	if(!empty($_SESSION['hosting_username'])){
		if(!empty($_POST['2fa_code']) && $_POST['2fa_code'] === $_SESSION['2fa_code']){
			unset($_SESSION['2fa_code']);
			unset($_SESSION['pgp_key']);
			unset($_SESSION['tfa']);
			session_write_close();
			header('Location: home.php');
			exit;
		}else{
			$msg.='<p style="color:red">Wrong 2FA code</p>';
		}
	} else {
		$db = get_db_instance();
		$ok=true;
		if($error=check_captcha_error()){
			$msg.="<p style=\"color:red;\">$error</p>";
			$ok=false;
		}elseif(!isset($_POST['username']) || $_POST['username']===''){
			$msg.='<p style="color:red;">Error: username may not be empty.</p>';
			$ok=false;
		}else{
			$stmt=$db->prepare('SELECT username, password, id, tfa, pgp_key FROM users WHERE username=?;');
			$stmt->execute([$_POST['username']]);
			$tmp=[];
			if(($tmp=$stmt->fetch(PDO::FETCH_ASSOC))===false && preg_match('/^([2-7a-z]{16}).onion$/', $_POST['username'], $match)){
				$stmt=$db->prepare('SELECT users.username, users.password, users.id, users.tfa, users.pgp_key FROM users INNER JOIN onions ON (onions.user_id=users.id) WHERE onions.onion=?;');
				$stmt->execute([$match[1]]);
				$tmp=$stmt->fetch(PDO::FETCH_ASSOC);
			}
			if($tmp){
				$username=$tmp['username'];
				$password=$tmp['password'];
				$tfa=$tmp['tfa'];
				$pgp_key=$tmp['pgp_key'];
				$stmt=$db->prepare('SELECT new_account.approved FROM new_account INNER JOIN users ON (users.id=new_account.user_id) WHERE users.id=?;');
				$stmt->execute([$tmp['id']]);
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
			if($tfa){
				$code = bin2hex(random_bytes(3));
				$_SESSION['2fa_code'] = $code;
				$_SESSION['pgp_key'] = $pgp_key;
				$_SESSION['tfa'] = $tfa;
			} else {
				session_write_close();
				header('Location: home.php');
				exit;
			}
		}
	}
}
print_header('Login');
if($tfa){
	$gpg = gnupg_init();
	gnupg_seterrormode($gpg, GNUPG_ERROR_WARNING);
	gnupg_setarmor($gpg, 1);
	$imported_key = gnupg_import($gpg, $pgp_key);
	if($imported_key){
		$key_info = gnupg_keyinfo($gpg, $imported_key['fingerprint']);
		foreach($key_info as $key){
			if($key['can_encrypt']){
				foreach($key['subkeys'] as $subkey){
					gnupg_addencryptkey($gpg, $subkey['fingerprint']);
				}
			}
		}
		$encrypted = gnupg_encrypt($gpg, "To login, please enter the following code to confirm ownership of your key:\n\n".$_SESSION['2fa_code']."\n");
		echo $msg;
		echo "<p>To login, please decrypt the following PGP encrypted message and confirm the code:</p>";
		echo "<textarea readonly=\"readonly\" onclick=\"this.select()\" rows=\"10\" cols=\"70\">$encrypted</textarea>";
		?>
		<form action="login.php" method="post"><input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
		<table border="1">
			<tr><td><input type="text" name="2fa_code"></td><td><button type="submit">Confirm</button></td></tr>
		</table></form>
		<p>Don't have the private key at hand? <a href="logout.php">Logout</a></p>
		</body></html>
<?php
		exit;
	}
}
?>
<h1>Hosting - Login</h1>
<?php
main_menu('login.php');
echo $msg;
?>
<form method="POST" action="login.php"><table>
<tr><td>Username</td><td><input type="text" name="username" value="<?php
if(isset($_POST['username'])){
	echo htmlspecialchars($_POST['username']);
}
?>" required autofocus></td></tr>
<tr><td>Password</td><td><input type="password" name="pass" required></td></tr>
<?php send_captcha(); ?>
<tr><td colspan="2"><input type="submit" value="Login"></td></tr>
</table></form>
<p>If you disabled cookies, please re-enable them. You can't log in without!</p>
</body></html>
