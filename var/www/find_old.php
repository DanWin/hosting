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
$del=$db->prepare('UPDATE users SET todelete=1 WHERE onion=?;');
$stmt=$db->prepare('SELECT onion FROM users WHERE dateadded<?;');
$stmt->execute([time()-60*60*24*30]);
$all=$stmt->fetchAll(PDO::FETCH_NUM);
foreach($all as $tmp){
	$tmp=$tmp[0].'.onion';
	if(filemtime("/home/$tmp")>time()-60*60*24*30){
		continue;
	}
	$count_www=count(scandir("/home/$tmp/www/"));
	//check data empty and www no more than 1 file
	if($count_www>3 || count(scandir("/home/$tmp/data/"))>2){
		continue;
	}
	//check www empty or index unmodified
	if($count_www===3){
		if(!file_exists("/home/$tmp/www/index.hosting.html") || !in_array(md5_file("/home/$tmp/www/index.hosting.html"), INDEX_MD5S)){
			continue;
		}
	}
	//no data found, safe to delete
//	$del->execute([substr($tmp, 0, 16)]);
var_dump($tmp);
}
