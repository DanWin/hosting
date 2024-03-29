<?php
require('../common.php');
$db = get_db_instance();
header('Content-Type: text/html; charset=UTF-8');
session_start(['name'=>'hosting_admin']);
if($_SERVER['REQUEST_METHOD']==='HEAD'){
	exit; // headers sent, no further processing needed
}
print_header(_('Admin panel'), 'td{padding:5px;}', '_blank');
?>
<h1><?php echo _('Hosting - Admin panel'); ?></h1>
<?php
$error=false;
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['pass']) && $_POST['pass']===ADMIN_PASSWORD){
	if(!($error=check_captcha_error())){
		$_SESSION['logged_in']=true;
		$_SESSION['csrf_token']=sha1(uniqid());
	}
}
if(empty($_SESSION['logged_in'])){
	echo '<form action="' . $_SERVER['SCRIPT_NAME'] . '" method="POST" target="_self"><table>';
	echo '<tr><td>'._('Password').' </td><td><input type="password" name="pass" size="30" required autofocus></td></tr>';
	send_captcha();
	echo '<tr><td colspan="2"><button type="submit" name="action" value="login">'._('Login').'</button></td></tr>';
	echo '</table></form>';
	if($error){
		echo '<p role="alert" style="color:red">'.$error.'</p>';
	}elseif(isset($_POST['pass'])){
		echo '<p role="alert" style="color:red">'._('Wrong password!').'</p>';
	}
	echo '<p>'._("If you disabled cookies, please re-enable them. You can't log in without!").'</p>';
}else{
	echo '<p>';
	if(REQUIRE_APPROVAL){
		$stmt=$db->query('SELECT COUNT(*) FROM new_account WHERE approved=0;');
		$cnt=$stmt->fetch(PDO::FETCH_NUM)[0];
		echo '<a href="' . $_SERVER['SCRIPT_NAME'] . '?action=approve" target="_self">'.sprintf(_('Approve pending sites (%s)'), $cnt).'</a> | ';
	}
	echo '<a href="' . $_SERVER['SCRIPT_NAME'] . '?action=list" target="_self">'._('List of accounts').'</a> | <a href="' . $_SERVER['SCRIPT_NAME'] . '?action=delete" target="_self">'._('Delete accounts').'</a> | <a href="' . $_SERVER['SCRIPT_NAME'] . '?action=suspend" target="_self">'._('Suspend hidden services').'</a> | <a href="' . $_SERVER['SCRIPT_NAME'] . '?action=edit" target="_self">'._('Edit hidden services').'</a> | <a href="' . $_SERVER['SCRIPT_NAME'] . '?action=logout" target="_self">'._('Logout').'</a></p>';
	if(empty($_REQUEST['action']) || $_REQUEST['action']==='login'){
		echo '<p>'._('Welcome to the admin panel!').'</p>';
	}elseif($_REQUEST['action'] === 'logout'){
		session_destroy();
		header('Location: ' . $_SERVER['SCRIPT_NAME']);
		exit;
	}elseif($_REQUEST['action'] === 'list'){
		echo '<form action="' . $_SERVER['SCRIPT_NAME'] . '" method="POST">';
		echo '<input type="hidden" name="csrf_token" value="' . $_SESSION['csrf_token'] . '">';
		echo '<table border="1">';
		echo '<tr><th>'._('Username').'</th><th>'._('Onion link').'</th><th>'._('Action').'</th></tr>';
		$stmt=$db->query('SELECT users.username, onions.onion, onions.enabled FROM users INNER JOIN onions ON (onions.user_id=users.id) ORDER BY users.username;');
		$accounts = [];
		while($tmp=$stmt->fetch(PDO::FETCH_NUM)){
			$accounts[$tmp[0]] []= [$tmp[1], $tmp[2]];
		}
		foreach($accounts as $account => $onions){
			echo "<tr><td>$account</td><td>";
			$first = true;
			foreach($onions as $onion){
				if($first){
					$first = false;
				}else{
					echo '<br>';
				}
				if($onion[1]=='1'){
					echo "<a href=\"http://$onion[0].onion\">$onion[0].onion</a>";
				}else{
					echo "$onion[0].onion";
				}
			}
			echo '</td><td><button type="submit" name="action" value="edit_'.$onions[0][0].'">'._('Edit').'</button><button type="submit" name="action" value="delete_'.$onions[0][0].'">'._('Delete').'</button><button type="submit" name="action" value="suspend_'.$onions[0][0].'">'._('Suspend').'</button></td></tr>';
		}
		echo '</table></form>';
	}elseif( str_starts_with( $_REQUEST[ 'action' ], 'approve' ) ){
		$onion = substr($_REQUEST['action'], 8);
		if(!empty($onion)){
			if($error=check_csrf_error()){
				echo '<p role="alert" style="color:red">'.$error.'</p>';
			}else{
				$stmt=$db->prepare('UPDATE new_account INNER JOIN onions ON (onions.user_id=new_account.user_id) SET new_account.approved=1 WHERE onions.onion=?;');
				$stmt->execute([$onion]);
				echo '<p role="alert" style="color:green">'._('Successfully approved').'</p>';
			}
		}
		echo '<form action="' . $_SERVER['SCRIPT_NAME'] . '" method="POST" target="_self">';
		echo '<input type="hidden" name="csrf_token" value="' . $_SESSION['csrf_token'] . '">';
		echo '<table border="1">';
		echo '<tr><th>'._('Username').'</th><th>'._('Onion address').'</th><th>'._('Action').'</th></tr>';
		$stmt=$db->query('SELECT users.username, onions.onion FROM users INNER JOIN new_account ON (users.id=new_account.user_id) INNER JOIN onions ON (onions.user_id=users.id) WHERE new_account.approved=0 ORDER BY users.username;');
		while($tmp=$stmt->fetch(PDO::FETCH_NUM)){
			echo "<tr><td>$tmp[0]</td><td><a href=\"http://$tmp[1].onion\">$tmp[1].onion</a></td><td><button type=\"submit\" name=\"action\" value=\"approve_$tmp[1]\">"._('Approve').'</button><button type="submit" name="action" value="delete_'.$tmp[1].'">'._('Delete').'</button></td></tr>';
		}
		echo '</table></form>';
	}elseif( str_starts_with( $_REQUEST[ 'action' ], 'delete' ) ){
		$onion = $_POST[ 'onion' ] ?? substr( $_REQUEST[ 'action' ], 7 );
		echo '<p>'._('Delete accounts:').'</p>';
		echo '<form action="' . $_SERVER['SCRIPT_NAME'] . '" method="POST" target="_self">';
		echo '<input type="hidden" name="csrf_token" value="' . $_SESSION['csrf_token'] . '">';
		echo '<p>'._('Onion address:').' <input type="text" name="onion" size="30" value="';
		echo htmlspecialchars($onion);
		echo '" required autofocus></p>';
		echo '<button type="submit" name="action" value="delete">'._('Delete').'</button></form><br>';
		if(!empty($onion)){
			if($error=check_csrf_error()){
				echo '<p style="color:red;">'.$error.'</p>';
			}elseif(preg_match('~^([a-z2-7]{16}|[a-z2-7]{56})(\.onion)?$~', $onion, $match)){
				$stmt=$db->prepare('SELECT user_id FROM onions WHERE onion=?;');
				$stmt->execute([$match[1]]);
				if($user_id=$stmt->fetch(PDO::FETCH_NUM)){
					$stmt=$db->prepare('UPDATE users SET todelete=1 WHERE id=?;');
					$stmt->execute($user_id);
					echo '<p role="alert" style="color:green">'._('Successfully queued for deletion!').'</p>';
				}else{
					echo '<p role="alert" style="color:red">'._('Onion address not hosted by us!').'</p>';
				}
			}else{
				echo '<p role="alert" style="color:red">'._('Invalid onion address!').'</p>';
			}
		}
	}elseif( str_starts_with( $_REQUEST[ 'action' ], 'suspend' ) ){
		$onion = $_POST[ 'onion' ] ?? substr( $_REQUEST[ 'action' ], 8 );
		echo '<p>'._('Suspend hidden service:').'</p>';
		echo '<form action="' . $_SERVER['SCRIPT_NAME'] . '" method="POST" target="_self">';
		echo '<input type="hidden" name="csrf_token" value="' . $_SESSION['csrf_token'] . '">';
		echo '<p>'._('Onion address:').' <input type="text" name="onion" size="30" value="';
		echo htmlspecialchars($onion);
		echo '" required autofocus></p>';
		echo '<button type="submit" name="action" value="suspend">'._('Suspend').'</button></form><br>';
		if(!empty($onion)){
			if($error=check_csrf_error()){
				echo '<p role="alert" style="color:red">'.$error.'</p>';
			}elseif(preg_match('~^([a-z2-7]{16}|[a-z2-7]{56})(\.onion)?$~', $onion, $match)){
				$stmt=$db->prepare('SELECT instance FROM onions WHERE onion=?;');
				$stmt->execute([$match[1]]);
				if($instance=$stmt->fetch(PDO::FETCH_NUM)){
					$stmt=$db->prepare('UPDATE onions SET enabled=-2 WHERE onion=?;');
					$stmt->execute([$match[1]]);
					echo '<p role="alert" style="color:green">'._('Successfully queued for suspension!').'</p>';
					enqueue_instance_reload($instance[0]);
				}else{
					echo '<p role="alert" style="color:red">'._('Onion address not hosted by us!').'</p>';
				}
			}else{
				echo '<p role="alert" style="color:red">'._('Invalid onion address!').'</p>';
			}
		}
	}elseif( str_starts_with( $_REQUEST[ 'action' ], 'edit' ) ){
		$onion = $_POST[ 'onion' ] ?? substr( $_REQUEST[ 'action' ], 5 );
		echo '<p>'._('Edit hidden service:').'</p>';
		echo '<form action="' . $_SERVER['SCRIPT_NAME'] . '" method="POST" target="_self">';
		echo '<input type="hidden" name="csrf_token" value="' . $_SESSION['csrf_token'] . '">';
		echo '<p>'._('Onion address:').' <input type="text" name="onion" size="30" value="';
		echo htmlspecialchars($onion);
		echo '" required autofocus></p>';
		echo '<input type="submit" name="action" value="edit"></form><br>';
		if(!empty($onion)){
			if($error=check_csrf_error()){
				echo '<p role="alert" style="color:red">'.$error.'</p>';
			}elseif(preg_match('~^([a-z2-7]{16}|[a-z2-7]{56})(\.onion)?$~', $onion, $match)){
				if(isset($_POST['num_intros'])){
					$stmt=$db->prepare('SELECT version, instance FROM onions WHERE onion=?;');
					$stmt->execute([$match[1]]);
					if($onion=$stmt->fetch(PDO::FETCH_NUM)){
						$stmt=$db->prepare('UPDATE onions SET enabled = ?, enable_smtp = ?, num_intros = ?, max_streams = ? WHERE onion=?;');
						$enabled = isset($_POST['enabled']) ? 1 : 0;
						$enable_smtp = isset($_POST['enable_smtp']) ? 1 : 0;
						$num_intros = intval($_POST['num_intros']);
						if($num_intros<3){
							$num_intros = 3;
						}elseif($onion[0]==2 && $num_intros>10){
							$num_intros = 10;
						}elseif($num_intros>20){
							$num_intros = 20;
						}
						$max_streams = intval($_POST['max_streams']);
						if($max_streams<0){
							$max_streams = 0;
						}elseif($max_streams>65535){
							$max_streams = 65535;
						}
						$stmt->execute([$enabled, $enable_smtp, $num_intros, $max_streams, $match[1]]);
						enqueue_instance_reload($onion[1]);
						echo '<p role="alert" style="color:green">'._('Changes successfully saved!').'</p>';
					}
				}
				$stmt=$db->prepare('SELECT onion, enabled, enable_smtp, num_intros, max_streams, version FROM onions WHERE onion=?;');
				$stmt->execute([$match[1]]);
				if($onion=$stmt->fetch(PDO::FETCH_NUM)){
					echo '<form action="' . $_SERVER['SCRIPT_NAME'] . '" method="POST" target="_self">';
					echo '<input type="hidden" name="csrf_token" value="' . $_SESSION['csrf_token'] . '">';
					echo '<table border="1"><tr><th>'._('Onion').'</th><th>'._('Enabled').'</th><th>'._('SMTP enabled').'</th><th>'._('Nr. of intros').'</th><th>'._('Max streams per rend circuit').'</th><th>'._('Save').'</th></tr>';
					echo '<tr><td><input type="text" name="onion" size="15" value="'.$onion[0].'" required autofocus></td>';
					echo '<td><label><input type="checkbox" name="enabled" value="1"';
					echo $onion[1] ? ' checked' : '';
					echo '>'._('Enabled').'</label></td>';
					echo '<td><label><input type="checkbox" name="enable_smtp" value="1"';
					echo $onion[2] ? ' checked' : '';
					echo '>'._('Enabled').'</label></td>';
					echo '<td><input type="number" name="num_intros" min="3" max="20" value="'.$onion[3].'"></td>';
					echo '<td><input type="number" name="max_streams" min="0" max="65535" value="'.$onion[4].'"></td>';
					echo '<td><button type="submit" name="action" value="edit">'._('Save').'</button></td></tr>';
				}else{
					echo '<p role="alert" style="color:red">'._('Onion address not hosted by us!').'</p>';
				}
			}else{
				echo '<p role="alert" style="color:red">'._('Invalid onion address!').'</p>';
			}
		}
	}
}
echo '</body></html>';
