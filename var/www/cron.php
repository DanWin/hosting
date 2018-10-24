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
$update_priv=$db->prepare("UPDATE onions SET private_key=? WHERE user_id=?;");
$approval = REQUIRE_APPROVAL ? 'WHERE new_account.approved=1': '';
$stmt=$db->query("SELECT users.system_account, users.username, new_account.password, onions.private_key, users.php, users.autoindex, users.id, onions.onion FROM new_account INNER JOIN users ON (users.id=new_account.user_id) INNER JOIN onions ON (onions.user_id=users.id) $approval LIMIT 100;");
while($id=$stmt->fetch(PDO::FETCH_NUM)){
	$onion=$id[7];
	$system_account=$id[0];
	$firstchar=substr($system_account, 0, 1);
	$reload[$firstchar]=true;
	//php openssl implementation has some issues, re-export using native openssl
	$pkey=openssl_pkey_get_private($id[3]);
	openssl_pkey_export_to_file($pkey, 'key.tmp');
	openssl_pkey_free($pkey);
	$priv_key=shell_exec('openssl rsa < key.tmp');
	unlink('key.tmp');
	$update_priv->execute([$priv_key, $id[6]]);
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

if($id[4]>0){
$php_location="
		location ~ [^/]\.php(/|\$) {
			include snippets/fastcgi-php.conf;
			fastcgi_pass unix:/run/php/$system_account;
		}
";
}else{
	$php_location='';
}
if($id[5]){
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
		if($id[4]==$key){
			file_put_contents("/etc/php/$version/fpm/pool.d/$firstchar/$system_account.conf", $php);
			break;
		}
	}
	//save hidden service
	mkdir("/var/lib/tor-instances/$firstchar/hidden_service_$onion.onion", 0700);
	file_put_contents("/var/lib/tor-instances/$firstchar/hidden_service_$onion.onion/hostname", "$onion.onion\n");
	file_put_contents("/var/lib/tor-instances/$firstchar/hidden_service_$onion.onion/private_key", $priv_key);
	chmod("/var/lib/tor-instances/$firstchar/hidden_service_$onion.onion/hostname", 0600);
	chmod("/var/lib/tor-instances/$firstchar/hidden_service_$onion.onion/private_key", 0600);
	chown("/var/lib/tor-instances/$firstchar/hidden_service_$onion.onion/", "_tor-$firstchar");
	chown("/var/lib/tor-instances/$firstchar/hidden_service_$onion.onion/hostname", "_tor-$firstchar");
	chown("/var/lib/tor-instances/$firstchar/hidden_service_$onion.onion/private_key", "_tor-$firstchar");
	chgrp("/var/lib/tor-instances/$firstchar/hidden_service_$onion.onion/", "_tor-$firstchar");
	chgrp("/var/lib/tor-instances/$firstchar/hidden_service_$onion.onion/hostname", "_tor-$firstchar");
	chgrp("/var/lib/tor-instances/$firstchar/hidden_service_$onion.onion/private_key", "_tor-$firstchar");
	//remove from to-add queue
	$del->execute([$id[6]]);
}

//delete old accounts
$del=$db->prepare("DELETE FROM users WHERE id=?;");
$stmt=$db->query("SELECT system_account, id, mysql_user FROM users WHERE todelete=1 LIMIT 100;");
$onions=$stmt->fetchAll(PDO::FETCH_NUM);
$stmt=$db->prepare('SELECT onion FROM onions WHERE user_id=?;');
$del_onions=$db->prepare('DELETE FROM onions WHERE user_id=?;');
foreach($onions as $onion){
	$firstchar=substr($onion[0], 0, 1);
	$reload[$firstchar]=true;
	//delete config files
	foreach(PHP_VERSIONS as $v){
		// new naming schema
		if(file_exists("/etc/php/$v/fpm/pool.d/$firstchar/$onion[0].conf")){
			unlink("/etc/php/$v/fpm/pool.d/$firstchar/$onion[0].conf");
		}
		// old naming schema
		if(file_exists("/etc/php/$v/fpm/pool.d/$firstchar/".substr($onion[0], 0, 16).".conf")){
			unlink("/etc/php/$v/fpm/pool.d/$firstchar/".substr($onion[0], 0, 16).".conf");
		}
	}
	if(file_exists("/etc/nginx/sites-enabled/$onion[0]")){
		unlink("/etc/nginx/sites-enabled/$onion[0]");
	}
	$stmt->execute([$onion[1]]);
	while($tmp=$stmt->fetch(PDO::FETCH_NUM)){
		//delete hidden service from tor
		if(file_exists("/var/lib/tor-instances/$firstchar/hidden_service_$tmp[0].onion/")){
			unlink("/var/lib/tor-instances/$firstchar/hidden_service_$tmp[0].onion/hostname");
			unlink("/var/lib/tor-instances/$firstchar/hidden_service_$tmp[0].onion/private_key");
			rmdir("/var/lib/tor-instances/$firstchar/hidden_service_$tmp[0].onion/");
		}
	}
	$del_onions->execute([$onion[1]]);
}

//reload services
if(!empty($reload)){
	exec('service nginx reload');
}
foreach($reload as $key => $val){
	foreach(PHP_VERSIONS as $version){
		exec("service php$version-fpm@$key restart");
	}
	rewrite_torrc($db, $key);
}

//continue deleting old accounts
$stmt=$db->prepare('SELECT mysql_database FROM mysql_databases WHERE user_id=?;');
foreach($onions as $onion){
	//kill processes of the user to allow deleting system users
	exec('skill -u ' . escapeshellarg($onion[0]));
	//delete user and group
	exec('userdel -rf ' . escapeshellarg($onion[0]));
	//delete all log files
	if(file_exists("/var/log/nginx/access_$onion[0].log")){
		unlink("/var/log/nginx/access_$onion[0].log");
	}
	if(file_exists("/var/log/nginx/access_$onion[0].log.1")){
		unlink("/var/log/nginx/access_$onion[0].log.1");
	}
	if(file_exists("/var/log/nginx/error_$onion[0].log")){
		unlink("/var/log/nginx/error_$onion[0].log");
	}
	if(file_exists("/var/log/nginx/error_$onion[0].log.1")){
		unlink("/var/log/nginx/error_$onion[0].log.1");
	}
	//delete user from database
	$db->exec("DROP USER '$onion[2]'@'%';");
	$stmt->execute([$onion[1]]);
	while($tmp=$stmt->fetch(PDO::FETCH_NUM)){
		$db->exec("DROP DATABASE IF EXISTS `$tmp[0]`;");
	}
	$db->exec('FLUSH PRIVILEGES;');
	//delete user from user database
	$del->execute([$onion[1]]);
}

// update passwords
$stmt=$db->query("SELECT users.system_account, pass_change.password, users.id FROM pass_change INNER JOIN users ON (users.id=pass_change.user_id) LIMIT 100;");
$del=$db->prepare("DELETE FROM pass_change WHERE user_id=?;");
while($onion=$stmt->fetch(PDO::FETCH_NUM)){
	exec('usermod -p '. escapeshellarg($onion[1]) . ' ' . escapeshellarg($onion[0]));
	$del->execute([$onion[2]]);
}
