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
	exec('useradd -l -p ' . escapeshellarg($id[2]) . ' -g www-data -k /var/www/skel -m -s /usr/sbin/nologin ' . escapeshellarg($system_account));
	chown("/home/$system_account", 'root');
	chgrp("/home/$system_account", 'www-data');
	chmod("/home/$system_account", 0550);
	foreach(['.ssh', 'data', 'Maildir', 'tmp'] as $dir){
		mkdir("/home/$system_account/$dir", 0700);
		chown("/home/$system_account/$dir", $system_account);
		chgrp("/home/$system_account/$dir", 'www-data');
	}
	foreach(['logs'] as $dir){
		mkdir("/home/$system_account/$dir", 0550);
		chown("/home/$system_account/$dir", $system_account);
		chgrp("/home/$system_account/$dir", 'www-data');
	}

//configuration for services

if($id[3]>0){
$php_location="
		location ~ [^/]\.php(/|\$) {
			include snippets/fastcgi-php.conf;
			fastcgi_pass unix:/run/php/$system_account;
		}
";
}else{
	$php_location='';
}
if($id[4]){
	$autoindex='on';
}else{
	$autoindex='off';
}

$nginx="server {
	listen [::]:80;
	listen unix:/var/run/nginx/$system_account;
	root /home/$system_account/www;
	server_name $onion.onion *.$onion.onion;
	access_log /var/log/nginx/access_$system_account.log custom buffer=8k flush=1m;
	access_log /home/$system_account/logs/access.log custom buffer=8k flush=1m;
	error_log /var/log/nginx/error_$system_account.log notice;
	error_log /home/$system_account/logs/error.log notice;
	disable_symlinks on from=/home/$system_account;
	autoindex $autoindex;
	location / {
		try_files \$uri \$uri/ =404;$php_location
	}
}
";

$php="[$system_account]
user = $system_account
group = www-data
listen = /run/php/$system_account
listen.owner = www-data
listen.group = www-data
listen.mode = 0660
pm = ondemand
pm.max_children = 20
pm.process_idle_timeout = 10s;
php_admin_value[sendmail_path] = '/usr/bin/php /var/www/sendmail_wrapper.php \"$system_account <$system_account@" . ADDRESS . ">\" | /usr/sbin/sendmail -t -i'
php_admin_value[memory_limit] = 256M
php_admin_value[disable_functions] = exec,link,passthru,pcntl_alarm,pcntl_async_signals,pcntl_exec,pcntl_fork,pcntl_get_last_error,pcntl_getpriority,pcntl_setpriority,pcntl_signal,pcntl_signal_dispatch,pcntl_signal_get_handler,pcntl_sigprocmask,pcntl_sigtimedwait,pcntl_sigwaitinfo,pcntl_strerror,pcntl_waitpid,pcntl_wait,pcntl_wexitstatus,pcntl_wifcontinued,pcntl_wifexited,pcntl_wifsignaled,pcntl_wifstopped,pcntl_wstopsig,pcntl_wtermsig,popen,posix_ctermid,posix_getgrgid,posix_getgrnam,posix_getpgid,posix_getpwnam,posix_getpwuid,posix_getrlimit,posix_getsid,posix_kill,posix_setegid,posix_seteuid,posix_setgid,posix_setpgid,posix_setrlimit,posix_setuid,posix_ttyname,posix_uname,proc_open,putenv,shell_exec,socket_listen,socket_create_listen,socket_bind,stream_socket_server,symlink,system
php_admin_value[open_basedir] = /home/$system_account
php_admin_value[upload_tmp_dir] = /home/$system_account/tmp
php_admin_value[soap.wsdl_cache_dir] = /home/$system_account/tmp
php_admin_value[session.save_path] = /home/$system_account/tmp
";

	//save configuration files
	file_put_contents("/etc/nginx/sites-enabled/$system_account", $nginx);
	foreach(PHP_VERSIONS as $key=>$version){
		if($id[3]==$key){
			file_put_contents("/etc/php/$version/fpm/pool.d/$firstchar/$system_account.conf", $php);
			break;
		}
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
		openssl_pkey_export_to_file($pkey, 'key.tmp');
		openssl_pkey_free($pkey);
		$priv_key=shell_exec('openssl rsa < key.tmp');
		unlink('key.tmp');
		//save hidden service
		mkdir("/var/lib/tor-instances/$firstchar/hidden_service_$onion[0].onion", 0700);
		file_put_contents("/var/lib/tor-instances/$firstchar/hidden_service_$onion[0].onion/hostname", "$onion[0].onion\n");
		file_put_contents("/var/lib/tor-instances/$firstchar/hidden_service_$onion[0].onion/private_key", $priv_key);
		chmod("/var/lib/tor-instances/$firstchar/hidden_service_$onion[0].onion/hostname", 0600);
		chmod("/var/lib/tor-instances/$firstchar/hidden_service_$onion[0].onion/private_key", 0600);
		chown("/var/lib/tor-instances/$firstchar/hidden_service_$onion[0].onion/", "_tor-$firstchar");
		chown("/var/lib/tor-instances/$firstchar/hidden_service_$onion[0].onion/hostname", "_tor-$firstchar");
		chown("/var/lib/tor-instances/$firstchar/hidden_service_$onion[0].onion/private_key", "_tor-$firstchar");
		chgrp("/var/lib/tor-instances/$firstchar/hidden_service_$onion[0].onion/", "_tor-$firstchar");
		chgrp("/var/lib/tor-instances/$firstchar/hidden_service_$onion[0].onion/hostname", "_tor-$firstchar");
		chgrp("/var/lib/tor-instances/$firstchar/hidden_service_$onion[0].onion/private_key", "_tor-$firstchar");
		$update_onion->execute([$priv_key, $onion[0]]);
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
	//delete config files
	foreach(DISABLED_PHP_VERSIONS as $v){
		// new naming schema
		if(file_exists("/etc/php/$v/fpm/pool.d/$firstchar/$account[0].conf")){
			unlink("/etc/php/$v/fpm/pool.d/$firstchar/$account[0].conf");
		}
		// old naming schema
		if(file_exists("/etc/php/$v/fpm/pool.d/$firstchar/".substr($account[0], 0, 16).".conf")){
			unlink("/etc/php/$v/fpm/pool.d/$firstchar/".substr($account[0], 0, 16).".conf");
		}
	}
	foreach(PHP_VERSIONS as $v){
		// new naming schema
		if(file_exists("/etc/php/$v/fpm/pool.d/$firstchar/$account[0].conf")){
			unlink("/etc/php/$v/fpm/pool.d/$firstchar/$account[0].conf");
		}
		// old naming schema
		if(file_exists("/etc/php/$v/fpm/pool.d/$firstchar/".substr($account[0], 0, 16).".conf")){
			unlink("/etc/php/$v/fpm/pool.d/$firstchar/".substr($account[0], 0, 16).".conf");
		}
	}
	if(file_exists("/etc/nginx/sites-enabled/$account[0]")){
		unlink("/etc/nginx/sites-enabled/$account[0]");
	}
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
		foreach(glob("/var/lib/tor-instances/$firstchar/hidden_service_$onion[0].onion/*") as $file){
			unlink($file);
		}
		rmdir("/var/lib/tor-instances/$firstchar/hidden_service_$onion[0].onion/");
	}
	$del_onions->execute([$onion[0]]);
}



//reload services
if(!empty($reload)){
	exec('service nginx reload');
	foreach(DISABLED_PHP_VERSIONS as $version){
		exec("service php$version-fpm reload");
	}
}
foreach($reload as $key => $val){
	foreach(PHP_VERSIONS as $version){
		exec("service php$version-fpm@$key restart");
	}
	rewrite_torrc($db, $key);
}

//continue deleting old accounts
$stmt=$db->prepare('SELECT mysql_database FROM mysql_databases WHERE user_id=?;');
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
	$db->exec("DROP USER '$account[2]'@'%';");
	$stmt->execute([$account[1]]);
	while($tmp=$stmt->fetch(PDO::FETCH_NUM)){
		$db->exec("DROP DATABASE IF EXISTS `$tmp[0]`;");
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
