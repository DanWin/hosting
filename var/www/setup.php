<?php
include('common.php');
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
	$db->exec('CREATE TABLE users (onion char(16) COLLATE latin1_bin NOT NULL PRIMARY KEY, username varchar(50) COLLATE latin1_bin NOT NULL UNIQUE, password varchar(255) COLLATE latin1_bin NOT NULL, private_key varchar(1000) COLLATE latin1_bin NOT NULL, dateadded int(10) unsigned NOT NULL, public tinyint(3) unsigned NOT NULL, php tinyint(1) unsigned NOT NULL, autoindex tinyint(1) unsigned NOT NULL, todelete tinyint(1) UNSIGNED NOT NULL, KEY public (public), KEY dateadded (dateadded), KEY todelete (todelete)) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_bin;');
	$db->exec('CREATE TABLE new_account (onion char(16) COLLATE latin1_bin NOT NULL PRIMARY KEY, password varchar(255) COLLATE latin1_bin NOT NULL, approved tinyint(1) UNSIGNED NOT NULL, CONSTRAINT new_account_ibfk_1 FOREIGN KEY (onion) REFERENCES users (onion) ON DELETE CASCADE ON UPDATE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_bin;');
	$db->exec('CREATE TABLE pass_change (onion char(16) COLLATE latin1_bin NOT NULL PRIMARY KEY, password varchar(255) COLLATE latin1_bin NOT NULL, CONSTRAINT pass_change_ibfk_1 FOREIGN KEY (onion) REFERENCES users (onion) ON DELETE CASCADE ON UPDATE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_bin;');
	$db->exec('CREATE TABLE settings (setting varchar(50) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL PRIMARY KEY, value text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_bin;');
	$stmt=$db->prepare("INSERT INTO settings (setting, value) VALUES ('version', ?);");
	$stmt->execute([DBVERSION]);
	echo "Database has successfully been set up\n";
}else{
	$version=$version->fetch(PDO::FETCH_NUM)[0];
	if($version<2){
		$db->exec('ALTER TABLE users ADD todelete tinyint(1) UNSIGNED NOT NULL, ADD INDEX(todelete);');
		$db->exec('ALTER TABLE new_account ADD approved tinyint(1) UNSIGNED NOT NULL;');
		$db->exec('DROP TABLE del_account;');
	}
	if($version<3){
		$stmt=$db->query("SELECT onion FROM users;");
		while($id=$stmt->fetch(PDO::FETCH_NUM)){
			$onion=$id[0];
			$firstchar=substr($onion, 0, 1);
			$replace=str_replace("listen unix:/var/run/nginx.sock;", "listen unix:/var/run/nginx/$onion backlog=2048;", file_get_contents("/etc/nginx/sites-enabled/$onion.onion"));
			file_put_contents("/etc/nginx/sites-enabled/$onion.onion", $replace);
			$torrc=file_get_contents("/etc/tor/instances/$firstchar/torrc");
			$torrc=str_replace("$onion.onion/\nHiddenServicePort 80 unix:/var/run/nginx.sock", "$onion.onion/\nHiddenServicePort 80 unix:/var/run/nginx/$onion", $torrc);
			file_put_contents("/etc/tor/instances/$firstchar/torrc", $torrc);
		}
		exec('service nginx reload');
		exec("service tor reload");
	}
	$stmt=$db->prepare("UPDATE settings SET value=? WHERE setting='version';");
	$stmt->execute([DBVERSION]);
	if(DBVERSION!=$version){
		echo "Database has successfully been updated to the latest version\n";
	}else{
		echo "Database already up-to-date\n";
	}
}
