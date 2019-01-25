<?php
include('common.php');
try{
	$db=new PDO('mysql:host=' . DBHOST . ';dbname=' . DBNAME, DBUSER, DBPASS, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_WARNING, PDO::ATTR_PERSISTENT=>PERSISTENT]);
}catch(PDOException $e){
	die('No Connection to MySQL database!');
}

//instances to reload
$reload=[];
$stmt=$db->query('SELECT id FROM service_instances WHERE reload=1;');
while($tmp=$stmt->fetch(PDO::FETCH_NUM)){
	$reload[$tmp[0]]=true;
}
$db->query('UPDATE service_instances SET reload=0 WHERE reload=1;');

//add new accounts
$del=$db->prepare("DELETE FROM new_account WHERE user_id=?;");
$enable_onion=$db->prepare("UPDATE onions SET enabled=2 WHERE onion=?;");
$approval = REQUIRE_APPROVAL ? 'WHERE new_account.approved=1': '';
$stmt=$db->query("SELECT users.system_account, users.username, new_account.password, users.php, users.autoindex, users.id, onions.onion FROM new_account INNER JOIN users ON (users.id=new_account.user_id) INNER JOIN onions ON (onions.user_id=users.id) $approval LIMIT 100;");
while($id=$stmt->fetch(PDO::FETCH_NUM)){
	$onion=$id[6];
	$system_account=$id[0];
	$firstchar=substr($system_account, 0, 1);
	$reload[$firstchar]=true;
	$enable_onion->execute([$id[6]]);
	//add and manage rights of system user
	$shell = ENABLE_SHELL_ACCESS ? '/bin/bash' : '/usr/sbin/nologin';
	exec('useradd -l -p ' . escapeshellarg($id[2]) . ' -g www-data -k /var/www/skel -m -s ' . escapeshellarg($shell) . ' ' . escapeshellarg($system_account));
	exec('/var/www/setup_chroot.sh  ' . escapeshellarg("/home/$system_account"));
	exec('grep ' . escapeshellarg($system_account) . ' /etc/passwd >> ' . escapeshellarg("/home/$system_account/etc/passwd"));
	foreach(['.ssh', 'data', 'Maildir'] as $dir){
		mkdir("/home/$system_account/$dir", 0700);
		chown("/home/$system_account/$dir", $system_account);
		chgrp("/home/$system_account/$dir", 'www-data');
	}
	foreach(['logs'] as $dir){
		mkdir("/home/$system_account/$dir", 0550);
		chown("/home/$system_account/$dir", $system_account);
		chgrp("/home/$system_account/$dir", 'www-data');
	}
	//remove from to-add queue
	$del->execute([$id[5]]);
}

//add hidden services to tor
$update_onion=$db->prepare('UPDATE onions SET private_key=?, enabled=1 WHERE onion=?;');
$stmt=$db->query('SELECT onion, private_key, version FROM onions WHERE enabled=2;');
$onions=$stmt->fetchAll(PDO::FETCH_NUM);
foreach($onions as $onion){
	$firstchar=substr($onion[0], 0, 1);
	$reload[$firstchar]=true;
	if($onion[2]==2){
		//php openssl implementation has some issues, re-export using native openssl
		$pkey=openssl_pkey_get_private($onion[1]);
		openssl_pkey_export($pkey, $exported);
		openssl_pkey_free($pkey);
		$priv_key=shell_exec('echo ' . escapeshellarg($exported) . ' | openssl rsa');
		//save hidden service
		mkdir("/var/lib/tor-instances/$firstchar/hidden_service_$onion[0].onion", 0700);
		file_put_contents("/var/lib/tor-instances/$firstchar/hidden_service_$onion[0].onion/private_key", $priv_key);
		chmod("/var/lib/tor-instances/$firstchar/hidden_service_$onion[0].onion/private_key", 0600);
		chown("/var/lib/tor-instances/$firstchar/hidden_service_$onion[0].onion/", "_tor-$firstchar");
		chown("/var/lib/tor-instances/$firstchar/hidden_service_$onion[0].onion/private_key", "_tor-$firstchar");
		chgrp("/var/lib/tor-instances/$firstchar/hidden_service_$onion[0].onion/", "_tor-$firstchar");
		chgrp("/var/lib/tor-instances/$firstchar/hidden_service_$onion[0].onion/private_key", "_tor-$firstchar");
		$update_onion->execute([$priv_key, $onion[0]]);
	}elseif($onion[2]==3){
		$priv_key=base64_decode($onion[1]);
		//save hidden service
		mkdir("/var/lib/tor-instances/$firstchar/hidden_service_$onion[0].onion", 0700);
		file_put_contents("/var/lib/tor-instances/$firstchar/hidden_service_$onion[0].onion/hs_ed25519_secret_key", $priv_key);
		chmod("/var/lib/tor-instances/$firstchar/hidden_service_$onion[0].onion/hs_ed25519_secret_key", 0600);
		chown("/var/lib/tor-instances/$firstchar/hidden_service_$onion[0].onion/", "_tor-$firstchar");
		chown("/var/lib/tor-instances/$firstchar/hidden_service_$onion[0].onion/hs_ed25519_secret_key", "_tor-$firstchar");
		chgrp("/var/lib/tor-instances/$firstchar/hidden_service_$onion[0].onion/", "_tor-$firstchar");
		chgrp("/var/lib/tor-instances/$firstchar/hidden_service_$onion[0].onion/hs_ed25519_secret_key", "_tor-$firstchar");
		$update_onion->execute([$onion[1], $onion[0]]);
	}
}

//delete old accounts
$del=$db->prepare("DELETE FROM users WHERE id=?;");
$stmt=$db->query("SELECT system_account, id, mysql_user FROM users WHERE todelete=1 LIMIT 100;");
$accounts=$stmt->fetchAll(PDO::FETCH_NUM);
$mark_onions=$db->prepare('UPDATE onions SET enabled=-1 WHERE user_id=? AND enabled!=-2;');
foreach($accounts as $account){
	$firstchar=substr($account[0], 0, 1);
	$reload[$firstchar]=true;
	$mark_onions->execute([$account[1]]);
}

//delete hidden services from tor
$del_onions=$db->prepare('DELETE FROM onions WHERE onion=?;');
$stmt=$db->query('SELECT onion FROM onions WHERE enabled=-1;');
$onions=$stmt->fetchAll(PDO::FETCH_NUM);
foreach($onions as $onion){
	$firstchar=substr($onion[0], 0, 1);
	$reload[$firstchar]=true;
	if(file_exists("/var/lib/tor-instances/$firstchar/hidden_service_$onion[0].onion/")){
		if(file_exists("/var/lib/tor-instances/$firstchar/hidden_service_$onion[0].onion/authorized_clients/")){
			foreach(glob("/var/lib/tor-instances/$firstchar/hidden_service_$onion[0].onion/authorized_clients/*") as $file){
				unlink($file);
			}
			rmdir("/var/lib/tor-instances/$firstchar/hidden_service_$onion[0].onion/authorized_clients");
		}
		foreach(glob("/var/lib/tor-instances/$firstchar/hidden_service_$onion[0].onion/*") as $file){
			unlink($file);
		}
		rmdir("/var/lib/tor-instances/$firstchar/hidden_service_$onion[0].onion/");
	}
	$del_onions->execute([$onion[0]]);
}

//reload services
if(!empty($reload)){
	rewrite_nginx_config($db);
}
foreach($reload as $key => $val){
	rewrite_php_config($db, $key);
	rewrite_torrc($db, $key);
}

//continue deleting old accounts
$stmt=$db->prepare('SELECT mysql_database FROM mysql_databases WHERE user_id=?;');
$drop_user=$db->prepare("DROP USER ?@'%';");
foreach($accounts as $account){
	//kill processes of the user to allow deleting system users
	exec('skill -u ' . escapeshellarg($account[0]));
	//delete user and group
	exec('userdel -rf ' . escapeshellarg($account[0]));
	//delete all log files
	if(file_exists("/var/log/nginx/access_$account[0].log")){
		unlink("/var/log/nginx/access_$account[0].log");
	}
	if(file_exists("/var/log/nginx/access_$account[0].log.1")){
		unlink("/var/log/nginx/access_$account[0].log.1");
	}
	if(file_exists("/var/log/nginx/error_$account[0].log")){
		unlink("/var/log/nginx/error_$account[0].log");
	}
	if(file_exists("/var/log/nginx/error_$account[0].log.1")){
		unlink("/var/log/nginx/error_$account[0].log.1");
	}
	//delete user from database
	$drop_user->execute([$account[2]]);
	$stmt->execute([$account[1]]);
	while($tmp=$stmt->fetch(PDO::FETCH_NUM)){
		$db->exec('DROP DATABASE IF EXISTS `'.preg_replace('/[^a-z0-9]/i', '', $tmp[0]).'`;');
	}
	$db->exec('FLUSH PRIVILEGES;');
	//delete user from user database
	$del->execute([$account[1]]);
}

// update passwords
$stmt=$db->query("SELECT users.system_account, pass_change.password, users.id FROM pass_change INNER JOIN users ON (users.id=pass_change.user_id) LIMIT 100;");
$del=$db->prepare("DELETE FROM pass_change WHERE user_id=?;");
while($account=$stmt->fetch(PDO::FETCH_NUM)){
	exec('usermod -p '. escapeshellarg($account[1]) . ' ' . escapeshellarg($account[0]));
	$del->execute([$account[2]]);
}
