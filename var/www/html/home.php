<?php
require('../common.php');
$db = get_db_instance();
$user=check_login();
header('Content-Type: text/html; charset=UTF-8');
if(isset($_POST['action']) && $_POST['action']==='add_db'){
	if($error=check_csrf_error()){
		die($error);
	}
	add_user_db($user['id']);
}
if(isset($_POST['action']) && $_POST['action']==='del_db' && !empty($_POST['db'])){
	if($error=check_csrf_error()){
		die($error);
	}
	print_header(_('Delete database'));
?>
<p><?php printf(_("This will delete your database %s and all data associated with it. It can't be un-done. Are you sure?"), htmlspecialchars($_POST['db'])); ?></p>
<form method="post" action="home.php"><input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
<input type="hidden" name="db" value="<?php echo htmlspecialchars($_POST['db']); ?>">
<button type="submit" name="action" value="del_db_2"><?php echo _('Yes, delete'); ?></button>
</form>
<p><a href="home.php"><?php echo _("No, don't delete"); ?></a></p>
</body></html><?php
exit;
}
if(isset($_POST['action']) && $_POST['action']==='del_db_2' && !empty($_POST['db'])){
	if($error=check_csrf_error()){
		die($error);
	}
	del_user_db($user['id'], $_POST['db']);
}
if(isset($_POST['action']) && $_POST['action']==='del_onion' && !empty($_POST['onion'])){
	if($error=check_csrf_error()){
		die($error);
	}
	print_header(_('Delete onion domain'));
?>
<p><?php printf(_("This will delete your onion domain %s and all data asociated with it. It can't be un-done. Are you sure?"),  htmlspecialchars($_POST['onion']).'.onion'); ?></p>
<form method="post" action="home.php"><input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
<input type="hidden" name="onion" value="<?php echo htmlspecialchars($_POST['onion']); ?>">
<button type="submit" name="action" value="del_onion_2"><?php echo _('Yes, delete'); ?></button>
</form>
<p><a href="home.php"><?php echo _("No, don't delete"); ?></a></p>
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
			$msg = '<p role="alert" style="color:red">'.$data['message'].'</p>';
			$ok = false;
		} else {
			$check=$db->prepare('SELECT null FROM onions WHERE onion=?;');
			$check->execute([$onion]);
			if($check->fetch(PDO::FETCH_NUM)){
				$msg = '<p role="alert" style="color:red">'._('Error onion already exists.').'</p>';
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
		add_user_onion($user['id'], $onion, $priv_key, $onion_version);
	}
}
if(isset($_POST['action']) && $_POST['action']==='del_onion_2' && !empty($_POST['onion'])){
	if($error=check_csrf_error()){
		die($error);
	}
	del_user_onion($user['id'], $_POST['onion']);
}
if(isset($_POST['action']) && $_POST['action']==='add_domain' && !empty($_POST['domain'])){
	if($error=check_csrf_error()){
		die($error);
	}
	$error = add_user_domain($user['id'], $_POST['domain']);
	if(!empty($error)){
		$msg = '<p role="alert" style="color:red">'.$error.'</p>';
	}
}
if(isset($_POST['action']) && $_POST['action']==='del_domain' && !empty($_POST['domain'])){
	if($error=check_csrf_error()){
		die($error);
	}
	print_header(_('Delete domain'));
?>
<p><?php printf(_("This will delete your domain %s and all data asociated with it. It can't be un-done. Are you sure?"), htmlspecialchars($_POST['domain'])); ?></p>
<form method="post" action="home.php"><input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
<input type="hidden" name="domain" value="<?php echo htmlspecialchars($_POST['domain']); ?>">
<button type="submit" name="action" value="del_domain_2"><?php echo _('Yes, delete'); ?></button>
</form>
<p><a href="home.php"><?php echo _("No, don't delete"); ?></a></p>
</body></html><?php
exit;
}
if(isset($_POST['action']) && $_POST['action']==='del_domain_2' && !empty($_POST['domain'])){
	if($error=check_csrf_error()){
		die($error);
	}
	del_user_domain($user['id'], $_POST['domain']);
}
if(isset($_REQUEST['action']) && isset($_REQUEST['onion']) && $_REQUEST['action']==='edit_onion'){
	if($error=check_csrf_error()){
		die($error);
	}
	$stmt=$db->prepare('SELECT onions.version, onions.instance FROM onions INNER JOIN users ON (users.id=onions.user_id) WHERE onions.onion = ? AND users.id = ? AND onions.enabled IN (0, 1);');
	$stmt->execute([$_REQUEST['onion'], $user['id']]);
	if($onion=$stmt->fetch(PDO::FETCH_ASSOC)){
		$stmt=$db->prepare('UPDATE onions SET enabled = ?, enable_smtp = ?, num_intros = ?, max_streams = ? WHERE onion = ?;');
		$enabled = isset($_REQUEST['enabled']) ? 1 : 0;
		$enable_smtp = isset($_REQUEST['enable_smtp']) ? 1 : 0;
		$num_intros = intval($_REQUEST['num_intros']);
		if($num_intros<3){
				$num_intros = 3;
		}elseif($onion['version']==2 && $num_intros>10){
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
		enqueue_instance_reload($onion['instance']);
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
		enqueue_instance_reload();
	}
}
print_header(_('Dashboard'), '#custom_onion:not(checked)+#private_key{display:none;}#custom_onion:checked+#private_key{display:block;}td{padding:5px}meter{width:200px}');
dashboard_menu($user, 'home.php');
if(!empty($msg)){
	echo $msg;
}
echo '<p>'.sprintf(_('Enter system account password to check your %s mail:'), $user['system_account'].'@' . ADDRESS).'</td><td><form action="squirrelmail/src/redirect.php" method="post" target="_blank"><input type="hidden" name="login_username" value="'.$user['system_account'].'"><input type="password" name="secretkey"><button type="submit">'._('Login to webmail').'</button></form></p>';
echo '<h3>'._('Onion domains').'</h3>';
echo '<table border="1">';
echo '<tr><th>'._('Onion').'</th><th>'._('Private key').'</th><th>'._('Enabled').'</th><th>'._('SMTP enabled').'</th><th>'._('Nr. of intros').'</th><th>'._('Max streams per rend circuit').'</th><th>'._('Action').'</th></tr>';
$stmt=$db->prepare('SELECT onion, private_key, enabled, enable_smtp, num_intros, max_streams FROM onions WHERE user_id = ?;');
$stmt->execute([$user['id']]);
$count_onions = 0;
while($onion=$stmt->fetch(PDO::FETCH_ASSOC)){
	++$count_onions;
	echo "<form action=\"home.php\" method=\"post\"><input type=\"hidden\" name=\"csrf_token\" value=\"$_SESSION[csrf_token]\"><input type=\"hidden\" name=\"onion\" value=\"$onion[onion]\"><tr><td><a href=\"http://$onion[onion].onion\" target=\"_blank\">$onion[onion].onion</a></td><td>";
	if(isset($_REQUEST['show_priv'])){
		echo "<pre>$onion[private_key]</pre>";
	}else{
		echo '<a href="home.php?show_priv=1">'._('Show private key').'</a>';
	}
	echo '</td><td><label><input type="checkbox" name="enabled" value="1"';
	echo $onion['enabled'] ? ' checked' : '';
	echo '>'._('Enabled').'</label></td>';
	echo '<td><label><input type="checkbox" name="enable_smtp" value="1"';
	echo $onion['enable_smtp'] ? ' checked' : '';
	echo '>'._('Enabled').'</label></td>';
	echo '<td><input type="number" name="num_intros" min="3" max="20" value="'.$onion['num_intros'].'"></td>';
	echo '<td><input type="number" name="max_streams" min="0" max="65535" value="'.$onion['max_streams'].'"></td>';
	if(in_array($onion['enabled'], [0, 1])){
		echo '<td><button type="submit" name="action" value="edit_onion">'._('Save').'</button>';
		echo '<button type="submit" name="action" value="del_onion">'._('Delete').'</button></td>';
	}else{
		echo '<td>'._('Unavailable').'</td>';
	}
	echo '</tr></form>';
}
if($count_onions<MAX_NUM_USER_ONIONS){
	echo "<form action=\"home.php\" method=\"post\"><input type=\"hidden\" name=\"csrf_token\" value=\"$_SESSION[csrf_token]\">";
	echo '<tr><td colspan="6">'._('Add additional hidden service:').'<br>';
	echo '<label><input type="radio" name="onion_type" value="3"';
	echo (!isset($_POST['onion_type']) || $_POST['onion_type']==3) ? ' checked' : '';
	echo '>'._('Random v3 Address').'</label>';
	echo '<label><input type="radio" name="onion_type" value="2"';
	echo isset($_POST['onion_type']) && $_POST['onion_type']==2 ? ' checked' : '';
	echo '>'._('Random v2 Address').'</label>';
	echo '<label><input id="custom_onion" type="radio" name="onion_type" value="custom"';
	echo isset($_POST['onion_type']) && $_POST['onion_type']==='custom' ? ' checked' : '';
	echo '>'._('Custom private key');
	echo '<textarea id="private_key" name="private_key" rows="5" cols="28">';
	echo isset($_REQUEST['private_key']) ? htmlspecialchars($_REQUEST['private_key']) : '';
	echo '</textarea>';
	echo '</label></td><td><button type="submit" name="action" value="add_onion">'._('Add onion').'</button></td></tr></form>';
}
echo '</table>';
if(MAX_NUM_USER_DOMAINS>0){
	echo '<h3>'._('Clearnet domains').'</h3>';
	echo '<table border="1">';
	echo '<tr><th>'._('Domain').'</th><th>'._('Enabled').'</th><th>'._('Action').'</th></tr>';
	$stmt=$db->prepare('SELECT domain, enabled FROM domains WHERE user_id = ?;');
	$stmt->execute([$user['id']]);
	$count_domains = 0;
	while($domain=$stmt->fetch(PDO::FETCH_ASSOC)){
		++$count_domains;
		echo "<form action=\"home.php\" method=\"post\"><input type=\"hidden\" name=\"csrf_token\" value=\"$_SESSION[csrf_token]\"><input type=\"hidden\" name=\"domain\" value=\"$domain[domain]\"><tr><td><a href=\"https://$domain[domain]\" target=\"_blank\">$domain[domain]</a></td>";
		echo '<td><label><input type="checkbox" name="enabled" value="1"';
		echo $domain['enabled'] ? ' checked' : '';
		echo '>'._('Enabled').'</label></td>';
		if(in_array($domain['enabled'], [0, 1])){
			echo '<td><button type="submit" name="action" value="edit_domain">'._('Save').'</button>';
			echo '<button type="submit" name="action" value="del_domain">'._('Delete').'</button></td>';
		}else{
			echo '<td>'._('Unavailable').'</td>';
		}
		echo '</tr></form>';
	}
	if($count_domains<MAX_NUM_USER_DOMAINS){
		echo "<form action=\"home.php\" method=\"post\"><input type=\"hidden\" name=\"csrf_token\" value=\"$_SESSION[csrf_token]\">";
		echo '<tr><td colspan="2">'._('Add additional domain:').'<br>';
		echo '<input type="text" name="domain" value="';
		echo isset($_POST['domain']) ? htmlspecialchars($_POST['domain']) : '';
		echo '">';
		echo '</td><td><button type="submit" name="action" value="add_domain">'._('Add domain').'</button></td></tr></form>';
	}
	echo '</table>';
	echo '<p>'.sprintf(_('To enable your clearnet domain, edit your DNS settings and enter %1$s as your A record and %2$s as your AAAA record. Once you have modified your DNS settings, <a href="%3$s" target="_blank">contact me</a> to configure the SSL certificate. You may also use any subdomain of %4$s'), CLEARNET_A, CLEARNET_AAAA, CONTACT_URL, CLEARNET_SUBDOMAINS).'</p>';
}
echo '<h3>'._('MySQL Database<').'/h3>';
echo '<table border="1">';
echo '<tr><th>'._('Database').'</th><th>'._('Host').'</th><th>'._('User').'</th><th>'._('Action').'</th></tr>';
$stmt=$db->prepare('SELECT mysql_database FROM mysql_databases WHERE user_id = ?;');
$stmt->execute([$user['id']]);
$count_dbs = 0;
while($mysql=$stmt->fetch(PDO::FETCH_ASSOC)){
	++$count_dbs;
	echo '<form action="home.php" method="post">';
	echo '<input type="hidden" name="csrf_token" value="'.$_SESSION['csrf_token'].'">';
	echo '<input type="hidden" name="db" value="'.$mysql['mysql_database'].'">';
	echo '<tr><td>'.htmlspecialchars($mysql['mysql_database']).'</td><td>localhost</td><td>'.htmlspecialchars($user['mysql_user']).'</td>';
	echo '<td><button type="submit" name="action" value="del_db">'._('Delete').'</button></td></tr>';
	echo '</form>';
}
echo '</table>';
if($count_dbs<MAX_NUM_USER_DBS){
	echo '<p><form action="home.php" method="post"><input type="hidden" name="csrf_token" value="'.$_SESSION['csrf_token'].'"><button type="submit" name="action" value="add_db">'._('Add new database').'</button></form></p>';
}
?>
<p><a href="password.php?type=sql"><?php echo _('Change MySQL password'); ?></a></p>
<p><?php printf(_('You can use <a href="/phpmyadmin/" target="_blank">PHPMyAdmin</a> and <a href="/adminer/?username=%s" target="_blank">Adminer</a> for web based database administration.'), rawurlencode($user['mysql_user'])); ?></p>
<h3><?php echo _('System Account'); ?></h3>
<table border="1">
<tr><th><?php echo _('Username'); ?></th><th><?php echo _('Host'); ?></th><th><?php echo _('SFTP Port'); ?></th><th><?php echo _('POP3 Port'); ?></th><th><?php echo _('IMAP Port'); ?></th><th><?php echo _('SMTP port'); ?></th></tr>
<?php
foreach(SERVERS as $server=>$tmp){
	echo "<tr><td>$user[system_account]</td><td>$server</td><td>$tmp[sftp]</td><td>$tmp[pop3]</td><td>$tmp[imap]</td><td>$tmp[smtp]</td></tr>";
}
?>
</table>
<p><a href="password.php?type=sys"><?php echo _('Change system account password'); ?></a></p>
<p><?php echo _('You can use the <a target="_blank" href="files.php">FileManager</a> for web based file management.'); ?></p>
<?php
$stmt = $db->prepare('SELECT quota_size, quota_size_used, quota_files, quota_files_used FROM disk_quota WHERE user_id = ?;');
$stmt->execute([$user['id']]);
$quota = $stmt->fetch(PDO::FETCH_ASSOC);
$quota_usage = $quota['quota_size_used'] / $quota['quota_size'];
$quota_files_usage = $quota['quota_files_used'] / $quota['quota_files'];
$usage_text = bytes_to_human_readable($quota['quota_size_used'] * 1024) . ' of ' . bytes_to_human_readable($quota['quota_size'] * 1024) . ' - ' . round($quota_usage * 100, 2).'%';
$usage_files_text = sprintf(_('%1$d of %2$d - %3$f%%'), $quota['quota_files_used'], $quota['quota_files'], round($quota_files_usage * 100, 2));
?>
<p><?php echo _('Your disk usage:'); ?> <meter value="<?php echo round($quota_usage, 2); ?>"><?php echo $usage_text; ?></meter> - <?php printf(_('%s (updated hourly)'), $usage_text); ?> <?php echo ENABLE_UPGRADES ? '<a href="upgrade.php?upgrade=1g_quota">'._('Upgrade').'</a>' : ''; ?></p>
<p><?php echo _('Your file number usage:'); ?> <meter value="<?php echo round($quota_files_usage, 2); ?>"><?php echo $usage_files_text; ?></meter> - <?php printf(_('%s (updated hourly)'), $usage_files_text); ?> <?php echo ENABLE_UPGRADES ? '<a href="upgrade.php?upgrade=100k_files_quota">'._('Upgrade').'</a>' : ''; ?></p>
<h3><?php echo _('Logs'); ?></h3>
<table border="1">
<tr><th><?php echo _('Date'); ?></th><th>access.log</th><th>error.log</th></tr>
<tr><td><?php echo _('Today'); ?></td><td><a href="log.php?type=access&amp;old=0" target="_blank">access.log</a></td><td><a href="log.php?type=error&amp;old=0" target="_blank">error.log</a></td></tr>
<tr><td><?php echo _('Yesterday'); ?></td><td><a href="log.php?type=access&amp;old=1" target="_blank">access.log</a></td><td><a href="log.php?type=error&amp;old=1" target="_blank">error.log</a></td></tr>
</table>
</body></html>
