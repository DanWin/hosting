<?php
include('../common.php');
try{
	$db=new PDO('mysql:host=' . DBHOST . ';dbname=' . DBNAME, DBUSER, DBPASS, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_WARNING, PDO::ATTR_PERSISTENT=>PERSISTENT]);
}catch(PDOException $e){
	die('No Connection to MySQL database!');
}
header('Content-Type: text/html; charset=UTF-8');
session_start(['name'=>'hosting_admin']);
if($_SERVER['REQUEST_METHOD']==='HEAD'){
	exit; // headers sent, no further processing needed
}
echo '<!DOCTYPE html><html><head>';
echo '<title>Daniel\'s Hosting - Admin panel</title>';
echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
echo '<meta name="author" content="Daniel Winzen">';
echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
echo '<link rel="canonical" href="'.CANONICAL_URL. $_SERVER['SCRIPT_NAME'] .'">';
echo '</head><body>';
echo '<h1>Hosting - Admin panel</h1>';
$error=false;
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['pass']) && $_POST['pass']===ADMIN_PASSWORD){
	if(!($error=check_captcha_error())){
		$_SESSION['logged_in']=true;
		$_SESSION['csrf_token']=sha1(uniqid());
	}
}
if(empty($_SESSION['logged_in'])){
	echo '<form action="' . $_SERVER['SCRIPT_NAME'] . '" method="POST"><table>';
	echo "<tr><td>Password </td><td><input type=\"password\" name=\"pass\" size=\"30\" required autofocus></td></tr>";
	send_captcha();
	echo "<tr><td colspan=\"2\"><input type=\"submit\" name=\"action\" value=\"login\"></td></tr>";
	echo '</table></form>';
	if($error){
		echo "<p style=\"color:red;\">$error</p>";
	}elseif(isset($_POST['pass'])){
		echo "<p style=\"color:red;\">Wrong password!</p>";
	}
	echo '<p>If you disabled cookies, please re-enable them. You can\'t log in without!</p>';
}else{
	echo '<p>';
	if(REQUIRE_APPROVAL){
		$stmt=$db->query('SELECT COUNT(*) FROM new_account WHERE approved=0;');
		$cnt=$stmt->fetch(PDO::FETCH_NUM)[0];
		echo '<a href="' . $_SERVER['SCRIPT_NAME'] . "?action=approve\">Approve pending sites ($cnt)</a> | ";
	}
	echo '<a href="' . $_SERVER['SCRIPT_NAME'] . '?action=list">List of accounts</a> | <a href="' . $_SERVER['SCRIPT_NAME'] . '?action=delete">Delete accounts</a> | <a href="' . $_SERVER['SCRIPT_NAME'] . '?action=suspend">Suspend hidden services</a> | <a href="' . $_SERVER['SCRIPT_NAME'] . '?action=edit">Edit hidden services</a> | <a href="' . $_SERVER['SCRIPT_NAME'] . '?action=logout">Logout</a></p>';
	if(empty($_REQUEST['action']) || $_REQUEST['action']==='login'){
		echo '<p>Welcome to the admin panel!</p>';
	}elseif($_REQUEST['action'] === 'logout'){
		session_destroy();
		header('Location: ' . $_SERVER['SCRIPT_NAME']);
		exit;
	}elseif($_REQUEST['action'] === 'list'){
		echo '<form action="' . $_SERVER['SCRIPT_NAME'] . "\" method=\"POST\"><input type=\"hidden\" name=\"csrf_token\" value=\"$_SESSION[csrf_token]\">";
		echo '<table border="1">';
		echo '<tr><th>Username</th><th>Onion link</th><th>Action</th></tr>';
		$stmt=$db->query('SELECT users.username, onions.onion, onions.enabled FROM users INNER JOIN onions ON (onions.user_id=users.id) ORDER BY users.username;');
		$sccounts = [];
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
					echo "<a href=\"http://$onion[0].onion\" target=\"_blank\">$onion[0].onion</a>";
				}else{
					echo "$onion[0].onion";
				}
			}
			echo "</td><td><button type=\"submit\" name=\"action\" value=\"edit_{$onions[0][0]}\">Edit</button><button type=\"submit\" name=\"action\" value=\"delete_{$onions[0][0]}\">Delete</button><button type=\"submit\" name=\"action\" value=\"suspend_{$onions[0][0]}\">Suspend</button></td></tr>";
		}
		echo '</table></form>';
	}elseif(substr($_REQUEST['action'], 0, 7) === 'approve'){
		$onion = substr($_REQUEST['action'], 8);
		if(!empty($onion)){
			if($error=check_csrf_error()){
				echo '<p style="color:red;">'.$error.'</p>';
			}else{
				$stmt=$db->prepare('UPDATE new_account INNER JOIN onions ON (onions.user_id=new_account.user_id) SET new_account.approved=1 WHERE onions.onion=?;');
				$stmt->execute([$onion]);
				echo '<p style="color:green;">Successfully approved</p>';
			}
		}
		echo '<form action="' . $_SERVER['SCRIPT_NAME'] . "\" method=\"POST\"><input type=\"hidden\" name=\"csrf_token\" value=\"$_SESSION[csrf_token]\">";
		echo '<table border="1">';
		echo '<tr><th>Username</th><th>Onion address</th><th>Action</th></tr>';
		$stmt=$db->query('SELECT users.username, onions.onion FROM users INNER JOIN new_account ON (users.id=new_account.user_id) INNER JOIN onions ON (onions.user_id=users.id) WHERE new_account.approved=0 ORDER BY users.username;');
		while($tmp=$stmt->fetch(PDO::FETCH_NUM)){
			echo "<tr><td>$tmp[0]</td><td><a href=\"http://$tmp[1].onion\" target=\"_blank\">$tmp[1].onion</a></td><td><button type=\"submit\" name=\"action\" value=\"approve_$tmp[1]\">Approve</button><button type=\"submit\" name=\"action\" value=\"delete_$tmp[1]\">Delete</button></td></tr>";
		}
		echo '</table></form>';
	}elseif(substr($_REQUEST['action'], 0, 6) === 'delete'){
		$onion = '';
		if(isset($_POST['onion'])){
			$onion = $_POST['onion'];
		}else{
			$onion = substr($_REQUEST['action'], 7);
		}
		echo '<p>Delete accouts:</p>';
		echo '<form action="' . $_SERVER['SCRIPT_NAME'] . '" method="POST">';
		echo '<input type="hidden" name="csrf_token" value="' . $_SESSION['csrf_token'] . '">';
		echo '<p>Onion address: <input type="text" name="onion" size="30" value="';
		echo htmlspecialchars($onion);
		echo '" required autofocus></p>';
		echo '<input type="submit" name="action" value="delete"></form><br>';
		if(!empty($onion)){
			if($error=check_csrf_error()){
				echo '<p style="color:red;">'.$error.'</p>';
			}elseif(preg_match('~^([a-z2-7]{16}|[a-z2-7]{56})(\.onion)?$~', $onion, $match)){
				$stmt=$db->prepare('SELECT user_id FROM onions WHERE onion=?;');
				$stmt->execute([$match[1]]);
				if($user_id=$stmt->fetch(PDO::FETCH_NUM)){
					$stmt=$db->prepare('UPDATE users SET todelete=1 WHERE id=?;');
					$stmt->execute($user_id);
					echo "<p style=\"color:green;\">Successfully queued for deletion!</p>";
				}else{
					echo "<p style=\"color:red;\">Onion address not hosted by us!</p>";
				}
			}else{
				echo "<p style=\"color:red;\">Invalid onion address!</p>";
			}
		}
	}elseif(substr($_REQUEST['action'], 0, 7) === 'suspend'){
		$onion = '';
		if(isset($_POST['onion'])){
			$onion = $_POST['onion'];
		}else{
			$onion = substr($_REQUEST['action'], 8);
		}
		echo '<p>Suspend hidden service:</p>';
		echo '<form action="' . $_SERVER['SCRIPT_NAME'] . '" method="POST">';
		echo '<input type="hidden" name="csrf_token" value="'.$_SESSION['csrf_token'].'">';
		echo '<p>Onion address: <input type="text" name="onion" size="30" value="';
		echo htmlspecialchars($onion);
		echo '" required autofocus></p>';
		echo '<input type="submit" name="action" value="suspend"></form><br>';
		if(!empty($onion)){
			if($error=check_csrf_error()){
				echo '<p style="color:red;">'.$error.'</p>';
			}elseif(preg_match('~^([a-z2-7]{16}|[a-z2-7]{56})(\.onion)?$~', $onion, $match)){
				$stmt=$db->prepare('SELECT null FROM onions WHERE onion=?;');
				$stmt->execute([$match[1]]);
				if($stmt->fetch(PDO::FETCH_NUM)){
					$stmt=$db->prepare('UPDATE onions SET enabled=-2 WHERE onion=?;');
					$stmt->execute([$match[1]]);
					echo "<p style=\"color:green;\">Successfully queued for suspension!</p>";
					enqueue_instance_reload($db, substr($match[1], 0, 1));
				}else{
					echo "<p style=\"color:red;\">Onion address not hosted by us!</p>";
				}
			}else{
				echo "<p style=\"color:red;\">Invalid onion address!</p>";
			}
		}
	}elseif(substr($_REQUEST['action'], 0, 4) === 'edit'){
		$onion = '';
		if(isset($_POST['onion'])){
			$onion = $_POST['onion'];
		}else{
			$onion = substr($_REQUEST['action'], 5);
		}
		echo '<p>Edit hidden service:</p>';
		echo '<form action="' . $_SERVER['SCRIPT_NAME'] . '" method="POST">';
		echo '<input type="hidden" name="csrf_token" value="'.$_SESSION['csrf_token'].'">';
		echo '<p>Onion address: <input type="text" name="onion" size="30" value="';
		echo htmlspecialchars($onion);
		echo '" required autofocus></p>';
		echo '<input type="submit" name="action" value="edit"></form><br>';
		if(!empty($onion)){
			if($error=check_csrf_error()){
				echo '<p style="color:red;">'.$error.'</p>';
			}elseif(preg_match('~^([a-z2-7]{16}|[a-z2-7]{56})(\.onion)?$~', $onion, $match)){
				if(isset($_POST['num_intros'])){
					$stmt=$db->prepare('SELECT version FROM onions WHERE onion=?;');
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
						enqueue_instance_reload($db, substr($match[1], 0, 1));
						echo "<p style=\"color:green;\">Changes successfully saved!</p>";
					}
				}
				$stmt=$db->prepare('SELECT onion, enabled, enable_smtp, num_intros, max_streams, version FROM onions WHERE onion=?;');
				$stmt->execute([$match[1]]);
				if($onion=$stmt->fetch(PDO::FETCH_NUM)){
					echo '<form action="' . $_SERVER['SCRIPT_NAME'] . '" method="POST">';
					echo '<input type="hidden" name="csrf_token" value="'.$_SESSION['csrf_token'].'">';
					echo '<table border="1"><tr><th>Onion</th><th>Enabled</th><th>SMTP enabled</th><th>Nr. of intros</th><th>Max streams per rend circuit</th><th>Save</th></tr>';
					echo '<tr><td><input type="text" name="onion" size="15" value="'.$onion[0].'" required autofocus></td>';
					echo '<td><label><input type="checkbox" name="enabled" value="1"';
					echo $onion[1] ? ' checked' : '';
					echo '>Enabled</label></td>';
					echo '<td><label><input type="checkbox" name="enable_smtp" value="1"';
					echo $onion[2] ? ' checked' : '';
					echo '>Enabled</label></td>';
					echo '<td><input type="number" name="num_intros" min="3" max="20" value="'.$onion[3].'"></td>';
					echo '<td><input type="number" name="max_streams" min="0" max="65535" value="'.$onion[4].'"></td>';
					echo '<td><button type="submit" name="action" value="edit">Save</button></td></tr>';
				}else{
					echo "<p style=\"color:red;\">Onion address not hosted by us!</p>";
				}
			}else{
				echo "<p style=\"color:red;\">Invalid onion address!</p>";
			}
		}
	}
}
echo '</body></html>';
