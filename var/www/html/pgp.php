<?php
require('../common.php');
$user=check_login();
print_header('PGP 2FA');
dashboard_menu($user, 'pgp.php');
if($_SERVER['REQUEST_METHOD']==='POST'){
	if($error=check_csrf_error()){
		die($error);
	}
	if(isset($_POST['pgp_key'])){
		$pgp_key = trim($_POST['pgp_key']);
		$gpg = gnupg_init();
		gnupg_seterrormode($gpg, GNUPG_ERROR_WARNING);
		gnupg_setarmor($gpg, 1);
		$imported_key = gnupg_import($gpg, $pgp_key);
		if(!$imported_key){
			echo "<p style=\"color:red\">There was an error importing the key</p>";
		}else{
			$db = get_db_instance();
			$stmt = $db->prepare('UPDATE users SET pgp_key = ?, tfa = 0, pgp_verified = 0 WHERE id = ?;');
			$stmt->execute([$pgp_key, $user['id']]);
			$user['pgp_key'] = $pgp_key;
		}
	}
	if(isset($_POST['enable_2fa_code'])){
		if($_POST['enable_2fa_code'] !== $_SESSION['enable_2fa_code']){
			echo "<p style=\"color:red\">Sorry, the code was incorrect</p>";
		} else {
			$db = get_db_instance();
			$stmt = $db->prepare('UPDATE users SET tfa = 1, pgp_verified = 1 WHERE id = ?;');
			$stmt->execute([$user['id']]);
			$user['tfa'] = 1;
		}
	}
}
if(!empty($user['pgp_key'])){
	if($user['tfa'] == '1'){
		echo "<p style=\"color:green\">Yay, PGP based 2FA is enabled!</p>";
	} else {
		$gpg = gnupg_init();
		gnupg_seterrormode($gpg, GNUPG_ERROR_WARNING);
		gnupg_setarmor($gpg, 1);
		$imported_key = gnupg_import($gpg, $user['pgp_key']);
		if($imported_key){
			$key_info = gnupg_keyinfo($gpg, $imported_key['fingerprint']);
			foreach($key_info as $key){
				if(!$key['can_encrypt']){
					echo "<p>Sorry, this key can't be used to encrypt a message to you. Your key may have expired or has been revoked.</p>";
				}else{
					foreach($key['subkeys'] as $subkey){
						gnupg_addencryptkey($gpg, $subkey['fingerprint']);
					}
				}
			}
			$_SESSION['enable_2fa_code'] = bin2hex(random_bytes(3));
			if($encrypted = gnupg_encrypt($gpg, "To enable 2FA, please enter the following code to confirm ownership of your key:\n\n$_SESSION[enable_2fa_code]\n")){
				echo "<p>To enable 2FA using your PGP key, please decrypt the following PGP encrypted message and confirm the code:</p>";
				echo "<textarea readonly=\"readonly\" onclick=\"this.select()\" rows=\"10\" cols=\"70\">$encrypted</textarea>";
				?>
				<form action="pgp.php" method="post"><input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
				<table border="1">
					<tr><td><input type="text" name="enable_2fa_code"></td><td><button type="submit">Confirm</button></td></tr>
				</table></form>
				<hr>
				<?php
			}
		}
	}
}
?>
<p>Add your PGP key for more security features like 2FA:</p>
<form action="pgp.php" method="post">
<input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
<table border="1">
<tr><td><textarea name="pgp_key" rows="10" cols="70"><?php echo $user['pgp_key']; ?></textarea></td></tr>
<tr><td><button type="submit">Update PGP key</button></td></tr>
</table>
</form>
<p><a href="home.php">Go back to dashboard.</a></p>
</body></html>
