<?php
include('../common.php');
try{
	$db=new PDO('mysql:host=' . DBHOST . ';dbname=' . DBNAME, DBUSER, DBPASS, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_WARNING, PDO::ATTR_PERSISTENT=>PERSISTENT]);
}catch(PDOException $e){
	die('No Connection to MySQL database!');
}
session_start();
$user=check_login();
if(isset($_POST['action']) && $_POST['action']==='add_db'){
	if($error=check_csrf_error()){
		die($error);
	}
	add_user_db($db, $user['id']);
}
if(isset($_POST['action']) && $_POST['action']==='del_db' && !empty($_POST['db'])){
	if($error=check_csrf_error()){
		die($error);
	} ?>
<!DOCTYPE html><html><head>
<title>Daniel's Hosting - Delete database</title>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<meta name="author" content="Daniel Winzen">
<meta name="viewport" content="width=device-width, initial-scale=1">
</head><body>
<p>This will delete your database <?php echo htmlspecialchars($_POST['db']); ?> and all data asociated with it. It can't be un-done. Are you sure?</p>
<form method="post" action="home.php"><input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
<input type="hidden" name="db" value="<?php echo htmlspecialchars($_POST['db']); ?>">
<button type="submit" name="action" value="del_db_2">Yes, delete</button>
</form>
<p><a href="home.php">No, don't delete.</a></p>
</body></html><?php
exit;
}
if(isset($_POST['action']) && $_POST['action']==='del_db_2' && !empty($_POST['db'])){
	if($error=check_csrf_error()){
		die($error);
	}
	del_user_db($db, $user['id'], $_POST['db']);
}
if(isset($_POST['action']) && $_POST['action']==='del_onion' && !empty($_POST['onion'])){
	if($error=check_csrf_error()){
		die($error);
	} ?>
<!DOCTYPE html><html><head>
<title>Daniel's Hosting - Delete onion domain</title>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<meta name="author" content="Daniel Winzen">
<meta name="viewport" content="width=device-width, initial-scale=1">
</head><body>
<p>This will delete your onion domain <?php echo htmlspecialchars($_POST['onion']); ?>.onion and all data asociated with it. It can't be un-done. Are you sure?</p>
<form method="post" action="home.php"><input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
<input type="hidden" name="onion" value="<?php echo htmlspecialchars($_POST['onion']); ?>">
<button type="submit" name="action" value="del_onion_2">Yes, delete</button>
</form>
<p><a href="home.php">No, don't delete.</a></p>
</body></html><?php
exit;
}
if(isset($_POST['action']) && $_POST['action']==='add_onion'){
	if($error=check_csrf_error()){
		die($error);
	}
	$ok = true;
	if(isset($_REQUEST['onion_type']) && $_REQUEST['onion_type']==='custom' && isset($_REQUEST['private_key']) && !empty(trim($_REQUEST['private_key']))){
		$priv_key = trim($_REQUEST['private_key']);
		$data = private_key_to_onion($priv_key);
		$onion = $data['onion'];
		$onion_version = $data['version'];
		if(!$data['ok']){
			$msg = "<p style=\"color:red;\">$data[message]</p>";
			$ok = false;
		} else {
			$check=$db->prepare('SELECT null FROM onions WHERE onion=?;');
			$check->execute([$onion]);
			if($check->fetch(PDO::FETCH_NUM)){
				$msg = '<p style="color:red;">Error onion already exists.</p>';
				$ok = false;
			}
		}
	}else{
		$onion_version = 3;
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
	$stmt = $db->prepare('SELECT COUNT(*) FROM onions WHERE user_id = ?;');
	$stmt->execute([$user['id']]);
	$count = $stmt->fetch(PDO::FETCH_NUM);
	if($count[0]>=MAX_NUM_USER_ONIONS) {
		$ok = false;
	}
	if($ok){
		$stmt=$db->prepare('INSERT INTO onions (user_id, onion, private_key, version, enabled) VALUES (?, ?, ?, ?, 2);');
		$stmt->execute([$user['id'], $onion, $priv_key, $onion_version]);
	}
}
if(isset($_POST['action']) && $_POST['action']==='del_onion_2' && !empty($_POST['onion'])){
	if($error=check_csrf_error()){
		die($error);
	}
	del_user_onion($db, $user['id'], $_POST['onion']);
}
if(isset($_POST['action']) && $_POST['action']==='add_domain' && !empty($_POST['domain'])){
	if($error=check_csrf_error()){
		die($error);
	}
	$error = add_user_domain($db, $user['id'], $_POST['domain']);
	if(!empty($error)){
		$msg = "<p style=\"color:red;\">$error</p>";
	}else{
		$stmt=$db->prepare('UPDATE service_instances SET reload = 1 WHERE id = ?');
		$stmt->execute([substr($user['system_account'], 0, 1)]);
	}
}
if(isset($_POST['action']) && $_POST['action']==='del_domain' && !empty($_POST['domain'])){
	if($error=check_csrf_error()){
		die($error);
	} ?>
<!DOCTYPE html><html><head>
<title>Daniel's Hosting - Delete domain</title>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<meta name="author" content="Daniel Winzen">
<meta name="viewport" content="width=device-width, initial-scale=1">
</head><body>
<p>This will delete your domain <?php echo htmlspecialchars($_POST['domain']); ?> and all data asociated with it. It can't be un-done. Are you sure?</p>
<form method="post" action="home.php"><input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
<input type="hidden" name="domain" value="<?php echo htmlspecialchars($_POST['domain']); ?>">
<button type="submit" name="action" value="del_domain_2">Yes, delete</button>
</form>
<p><a href="home.php">No, don't delete.</a></p>
</body></html><?php
exit;
}
if(isset($_POST['action']) && $_POST['action']==='del_domain_2' && !empty($_POST['domain'])){
	if($error=check_csrf_error()){
		die($error);
	}
	del_user_domain($db, $user['id'], $_POST['domain']);
	$stmt=$db->prepare('UPDATE service_instances SET reload = 1 WHERE id = ?');
	$stmt->execute([substr($user['system_account'], 0, 1)]);
}
if(isset($_REQUEST['action']) && isset($_REQUEST['onion']) && $_REQUEST['action']==='edit_onion'){
	if($error=check_csrf_error()){
		die($error);
	}
	$stmt=$db->prepare('SELECT onions.version FROM onions INNER JOIN users ON (users.id=onions.user_id) WHERE onions.onion = ? AND users.id = ? AND onions.enabled IN (0, 1);');
	$stmt->execute([$_REQUEST['onion'], $user['id']]);
	if($onion=$stmt->fetch(PDO::FETCH_NUM)){
		$stmt=$db->prepare('UPDATE onions SET enabled = ?, enable_smtp = ?, num_intros = ?, max_streams = ? WHERE onion = ?;');
		$enabled = isset($_REQUEST['enabled']) ? 1 : 0;
		$enable_smtp = isset($_REQUEST['enable_smtp']) ? 1 : 0;
		$num_intros = intval($_REQUEST['num_intros']);
		if($num_intros<3){
				$num_intros = 3;
		}elseif($onion[0]==2 && $num_intros>10){
			$num_intros = 10;
		}elseif($num_intros>20){
			$num_intros = 20;
		}
		$max_streams = intval($_REQUEST['max_streams']);
		if($max_streams<0){
			$max_streams = 0;
		}elseif($max_streams>65535){
			$max_streams = 65535;
		}
		$stmt->execute([$enabled, $enable_smtp, $num_intros, $max_streams, $_REQUEST['onion']]);
		$stmt=$db->prepare('UPDATE service_instances SET reload = 1 WHERE id = ?');
		$stmt->execute([substr($_REQUEST['onion'], 0, 1)]);
	}
}
if(isset($_REQUEST['action']) && isset($_POST['domain']) && $_POST['action']==='edit_domain'){
	if($error=check_csrf_error()){
		die($error);
	}
	$stmt=$db->prepare('SELECT null FROM domains WHERE domain = ? AND user_id = ? AND enabled IN (0, 1);');
	$stmt->execute([$_POST['domain'], $user['id']]);
	if($onion=$stmt->fetch(PDO::FETCH_NUM)){
		$stmt=$db->prepare('UPDATE domains SET enabled = ? WHERE domain = ?;');
		$enabled = isset($_POST['enabled']) ? 1 : 0;
		$stmt->execute([$enabled, $_POST['domain']]);
		$stmt=$db->prepare('UPDATE service_instances SET reload = 1 WHERE id = ?');
		$stmt->execute([substr($user['system_account'], 0, 1)]);
	}
}

header('Content-Type: text/html; charset=UTF-8');
echo '<!DOCTYPE html><html><head>';
echo '<title>Daniel\'s Hosting - Dashboard</title>';
echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
echo '<meta name="author" content="Daniel Winzen">';
echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
echo '<style type="text/css">#custom_onion:not(checked)+#private_key{display:none;}#custom_onion:checked+#private_key{display:block;}</style>';
echo '</head><body>';
echo "<p>Logged in as $user[username] <a href=\"logout.php\">Logout</a> | <a href=\"password.php\">Change passwords</a> | <a target=\"_blank\" href=\"files.php\">FileManager</a> | <a href=\"delete.php\">Delete account</a></p>";
if(!empty($msg)){
	echo $msg;
}
echo "<p>Enter system account password to check your $user[system_account]@" . ADDRESS . " mail:</td><td><form action=\"squirrelmail/src/redirect.php\" method=\"post\" target=\"_blank\"><input type=\"hidden\" name=\"login_username\" value=\"$user[system_account]\"><input type=\"password\" name=\"secretkey\"><input type=\"submit\" value=\"Login to webmail\"></form></p>";
echo '<h3>Onion domains</h3>';
echo '<table border="1">';
echo '<tr><th>Onion</th><th>Private key</th><th>Enabled</th><th>SMTP enabled</th><th>Nr. of intros</th><th>Max streams per rend circuit</th><th>Action</th></tr>';
$stmt=$db->prepare('SELECT onion, private_key, enabled, enable_smtp, num_intros, max_streams FROM onions WHERE user_id = ?;');
$stmt->execute([$user['id']]);
$count_onions = 0;
while($onion=$stmt->fetch(PDO::FETCH_ASSOC)){
	++$count_onions;
	echo "<form action=\"home.php\" method=\"post\"><input type=\"hidden\" name=\"csrf_token\" value=\"$_SESSION[csrf_token]\"><input type=\"hidden\" name=\"onion\" value=\"$onion[onion]\"><tr><td><a href=\"http://$onion[onion].onion\" target=\"_blank\">$onion[onion].onion</a></td><td>";
	if(isset($_REQUEST['show_priv'])){
		echo "<pre>$onion[private_key]</pre>";
	}else{
		echo '<a href="home.php?show_priv=1">Show private key</a>';
	}
	echo '</td><td><label><input type="checkbox" name="enabled" value="1"';
	echo $onion['enabled'] ? ' checked' : '';
	echo '>Enabled</label></td>';
	echo '<td><label><input type="checkbox" name="enable_smtp" value="1"';
	echo $onion['enable_smtp'] ? ' checked' : '';
	echo '>Enabled</label></td>';
	echo '<td><input type="number" name="num_intros" min="3" max="20" value="'.$onion['num_intros'].'"></td>';
	echo '<td><input type="number" name="max_streams" min="0" max="65535" value="'.$onion['max_streams'].'"></td>';
	if(in_array($onion['enabled'], [0, 1])){
		echo '<td><button type="submit" name="action" value="edit_onion">Save</button>';
		echo '<button type="submit" name="action" value="del_onion">Delete</button></td>';
	}else{
		echo '<td>Unavailable</td>';
	}
	echo '</tr></form>';
}
if($count_onions<MAX_NUM_USER_ONIONS){
	echo "<form action=\"home.php\" method=\"post\"><input type=\"hidden\" name=\"csrf_token\" value=\"$_SESSION[csrf_token]\">";
	echo '<tr><td colspan="6">Add additional hidden service:<br>';
	echo '<label><input type="radio" name="onion_type" value="3"';
	echo (!isset($_POST['onion_type']) || isset($_POST['onion_type']) && $_POST['onion_type']==3) ? ' checked' : '';
	echo '>Random v3 Address</label>';
	echo '<label><input type="radio" name="onion_type" value="2"';
	echo isset($_POST['onion_type']) && $_POST['onion_type']==2 ? ' checked' : '';
	echo '>Random v2 Address</label>';
	echo '<label><input id="custom_onion" type="radio" name="onion_type" value="custom"';
	echo isset($_POST['onion_type']) && $_POST['onion_type']==='custom' ? ' checked' : '';
	echo '>Custom private key';
	echo '<textarea id="private_key" name="private_key" rows="5" cols="28">';
	echo isset($_REQUEST['private_key']) ? htmlspecialchars($_REQUEST['private_key']) : '';
	echo '</textarea>';
	echo '</label></td><td><button type="submit" name="action" value="add_onion">Add onion</button></td></tr></form>';
}
echo '</table>';
if(MAX_NUM_USER_DOMAINS>0){
	echo '<h3>Clearnet domains</h3>';
	echo '<table border="1">';
	echo '<tr><th>Domain</th><th>Enabled</th><th>Action</th></tr>';
	$stmt=$db->prepare('SELECT domain, enabled FROM domains WHERE user_id = ?;');
	$stmt->execute([$user['id']]);
	$count_domains = 0;
	while($domain=$stmt->fetch(PDO::FETCH_ASSOC)){
		++$count_domains;
		echo "<form action=\"home.php\" method=\"post\"><input type=\"hidden\" name=\"csrf_token\" value=\"$_SESSION[csrf_token]\"><input type=\"hidden\" name=\"domain\" value=\"$domain[domain]\"><tr><td><a href=\"https://$domain[domain]\" target=\"_blank\">$domain[domain]</a></td>";
		echo '<td><label><input type="checkbox" name="enabled" value="1"';
		echo $domain['enabled'] ? ' checked' : '';
		echo '>Enabled</label></td>';
		if(in_array($domain['enabled'], [0, 1])){
			echo '<td><button type="submit" name="action" value="edit_domain">Save</button>';
			echo '<button type="submit" name="action" value="del_domain">Delete</button></td>';
		}else{
			echo '<td>Unavailable</td>';
		}
		echo '</tr></form>';
	}
	if($count_domains<MAX_NUM_USER_DOMAINS){
		echo "<form action=\"home.php\" method=\"post\"><input type=\"hidden\" name=\"csrf_token\" value=\"$_SESSION[csrf_token]\">";
		echo '<tr><td colspan="2">Add additional domain:<br>';
		echo '<input type="text" name="domain" value="';
		echo isset($_POST['domain']) ? htmlspecialchars($_POST['domain']) : '';
		echo '">';
		echo '</td><td><button type="submit" name="action" value="add_domain">Add domain</button></td></tr></form>';
	}
	echo '</table>';
	echo '<p>To enable your clearnet domain, edit your DNS settings and enter 116.202.17.147 as your A record and 2a01:4f8:c010:d56::1 as your AAAA record. Once you have modified your DNS settings, <a href="https://danwin1210.me/contact.php" target="_blank">contact me</a> to configure the SSL certificate. You may also use any subdomain of danwin1210.me, like yoursite.danwin1210.me</p>';
}
echo '<h3>MySQL Database</h3>';
echo '<table border="1">';
echo '<tr><th>Database</th><th>Host</th><th>User</th><th>Action</th></tr>';
$stmt=$db->prepare('SELECT mysql_database FROM mysql_databases WHERE user_id = ?;');
$stmt->execute([$user['id']]);
$count_dbs = 0;
while($mysql=$stmt->fetch(PDO::FETCH_ASSOC)){
	++$count_dbs;
	echo '<form action="home.php" method="post">';
	echo '<input type="hidden" name="csrf_token" value="'.$_SESSION['csrf_token'].'">';
	echo '<input type="hidden" name="db" value="'.$mysql['mysql_database'].'">';
	echo "<tr><td>$mysql[mysql_database]</td><td>localhost</td><td>$user[mysql_user]</td><td><button type=\"submit\" name=\"action\" value=\"del_db\">Delete</button></td></tr>";
	echo '</form>';
}
echo '</table>';
if($count_dbs<MAX_NUM_USER_DBS){
	echo '<p><form action="home.php" method="post"><input type="hidden" name="csrf_token" value="'.$_SESSION['csrf_token'].'"><button type="submit" name="action" value="add_db">Add new database</button></form></p>';
}
echo '<p><a href="password.php?type=sql">Change MySQL password</a></p>';
echo '<p>You can use <a href="/phpmyadmin/" target="_blank">PHPMyAdmin</a> and <a href="/adminer/" target="_blank">Adminer</a> for web based database administration.</p>';
echo '<h3>System Account</h3>';
echo '<table border="1">';
echo '<tr><th>Username</th><th>Host</th><th>FTP Port</th><th>SFTP Port</th><th>POP3 Port</th><th>IMAP Port</th><th>SMTP port</th></tr>';
foreach(SERVERS as $server=>$tmp){
	echo "<tr><td>$user[system_account]</td><td>$server</td><td>$tmp[ftp]</td><td>$tmp[sftp]</td><td>$tmp[pop3]</td><td>$tmp[imap]</td><td>$tmp[smtp]</td></tr>";
}
echo '</table>';
echo '<p><a href="password.php?type=sys">Change system account password</a></p>';
echo '<p>You can use the <a target="_blank" href="files.php">FileManager</a> for web based file management.</p>';
echo '<h3>Logs</h3>';
echo '<table border="1">';
echo '<tr><th>Date</th><th>access.log</th><th>error.log</th></tr>';
echo '<tr><td>Today</td><td><a href="log.php?type=access&amp;old=0" target="_blank">access.log</log></td><td><a href="log.php?type=error&amp;old=0" target="_blank">error.log</a></td></tr>';
echo '<tr><td>Yesterday</td><td><a href="log.php?type=access&amp;old=1" target="_blank">access.log</log></td><td><a href="log.php?type=error&amp;old=1" target="_blank">error.log</a></td></tr>';
echo '</table>';
echo '</body></html>';
