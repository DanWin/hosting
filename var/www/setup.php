<?php
require('common.php');
if(!extension_loaded('pdo_mysql')){
	die("Error: You need to install and enable the PDO php module\n");
}
try{
	$db=new PDO('mysql:host=' . DBHOST . ';dbname=' . DBNAME . ';charset=utf8mb4', DBUSER, DBPASS, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_WARNING, PDO::ATTR_PERSISTENT=>PERSISTENT]);
}catch(PDOException $e){
	try{
		//Attempt to create database
		$db=new PDO('mysql:host=' . DBHOST . ';charset=utf8mb4', DBUSER, DBPASS, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_WARNING, PDO::ATTR_PERSISTENT=>PERSISTENT]);
		if(false!==$db->exec('CREATE DATABASE ' . DBNAME)){
			$db=new PDO('mysql:host=' . DBHOST . ';dbname=' . DBNAME . ';charset=utf8mb4', DBUSER, DBPASS, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_WARNING, PDO::ATTR_PERSISTENT=>PERSISTENT]);
		}else{
			die("Error: No database connection!\n");
		}
	}catch(PDOException $e){
		die("Error: No database connection!\n");
	}
}
$version;
if(!@$version=$db->query("SELECT value FROM settings WHERE setting='version';")){
	//create tables
	$db->exec('CREATE TABLE captcha (id int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY, time int(11) NOT NULL, code char(5) COLLATE latin1_bin NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_bin;');
	$db->exec("CREATE TABLE service_instances (id char(1) NOT NULL PRIMARY KEY, reload tinyint(1) UNSIGNED NOT NULL DEFAULT '0', KEY reload (reload)) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_bin;");
	$db->exec("CREATE TABLE users (id int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY, system_account varchar(32) COLLATE latin1_bin NOT NULL UNIQUE, username varchar(50) COLLATE latin1_bin NOT NULL UNIQUE, password varchar(255) COLLATE latin1_bin NOT NULL, dateadded int(10) unsigned NOT NULL, public tinyint(1) unsigned NOT NULL, php tinyint(1) unsigned NOT NULL, autoindex tinyint(1) unsigned NOT NULL, todelete tinyint(1) UNSIGNED NOT NULL DEFAULT '0', mysql_user varchar(32) NOT NULL, instance char(1) NOT NULL DEFAULT '2', pgp_key text COLLATE 'latin1_bin' NULL, pgp_verified tinyint(1) NOT NULL DEFAULT '0', tfa tinyint(1) NOT NULL DEFAULT '0', KEY dateadded (dateadded), KEY public (public), KEY todelete (todelete), KEY instance (instance), CONSTRAINT instance_ibfk_2 FOREIGN KEY (instance) REFERENCES service_instances (id) ON DELETE RESTRICT ON UPDATE RESTRICT) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_bin;");
	$db->exec("CREATE TABLE new_account (user_id int(11) NOT NULL PRIMARY KEY, password varchar(255) COLLATE latin1_bin NOT NULL, approved tinyint(1) UNSIGNED NOT NULL DEFAULT '0', CONSTRAINT new_account_ibfk_1 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_bin;");
	$db->exec('CREATE TABLE pass_change (user_id int(11) NOT NULL PRIMARY KEY, password varchar(255) COLLATE latin1_bin NOT NULL, CONSTRAINT pass_change_ibfk_1 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_bin;');
	$db->exec('CREATE TABLE mysql_databases (user_id int(11) NOT NULL, mysql_database varchar(64) COLLATE latin1_bin NOT NULL, KEY user_id (user_id), CONSTRAINT mysql_database_ibfk_1 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_bin;');
	$db->exec("CREATE TABLE onions (user_id int(11) NULL, onion varchar(56) COLLATE latin1_bin NOT NULL PRIMARY KEY, private_key varchar(1000) COLLATE latin1_bin NOT NULL, version tinyint(1) NOT NULL, enabled tinyint(1) NOT NULL DEFAULT '1', num_intros tinyint(3) NOT NULL DEFAULT '3', enable_smtp tinyint(1) NOT NULL DEFAULT '1', max_streams tinyint(3) unsigned NOT NULL DEFAULT '6', instance char(1) NOT NULL DEFAULT '2', KEY user_id (user_id), KEY enabled (enabled), KEY instance(instance), CONSTRAINT onions_ibfk_1 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE, CONSTRAINT instance_ibfk_1 FOREIGN KEY (instance) REFERENCES service_instances (id) ON DELETE RESTRICT ON UPDATE RESTRICT) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_bin;");
	$db->exec("CREATE TABLE domains (user_id int(11) NULL, domain varchar(255) COLLATE latin1_bin NOT NULL PRIMARY KEY, enabled tinyint(1) NOT NULL DEFAULT '1', KEY user_id (user_id), KEY enabled (enabled), CONSTRAINT domains_ibfk_1 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_bin;");
	$db->exec("CREATE TABLE disk_quota (user_id int(11) NOT NULL, quota_size int(10) unsigned NOT NULL, quota_files int(10) unsigned NOT NULL, updated tinyint(1) NOT NULL DEFAULT 1, quota_size_used int(10) unsigned NOT NULL DEFAULT '0', quota_files_used int(10) unsigned NOT NULL DEFAULT '0', KEY user_id (user_id), KEY updated (updated), CONSTRAINT disk_quota_ibfk_2 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_bin;");
	$db->exec('CREATE TABLE nginx_rewrites (id int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY, user_id int(11) NOT NULL, `regex` varchar(255) NOT NULL, replacement varchar(255) NOT NULL, `flag` varchar(9) NOT NULL, ifnotexists tinyint(1) NOT NULL, CONSTRAINT nginx_rewrites_ibfk_2 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');
	$db->exec('CREATE TABLE payments (id int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY, user_id int(11) NULL, payment_for varchar(255) COLLATE latin1_bin NOT NULL, txn_id varchar(255) COLLATE utf8mb4_bin NOT NULL, status tinyint NOT NULL, CONSTRAINT payments_ibfk_1 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_bin;');
	$db->exec('CREATE TABLE settings (setting varchar(50) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL PRIMARY KEY, value text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_bin;');
	$stmt=$db->prepare("INSERT INTO settings (setting, value) VALUES ('version', ?);");
	$stmt->execute([DBVERSION]);
	echo "Database and files have successfully been set up\n";
}else{
	$version=$version->fetch(PDO::FETCH_NUM)[0];
	if($version<2){
		$db->exec('ALTER TABLE users ADD todelete tinyint(1) UNSIGNED NOT NULL, ADD INDEX(todelete);');
		$db->exec('ALTER TABLE new_account ADD approved tinyint(1) UNSIGNED NOT NULL;');
		$db->exec('DROP TABLE del_account;');
	}
	if($version<4){
		$db->exec('ALTER TABLE new_account DROP FOREIGN KEY new_account_ibfk_1;');
		$db->exec('ALTER TABLE pass_change DROP FOREIGN KEY pass_change_ibfk_1;');
		$db->exec('ALTER TABLE users DROP PRIMARY KEY;');
		$db->exec('ALTER TABLE users ADD id int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT FIRST;');
		$db->exec('ALTER TABLE users ADD UNIQUE (onion);');
		$db->exec('RENAME TABLE new_account TO copy_new_account;');
		$db->exec('CREATE TABLE new_account (user_id int(11) NOT NULL PRIMARY KEY, password varchar(255) COLLATE latin1_bin NOT NULL, approved tinyint(1) UNSIGNED NOT NULL, CONSTRAINT new_account_ibfk_1 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_bin;');
		$db->exec('INSERT INTO new_account SELECT users.id, copy_new_account.password, copy_new_account.approved FROM copy_new_account INNER JOIN users ON (users.onion=copy_new_account.onion);');
		$db->exec('DROP TABLE copy_new_account;');
		$db->exec('RENAME TABLE pass_change TO copy_pass_change;');
		$db->exec('CREATE TABLE pass_change (user_id int(11) NOT NULL PRIMARY KEY, password varchar(255) COLLATE latin1_bin NOT NULL, CONSTRAINT pass_change_ibfk_1 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_bin;');
		$db->exec('INSERT INTO pass_change SELECT users.id, copy_pass_change.password FROM copy_pass_change INNER JOIN users ON (users.onion=copy_pass_change.onion);');
		$db->exec('DROP TABLE copy_pass_change;');
	}
	if($version<5){
		$db->exec('ALTER TABLE users ADD mysql_user varchar(32) NOT NULL;');
		$db->exec("UPDATE users SET mysql_user=CONCAT(onion, '.onion');");
		$db->exec('CREATE TABLE mysql_databases (user_id int(11) NOT NULL KEY, mysql_database varchar(64) COLLATE latin1_bin NOT NULL, CONSTRAINT mysql_database_ibfk_1 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_bin;');
		$db->exec("INSERT INTO mysql_databases (user_id, mysql_database) SELECT id, onion FROM users;");
	}
	if($version<6){
		$db->exec('ALTER TABLE mysql_databases DROP PRIMARY KEY, ADD INDEX user_id (user_id);');
		$db->exec("CREATE TABLE onions (user_id int(11) NOT NULL, onion varchar(56) COLLATE latin1_bin NOT NULL PRIMARY KEY, private_key varchar(1000) COLLATE latin1_bin NOT NULL, version tinyint(1) NOT NULL, enabled tinyint(1) NOT NULL DEFAULT '1', num_intros tinyint(3) NOT NULL DEFAULT '3', enable_smtp tinyint(1) NOT NULL DEFAULT '1', KEY user_id (user_id), KEY enabled (enabled), CONSTRAINT onions_ibfk_1 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_bin;");
		$db->exec("INSERT INTO onions (user_id, onion, private_key, version) SELECT id, onion, private_key, 2 FROM users;");
		$db->exec('ALTER TABLE users DROP private_key;');
		$db->exec('ALTER TABLE users CHANGE onion system_account varchar(32) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL;');
		$db->exec("UPDATE users SET system_account = CONCAT(system_account, '.onion');");
		$stmt=$db->query("SELECT system_account FROM users;");
		while($id=$stmt->fetch(PDO::FETCH_NUM)){
			$system_account=$id[0];
			$onion=substr($id[0], 0, 16);
			$replace=preg_replace("~listen\sunix:/var/run/nginx(/[a-z2-7]{16}|\.sock)(\sbacklog=2048)?;~", "listen unix:/var/run/nginx/$system_account backlog=2048;", file_get_contents("/etc/nginx/sites-enabled/$system_account"));
			file_put_contents("/etc/nginx/sites-enabled/$system_account", $replace);
		}
	}
	if($version<7){
		$db->exec("ALTER TABLE onions ADD max_streams tinyint(3) unsigned NOT NULL DEFAULT '20';");
		$db->exec("CREATE TABLE service_instances (id char(1) NOT NULL PRIMARY KEY, reload tinyint(1) UNSIGNED NOT NULL DEFAULT '0', KEY reload (reload)) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_bin;");
		$stmt=$db->prepare('INSERT INTO service_instances (id, reload) VALUES (?, 1)');
		foreach(SERVICE_INSTANCES as $key){
			$stmt->execute([$key]);
		}
	}
	if($version<9){
		foreach(PHP_VERSIONS as $version){
			if(file_exists("/etc/php/$version/cli/conf.d/99-hosting.conf")){
				unlink("/etc/php/$version/cli/conf.d/99-hosting.conf");
			}
			if(file_exists("/etc/php/$version/fpm/conf.d/99-hosting.conf")){
				unlink("/etc/php/$version/fpm/conf.d/99-hosting.conf");
			}
		}
	}
	if($version<10){
		$db->exec('ALTER TABLE onions CHANGE user_id user_id int(11) NULL;');
		$db->exec('ALTER TABLE onions DROP FOREIGN KEY onions_ibfk_1;');
		$db->exec('ALTER TABLE onions ADD CONSTRAINT onions_ibfk_1 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE;');
		$nginx_default = 'server {
	listen unix:/var/run/nginx/suspended backlog=2048;
	add_header Content-Type text/html;
	location / {
		return 200 \'<html><head><title>Suspended</title></head><body>This domain has been suspended due to violation of <a href="http://' . ADDRESS . '">hosting rules</a>.</body></html>\';
	}
}
';
		file_put_contents('/etc/nginx/sites-enabled/default', $nginx_default, FILE_APPEND);
	}
	if($version<11){
		$db->exec("ALTER TABLE users CHANGE todelete todelete tinyint(1) UNSIGNED NOT NULL DEFAULT '0';");
		$db->exec("ALTER TABLE new_account CHANGE approved approved tinyint(1) UNSIGNED NOT NULL DEFAULT '0';");
	}
	if($version<12){
		$stmt=$db->query('SELECT system_account FROM users;');
		while($tmp=$stmt->fetch(PDO::FETCH_ASSOC)){
			// some software may break when absolute installation path changes, add symlinks to prevent that
			symlink('.', '/home/'.$tmp['system_account'].'/home');
			symlink('.', '/home/'.$tmp['system_account'].'/'.$tmp['system_account']);
			$firstchar=substr($tmp['system_account'], 0, 1);
			//delete config files
			foreach(array_replace(PHP_VERSIONS, DISABLED_PHP_VERSIONS) as $v){
				// new naming schema
				if(file_exists("/etc/php/$v/fpm/pool.d/$firstchar/$tmp[system_account].conf")){
					unlink("/etc/php/$v/fpm/pool.d/$firstchar/$tmp[system_account].conf");
				}
				// old naming schema
				if(file_exists("/etc/php/$v/fpm/pool.d/$firstchar/".substr($tmp['system_account'], 0, 16).".conf")){
					unlink("/etc/php/$v/fpm/pool.d/$firstchar/".substr($tmp['system_account'], 0, 16).".conf");
				}
			}
			if(file_exists("/etc/nginx/sites-enabled/$tmp[system_account]")){
				unlink("/etc/nginx/sites-enabled/$tmp[system_account]");
			}
		}
	}
	if($version<13){
		$db->exec("CREATE TABLE domains (user_id int(11) NULL, domain varchar(255) COLLATE latin1_bin NOT NULL PRIMARY KEY, enabled tinyint(1) NOT NULL DEFAULT '1', KEY user_id (user_id), KEY enabled (enabled), CONSTRAINT domains_ibfk_1 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_bin;");
	}
	if($version<14){
		$db->exec("ALTER TABLE onions ADD instance char(1) NOT NULL DEFAULT '2', ADD KEY instance(instance), ADD CONSTRAINT instance_ibfk_1 FOREIGN KEY (instance) REFERENCES service_instances (id) ON DELETE RESTRICT ON UPDATE RESTRICT;");
		$db->exec('UPDATE onions SET instance = SUBSTR(onion, 1, 1);');
		$db->exec("ALTER TABLE users ADD instance char(1) NOT NULL DEFAULT '2', ADD KEY instance(instance), ADD CONSTRAINT instance_ibfk_2 FOREIGN KEY (instance) REFERENCES service_instances (id) ON DELETE RESTRICT ON UPDATE RESTRICT;");
		$db->exec('UPDATE users SET instance = SUBSTR(system_account, 1, 1);');
	}
	if($version<15){
		$db->exec('CREATE TABLE disk_quota (user_id int(11) NOT NULL, quota_size int(10) unsigned NOT NULL, quota_files int(10) unsigned NOT NULL, updated tinyint(1) NOT NULL DEFAULT 1, KEY user_id (user_id), KEY updated (updated), CONSTRAINT disk_quota_ibfk_2 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_bin;');
		$stmt = $db->prepare('INSERT INTO disk_quota (user_id, quota_size, quota_files) SELECT id, ?, ? FROM users;');
		$stmt->execute([DEFAULT_QUOTA_SIZE, DEFAULT_QUOTA_FILES]);
	}
	if($version<16){
		$db->exec('UPDATE onions SET enabled=1 WHERE enabled=2;');
		$db->exec('CREATE TABLE nginx_rewrites (id int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY, user_id int(11) NOT NULL, `regex` varchar(255) NOT NULL, replacement varchar(255) NOT NULL, `flag` varchar(9) NOT NULL, ifnotexists tinyint(1) NOT NULL, CONSTRAINT nginx_rewrites_ibfk_2 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');
	}
	if($version<17){
		$db->exec("ALTER TABLE disk_quota ADD quota_size_used int(10) unsigned NOT NULL DEFAULT '0', ADD quota_files_used int(10) unsigned NOT NULL DEFAULT '0';");
		$db->exec('CREATE TABLE payments (id int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY, user_id int(11) NULL, payment_for varchar(255) COLLATE latin1_bin NOT NULL, txn_id varchar(255) COLLATE utf8mb4_bin NOT NULL, status tinyint NOT NULL, CONSTRAINT payments_ibfk_1 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_bin;');
	}
	if($version<18){
		$db->exec('UPDATE disk_quota set updated=1;');
		$db->exec("ALTER TABLE users ADD pgp_key text COLLATE 'latin1_bin' NULL, ADD pgp_verified tinyint(1) NOT NULL DEFAULT '0', ADD tfa tinyint(1) NOT NULL DEFAULT '0';");
	}
	if($version<20){
		$db->exec("ALTER TABLE onions CHANGE max_streams max_streams tinyint(3) unsigned NOT NULL DEFAULT '6';");
	}
	$stmt=$db->prepare("UPDATE settings SET value=? WHERE setting='version';");
	$stmt->execute([DBVERSION]);
}
foreach(PHP_VERSIONS as $version){
	if(!file_exists("/etc/php/$version/fpm/conf.d/")){
		mkdir("/etc/php/$version/fpm/conf.d/", 0755, true);
	}
	file_put_contents("/etc/php/$version/fpm/conf.d/99-hosting.ini", PHP_CONFIG);
	if(!file_exists("/etc/php/$version/cli/conf.d/")){
		mkdir("/etc/php/$version/cli/conf.d/", 0755, true);
	}
	file_put_contents("/etc/php/$version/cli/conf.d/99-hosting.ini", PHP_CONFIG);
	foreach(SERVICE_INSTANCES as $instance){
		$fpm_config = "[global]
pid = /run/php/php$version-fpm-$instance.pid
error_log = /var/log/php$version-fpm-$instance.log
process_control_timeout = 10
emergency_restart_threshold = 10
emergency_restart_interval = 10m
include=/etc/php/$version/fpm/pool.d/$instance/*.conf
";
		file_put_contents("/etc/php/$version/fpm/php-fpm-$instance.conf", $fpm_config);
		if(!file_exists("/etc/php/$version/fpm/pool.d/$instance/")){
			mkdir("/etc/php/$version/fpm/pool.d/$instance/", 0755, true);
		}
	}
	$fpm_config = "[global]
pid = /run/php/php$version-fpm.pid
error_log = /var/log/php$version-fpm.log
process_control_timeout = 10
emergency_restart_threshold = 10
emergency_restart_interval = 10m
include=/etc/php/$version/fpm/pool.d/*.conf
";
	file_put_contents("/etc/php/$version/fpm/php-fpm.conf", $fpm_config);
	$pool_config = "[hosting]
user = www-data
group = www-data
listen = /run/php/$version-hosting
listen.owner = www-data
listen.group = www-data
chroot = /var/www
pm = dynamic
pm.max_children = 25
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 3
php_admin_value[mysqli.allow_persistent] = On
php_admin_value[upload_tmp_dir] = /tmp
php_admin_value[soap.wsdl_cache_dir] = /tmp
php_admin_value[session.save_path] = /tmp
php_admin_value[sendmail_path] = '/usr/bin/php -r eval\(base64_decode\(\\\"JGM9Y3VybF9pbml0KCcxJyk7Y3VybF9zZXRvcHRfYXJyYXkoJGMsW0NVUkxPUFRfVU5JWF9TT0NLRVRfUEFUSD0+Jy92YXIvcnVuL21haWwuc29jaycsQ1VSTE9QVF9QT1NURklFTERTPT5bJ2NvbnRlbnQnPT5maWxlX2dldF9jb250ZW50cygncGhwOi8vc3RkaW4nKV1dKTtjdXJsX2V4ZWMoJGMpOwo=\\\"\)\)\;'
env[HOME]=/
[phpmyadmin]
user = www-data
group = www-data
listen = /run/php/$version-phpmyadmin
listen.owner = www-data
listen.group = www-data
chroot = /var/www
pm = dynamic
pm.max_children = 25
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 3
php_admin_value[mysqli.allow_persistent] = On
php_admin_value[upload_tmp_dir] = /tmp
php_admin_value[soap.wsdl_cache_dir] = /tmp
php_admin_value[session.save_path] = /tmp
php_admin_value[open_basedir] = /html/phpmyadmin:/tmp
env[HOME]=/
[squirrelmail]
user = www-data
group = www-data
listen = /run/php/$version-squirrelmail
listen.owner = www-data
listen.group = www-data
chroot = /var/www
pm = dynamic
pm.max_children = 25
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 3
php_admin_value[mysqli.allow_persistent] = On
php_admin_value[open_basedir] = /data/squirrelmail:/html/squirrelmail:/tmp
env[HOME]=/
[adminer]
user = www-data
group = www-data
listen = /run/php/$version-adminer
listen.owner = www-data
listen.group = www-data
chroot = /var/www
pm = dynamic
pm.max_children = 25
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 3
php_admin_value[mysqli.allow_persistent] = On
php_admin_value[upload_tmp_dir] = /tmp
php_admin_value[soap.wsdl_cache_dir] = /tmp
php_admin_value[session.save_path] = /tmp
php_admin_value[open_basedir] = /html/adminer:/tmp
env[HOME]=/
[mail]
user = www-data
group = www-data
listen = /run/php/$version-mail
listen.owner = www-data
listen.group = www-data
pm = dynamic
pm.max_children = 25
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 3
php_admin_value[mysqli.allow_persistent] = On
php_admin_value[upload_tmp_dir] = /tmp
php_admin_value[soap.wsdl_cache_dir] = /tmp
php_admin_value[session.save_path] = /tmp
env[HOME]=/
";
	if(!file_exists("/etc/php/$version/fpm/pool.d/")){
		mkdir("/etc/php/$version/fpm/pool.d/", 0755, true);
	}
	file_put_contents("/etc/php/$version/fpm/pool.d/www.conf", $pool_config);
	exec('systemctl reload '.escapeshellarg("php$version-fpm@default"));
}
echo "Updating chroots, this might take a while…\n";
exec('/var/www/setup_chroot.sh /var/www');
if(!SKIP_USER_CHROOT_UPDATE){
	$stmt=$db->query('SELECT system_account FROM users;');
	$shell = ENABLE_SHELL_ACCESS ? '/bin/bash' : '/usr/sbin/nologin';
	$last_account = '';
	while($tmp=$stmt->fetch(PDO::FETCH_ASSOC)){
		echo "Updating chroot for user $tmp[system_account]…\n";
		exec('usermod -s ' . escapeshellarg($shell) . ' ' . escapeshellarg($tmp['system_account']));
		setup_chroot($tmp['system_account'], $last_account);
		$last_account = $tmp['system_account'];
	}
}
if(!file_exists("/etc/nginx/sites-enabled/")){
	mkdir("/etc/nginx/sites-enabled/", 0755, true);
}
file_put_contents('/etc/nginx/sites-enabled/default', NGINX_DEFAULT);
if(!file_exists("/etc/nginx/streams-enabled/")){
	mkdir("/etc/nginx/streams-enabled/", 0755, true);
}
file_put_contents('/etc/nginx/streams-enabled/default', "server {
	listen unix:/var/www/var/run/mysqld/mysqld.sock;
	proxy_pass unix:/var/run/mysqld/mysqld.sock;
}");
exec('systemctl reload nginx');
// add new php/tor instances if not yet existing
$check=$db->prepare('SELECT null FROM service_instances WHERE id = ?;');
$stmt=$db->prepare('INSERT INTO service_instances (id) VALUES (?);');
foreach(SERVICE_INSTANCES as $instance){
	$check->execute([$instance]);
	if(!$check->fetch()){
		exec('useradd -d '.escapeshellarg("/var/lib/tor-instances/$instance").' -r -s /bin/false -M -U '.escapeshellarg("_tor-$instance"));
		exec('install -Z -d -m 02700 -o '.escapeshellarg("_tor-$instance").' -g '.escapeshellarg("_tor-$instance").' '.escapeshellarg("/var/lib/tor-instances/$instance"));
		exec('install -d '.escapeshellarg("/etc/tor/instances/$instance"));
		rewrite_torrc($instance);
		exec("systemctl enable ".escapeshellarg("tor@$instance"));
		exec("systemctl start ".escapeshellarg("tor@$instance"));
		foreach(PHP_VERSIONS as $version){
			rewrite_php_config($instance);
			exec("systemctl enable ".escapeshellarg("php$version-fpm@$instance"));
			exec("systemctl start ".escapeshellarg("php$version-fpm@$instance"));
		}
		$stmt->execute([$instance]);
		echo "Successfully added new instance $instance. Don't forget to add _tor-$instance as allowed user to your firewall rules in /etc/rc.local\n";
	}
}
// remove no longer enabled php/tor instances
$stmt=$db->query('SELECT id FROM service_instances;');
$update_users=$db->prepare('UPDATE users SET instance = (SELECT id FROM service_instances WHERE id !=? ORDER BY RAND() limit 1) WHERE instance=?;');
$update_onions=$db->prepare('UPDATE onions SET instance = (SELECT id FROM service_instances WHERE id !=? ORDER BY RAND() limit 1) WHERE instance=?;');
$drop_instance=$db->prepare('DELETE FROM service_instances WHERE id=?;');
while($tmp=$stmt->fetch(PDO::FETCH_NUM)){
	if(!in_array($tmp[0], SERVICE_INSTANCES)){
		exec('systemctl stop '.escapeshellarg("tor@$tmp[0]"));
		exec('systemctl disable '.escapeshellarg("tor@$tmp[0]"));
		exec('rm -r '.escapeshellarg("/var/lib/tor-instances/$tmp[0]/"));
		exec('rm -r '.escapeshellarg("/etc/tor/instances/$tmp[0]/"));
		exec('userdel '.escapeshellarg("_tor-$tmp[0]"));
		foreach(PHP_VERSIONS as $version){
			exec('systemctl stop '.escapeshellarg("php$version-fpm@$tmp[0]"));
			exec('systemctl disable '.escapeshellarg("php$version-fpm@$tmp[0]"));
			exec('rm -r '.escapeshellarg("/etc/php/$version/fpm/pool.d/$tmp[0]/"));
			unlink("/etc/php/$version/fpm/php-fpm-".basename($tmp[0]).'.conf');
		}
		$update_users->execute([$tmp[0], $tmp[0]]);
		$update_onions->execute([$tmp[0], $tmp[0]]);
		$drop_instance->execute($tmp);
	}
}
$db->exec('UPDATE service_instances SET reload=1;');
//set safe file permissions
exec('chown root:www-data -R /var/www/html/ /var/www/vendor/');
exec('find /var/www/html/ -type f -exec chmod 640 {} \;');
exec('find /var/www/html/ -type d -exec chmod 750 {} \;');
exec('chown root:www-data /var/www/common.php /var/www/composer.json /var/www/composer.lock /var/www/cron.php /var/www/find_old.php /var/www/setup_chroot.sh /var/www/setup.php');
exec('chmod 640 /var/www/common.php /var/www/composer.json /var/www/composer.lock /var/www/cron.php /var/www/find_old.php /var/www/setup.php');
exec('chmod 700 /var/www/setup_chroot.sh');
echo "Done - Database and files have been updated to the latest version :)\n";
