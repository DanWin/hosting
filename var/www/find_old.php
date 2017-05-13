<?php
include('common.php');
try{
	$db=new PDO('mysql:host=' . DBHOST . ';dbname=' . DBNAME, DBUSER, DBPASS, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_WARNING, PDO::ATTR_PERSISTENT=>PERSISTENT]);
}catch(PDOException $e){
	die('No Connection to MySQL database!');
}

//delete tmp files older than 24 hours
exec('find /home -path "/home/*.onion/tmp/*" -cmin +1440 -delete');

//delete unused accounts older than 30 days
$all=scandir('/home');
$stmt=$db->prepare('INSERT INTO del_account (onion) VALUES (?);');
foreach($all as $tmp){
	if(!preg_match('~^[a-z2-7]{16}\.onion$~', $tmp)){
		continue;
	}
	if(filemtime("/home/$tmp")>time()-60*60*24*30){
		continue;
	}
	//check data empty and www no more than 1 file
	if(count(scandir("/home/$tmp/data/"))>2 || count(scandir("/home/$tmp/www/"))>3){
		continue;
	}
	//check www empty or index unmodified
	if(count(scandir("/home/$tmp/www/"))===3){
		if(!file_exists("/home/$tmp/www/index.hosting.html") || !in_array(md5_file("/home/$tmp/www/index.hosting.html"), INDEX_MD5S)){
			continue;
		}
	}
	//no data found, safe to delete
	$stmt->execute([substr($tmp, 0, 16)]);
}
