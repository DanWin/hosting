<?php
require('common.php');
$db = get_db_instance();

//instances to reload
$reload=[];
$stmt=$db->query('SELECT id FROM service_instances WHERE reload=1;');
while($tmp=$stmt->fetch(PDO::FETCH_NUM)){
	$reload[$tmp[0]]=true;
}
$db->query('UPDATE service_instances SET reload=0 WHERE reload=1;');

//add new accounts
$newest_account=$db->query('SELECT system_account FROM users WHERE id NOT IN (SELECT user_id FROM new_account) AND todelete!=1 ORDER BY id DESC LIMIT 1;');
$last_account = $newest_account->fetch(PDO::FETCH_NUM);
if(is_array($last_account)){
	$last_account = $last_account[0];
} else {
	$last_account = '';
}
$del=$db->prepare("DELETE FROM new_account WHERE user_id=?;");
$approval = REQUIRE_APPROVAL ? 'WHERE new_account.approved=1': '';
$stmt=$db->query("SELECT users.system_account, new_account.password, users.id, users.instance FROM new_account INNER JOIN users ON (users.id=new_account.user_id) $approval LIMIT 100;");

while($account=$stmt->fetch(PDO::FETCH_ASSOC)){
	$system_account = basename($account['system_account']);
	if($system_account !== $account['system_account']){
		echo "ERROR: Account $account[system_account] looks strange\n";
		continue;
	}
	if(posix_getpwnam($system_account) !== false){
		echo "ERROR: Account $account[system_account] already exists\n";
		continue;
	}
	$reload[$account['instance']] = true;
	//add and manage rights of system user
	$shell = ENABLE_SHELL_ACCESS ? '/bin/bash' : '/usr/sbin/nologin';
	exec('useradd -l -g www-data -k /var/www/skel -m -s ' . escapeshellarg($shell) . ' ' . escapeshellarg($system_account));
	update_system_user_password($system_account, $account['password']);
	setup_chroot($system_account, $last_account);
	$last_account = $system_account;
	//remove from to-add queue
	$del->execute([$account['id']]);
}

//delete old accounts
$del=$db->prepare("DELETE FROM users WHERE id=?;");
$stmt=$db->query("SELECT system_account, id, mysql_user, instance FROM users WHERE todelete=1 LIMIT 100;");
$accounts=$stmt->fetchAll(PDO::FETCH_ASSOC);
$mark_onions=$db->prepare('UPDATE onions SET enabled=-1 WHERE user_id=? AND enabled!=-2;');
foreach($accounts as $account){
	$system_account = sanitize_system_account($account['system_account']);
	if($system_account === false){
		echo "ERROR: Account $account[system_account] looks strange\n";
		continue;
	}
	$reload[$account['instance']]=true;
	$mark_onions->execute([$account['id']]);
}

//delete hidden services from tor
$del_onions=$db->prepare('DELETE FROM onions WHERE onion=?;');
$stmt=$db->query('SELECT onion, instance FROM onions WHERE enabled=-1;');
$onions=$stmt->fetchAll(PDO::FETCH_ASSOC);
foreach($onions as $onion){
	$reload[$onion['instance']] = true;
	if(is_dir("/var/lib/tor-instances/$onion[instance]/hidden_service_$onion[onion].onion/")){
		if(is_dir("/var/lib/tor-instances/$onion[instance]/hidden_service_$onion[onion].onion/authorized_clients/")){
			foreach(glob("/var/lib/tor-instances/$onion[instance]/hidden_service_$onion[onion].onion/authorized_clients/*") as $file){
				unlink($file);
			}
			rmdir("/var/lib/tor-instances/$onion[instance]/hidden_service_$onion[onion].onion/authorized_clients");
		}
		foreach(glob("/var/lib/tor-instances/$onion[instance]/hidden_service_$onion[onion].onion/*") as $file){
			unlink($file);
		}
		rmdir("/var/lib/tor-instances/$onion[instance]/hidden_service_$onion[onion].onion/");
	}
	$del_onions->execute([$onion['onion']]);
}

//reload services
if(!empty($reload)){
	rewrite_nginx_config();
}
foreach($reload as $key => $val){
	rewrite_php_config($key);
	rewrite_torrc($key);
}

//continue deleting old accounts
$stmt=$db->prepare('SELECT mysql_database FROM mysql_databases WHERE user_id=?;');
$drop_user=$db->prepare("DROP USER ?@'%';");
foreach($accounts as $account){
	$system_account = sanitize_system_account($account['system_account']);
	if($system_account === false){
		echo "ERROR: Account $account[system_account] looks strange\n";
		continue;
	}
	//kill processes of the user to allow deleting system users
	exec('skill -u ' . escapeshellarg($system_account));
	//delete user and group
	exec('userdel -rf ' . escapeshellarg($system_account));
	//delete all log files
	$log_files = [
		"/var/log/nginx/access_".$system_account.".log",
		"/var/log/nginx/access_".$system_account.".log.1",
		"/var/log/nginx/error_".$system_account.".log",
		"/var/log/nginx/error_".$system_account.".log.1"
	];
	foreach($log_files as $log_file){
		if(file_exists($log_file)){
			unlink($log_file);
		}
	}
	//delete user from database
	$drop_user->execute([$account['mysql_user']]);
	$stmt->execute([$account['id']]);
	while($tmp=$stmt->fetch(PDO::FETCH_ASSOC)){
		$db->exec('DROP DATABASE IF EXISTS `'.preg_replace('/[^a-z0-9]/i', '', $tmp['mysql_database']).'`;');
	}
	$db->exec('FLUSH PRIVILEGES;');
	//delete user from user database
	$del->execute([$account['id']]);
}

// update passwords
$stmt=$db->query("SELECT users.system_account, pass_change.password, users.id FROM pass_change INNER JOIN users ON (users.id=pass_change.user_id) LIMIT 100;");
$del=$db->prepare("DELETE FROM pass_change WHERE user_id=?;");
while($account=$stmt->fetch(PDO::FETCH_ASSOC)){
	$system_account = sanitize_system_account($account['system_account']);
	if($system_account === false){
		echo "ERROR: Account $account[system_account] looks strange\n";
		continue;
	}
	update_system_user_password($system_account, $account['password']);
	$del->execute([$account['id']]);
}

//update quotas
$stmt=$db->query('SELECT users.system_account, disk_quota.quota_files, disk_quota.quota_size, users.id FROM disk_quota INNER JOIN users ON (users.id=disk_quota.user_id) WHERE disk_quota.updated = 1 AND users.id NOT IN (SELECT user_id FROM new_account) AND users.todelete!=1;');
$updated=$db->prepare("UPDATE disk_quota SET updated = 0 WHERE user_id=?;");
while($account=$stmt->fetch(PDO::FETCH_ASSOC)){
	$system_account = sanitize_system_account($account['system_account']);
	if($system_account === false){
		echo "ERROR: Account $account[system_account] looks strange\n";
		continue;
	}
	exec('quotatool -u '. escapeshellarg($system_account) . ' -i -q ' . escapeshellarg($account['quota_files']) . ' -l ' . escapeshellarg($account['quota_files']) . ' ' . HOME_MOUNT_PATH);
	exec('quotatool -u '. escapeshellarg($system_account) . ' -b -q ' . escapeshellarg($account['quota_size']) . ' -l ' . escapeshellarg($account['quota_size']) . ' ' . HOME_MOUNT_PATH);
	$updated->execute([$account['id']]);
}
