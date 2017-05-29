<?php
include('common.php');
try{
	$db=new PDO('mysql:host=' . DBHOST . ';dbname=' . DBNAME, DBUSER, DBPASS, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_WARNING, PDO::ATTR_PERSISTENT=>PERSISTENT]);
}catch(PDOException $e){
	die('No Connection to MySQL database!');
}
$reload=[];

//add new accounts
$del=$db->prepare("DELETE FROM new_account WHERE onion=?;");
$update_priv=$db->prepare("UPDATE users SET private_key=? WHERE onion=?;");
$stmt=$db->query("SELECT new_account.onion, users.username, new_account.password, users.private_key, users.php, users.autoindex FROM new_account INNER JOIN users ON (users.onion=new_account.onion) LIMIT 100;");
while($id=$stmt->fetch(PDO::FETCH_NUM)){
	$onion=$id[0];
	$firstchar=substr($onion, 0, 1);
	$reload[$firstchar]=true;
	//php openssl implementation has some issues, re-export using native openssl
	$pkey=openssl_pkey_get_private($id[3]);
	openssl_pkey_export_to_file($pkey, 'key.tmp');
	openssl_pkey_free($pkey);
	$priv_key=shell_exec('openssl rsa < key.tmp');
	unlink('key.tmp');
	$update_priv->execute([$priv_key, $onion]);
	//add and manage rights of system user
	exec('useradd -l -p '. escapeshellarg($id[2]) . " -g www-data -k /var/www/skel -m -s /usr/sbin/nologin $onion.onion");
	exec("chown root:www-data /home/$onion.onion");
	exec("chmod 550 /home/$onion.onion");

//configuration for services

if($id[4]>0){
$php_location="
		location ~ [^/]\.php(/|\$) {
			include snippets/fastcgi-php.conf;
			fastcgi_pass unix:/run/php/$onion;
		}
";
}else{
	$php_location='';
}
if($id[5]!=0){
	$autoindex='on';
}else{
	$autoindex='off';
}

$nginx="server {
	listen 80;
	root /home/$onion.onion/www;
	server_name $onion.onion *.$onion.onion;
	access_log /var/log/nginx/access_$onion.onion.log custom;
	error_log /var/log/nginx/error_$onion.onion.log notice;
	disable_symlinks on from=/home/$onion.onion/www;
	autoindex $autoindex;
	location / {
		try_files \$uri \$uri/ =404;$php_location
	}
}
";

$php="[$onion]
user = $onion.onion
group = www-data
listen = /run/php/$onion
listen.owner = www-data
listen.group = www-data
listen.mode = 0660
pm = ondemand
pm.max_children = 8
pm.process_idle_timeout = 10s;
php_admin_value[sendmail_path] = '/usr/bin/php /var/www/sendmail_wrapper.php \"$onion.onion <$onion.onion@" . ADDRESS . ">\" | /usr/sbin/sendmail -t -i'
php_admin_value[memory_limit] = 128M
php_admin_value[disable_functions] = pcntl_alarm,pcntl_fork,pcntl_waitpid,pcntl_wait,pcntl_wifexited,pcntl_wifstopped,pcntl_wifsignaled,pcntl_wifcontinued,pcntl_wexitstatus,pcntl_wtermsig,pcntl_wstopsig,pcntl_signal,pcntl_signal_get_handler,pcntl_signal_dispatch,pcntl_get_last_error,pcntl_strerror,pcntl_sigprocmask,pcntl_sigwaitinfo,pcntl_sigtimedwait,pcntl_exec,pcntl_getpriority,pcntl_setpriority,pcntl_async_signals,exec,passthru,shell_exec,system,popen,proc_open,socket_listen,socket_create_listen,socket_bind,stream_socket_server,fsockopen,pfsockopen,posix_kill,php_uname,link,symlink,posix_uname
php_admin_value[open_basedir] = /home/$onion.onion
php_admin_value[upload_tmp_dir] = /home/$onion.onion/tmp
php_admin_value[soap.wsdl_cache_dir] = /home/$onion.onion/tmp
php_admin_value[session.save_path] = /home/$onion.onion/tmp
";

	//save configuration files
	file_put_contents("/etc/nginx/sites-enabled/$onion.onion", $nginx);
	if($id[4]==1){
		file_put_contents("/etc/php/7.0/fpm/pool.d/$firstchar/$onion.conf", $php);
	}elseif($id[4]==2){
		file_put_contents("/etc/php/7.1/fpm/pool.d/$firstchar/$onion.conf", $php);
	}
	//save hidden service
	mkdir("/var/lib/tor-instances/$firstchar/hidden_service_$onion.onion");
	file_put_contents("/var/lib/tor-instances/$firstchar/hidden_service_$onion.onion/hostname", $onion);
	file_put_contents("/var/lib/tor-instances/$firstchar/hidden_service_$onion.onion/private_key", $priv_key);
	chmod("/var/lib/tor-instances/$firstchar/hidden_service_$onion.onion/", 0700);
	chmod("/var/lib/tor-instances/$firstchar/hidden_service_$onion.onion/hostname", 0600);
	chmod("/var/lib/tor-instances/$firstchar/hidden_service_$onion.onion/private_key", 0600);
	chown("/var/lib/tor-instances/$firstchar/hidden_service_$onion.onion/", "_tor-$firstchar");
	chown("/var/lib/tor-instances/$firstchar/hidden_service_$onion.onion/hostname", "_tor-$firstchar");
	chown("/var/lib/tor-instances/$firstchar/hidden_service_$onion.onion/private_key", "_tor-$firstchar");
	chgrp("/var/lib/tor-instances/$firstchar/hidden_service_$onion.onion/", "_tor-$firstchar");
	chgrp("/var/lib/tor-instances/$firstchar/hidden_service_$onion.onion/hostname", "_tor-$firstchar");
	chgrp("/var/lib/tor-instances/$firstchar/hidden_service_$onion.onion/private_key", "_tor-$firstchar");
	//add hidden service to torrc
	$torrc=file_get_contents("/etc/tor/instances/$firstchar/torrc");
	$torrc.="HiddenServiceDir /var/lib/tor-instances/$firstchar/hidden_service_$onion.onion/\nHiddenServicePort 80 127.0.0.1:80\nHiddenServicePort 25 127.0.0.1:25\n";
	file_put_contents("/etc/tor/instances/$firstchar/torrc", $torrc);
	//remove from to-add queue
	$del->execute([$onion]);
}

//delete old accounts
$del=$db->prepare("DELETE FROM users WHERE onion=?");
$stmt=$db->query("SELECT onion FROM del_account LIMIT 100;");
$onions=$stmt->fetchAll(PDO::FETCH_NUM);
foreach($onions as $onion){
	$firstchar=substr($onion[0], 0, 1);
	$reload[$firstchar]=true;
	//delete config files
	if(file_exists("/etc/php/7.0/fpm/pool.d/$firstchar/$onion[0].conf")){
		unlink("/etc/php/7.0/fpm/pool.d/$firstchar/$onion[0].conf");
	}
	if(file_exists("/etc/php/7.1/fpm/pool.d/$firstchar/$onion[0].conf")){
		unlink("/etc/php/7.1/fpm/pool.d/$firstchar/$onion[0].conf");
	}
	unlink("/etc/nginx/sites-enabled/$onion[0].onion");
	//clean torrc from user
	$torrc=file_get_contents("/etc/tor/instances/$firstchar/torrc");
	$torrc=str_replace("HiddenServiceDir /var/lib/tor-instances/$firstchar/hidden_service_$onion[0].onion/\nHiddenServicePort 80 127.0.0.1:80\nHiddenServicePort 25 127.0.0.1:25\n", '', $torrc);
	file_put_contents("/etc/tor/instances/$firstchar/torrc", $torrc);
	//delete hidden service from tor
	unlink("/var/lib/tor-instances/$firstchar/hidden_service_$onion[0].onion/hostname");
	unlink("/var/lib/tor-instances/$firstchar/hidden_service_$onion[0].onion/private_key");
	rmdir("/var/lib/tor-instances/$firstchar/hidden_service_$onion[0].onion/");
}

//reload services
foreach($reload as $key => $val){
	exec('service nginx reload');
	exec("service php7.0-fpm@$key reload");
	exec("service php7.1-fpm@$key reload");
	exec("service tor@$key reload");
}

//continue deleting old accounts
foreach($onions as $onion){
	//kill processes of the user to allow deleting system users
	exec("skill -u $onion[0].onion");
	//delete user and group
	exec("userdel -rf $onion[0].onion");
	//delete all log files
	exec("rm -f /var/log/nginx/*$onion[0].onion.log*");
	//delete user from database
	$db->exec("DROP USER '$onion[0].onion'@'localhost';");
	$db->exec("DROP DATABASE IF EXISTS `$onion[0]`;");
	$db->exec('FLUSH PRIVILEGES;');
	//delete user from user database
	$del->execute([$onion[0]]);
}

// update passwords
$stmt=$db->query("SELECT onion, password FROM pass_change LIMIT 100;");
$del=$db->prepare("DELETE FROM pass_change WHERE onion=?;");
while($onion=$stmt->fetch(PDO::FETCH_NUM)){
	exec('usermod -p '. escapeshellarg($onion[1]) . " $onion[0].onion");
	$del->execute([$onion[0]]);
}
?>
