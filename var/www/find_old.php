<?php
include('common.php');
try{
	$db=new PDO('mysql:host=' . DBHOST . ';dbname=' . DBNAME, DBUSER, DBPASS, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_WARNING, PDO::ATTR_PERSISTENT=>PERSISTENT]);
}catch(PDOException $e){
	die('No Connection to MySQL database!');
}

//delete tmp files older than 24 hours
$stmt=$db->query('SELECT system_account FROM users;');
$all=$stmt->fetchAll(PDO::FETCH_NUM);
foreach($all as $tmp){
	exec('find '.escapeshellarg("/home/$tmp[0]/tmp").' -path '.escapeshellarg("/home/$tmp[0]/tmp/*").' -cmin +1440 -delete');
}
exec("find /var/www/tmp -path '/var/www/tmp/*' -cmin +1440 -delete");

//delete unused accounts older than 30 days
$last_month=time()-60*60*24*30;
$del=$db->prepare('UPDATE users SET todelete=1 WHERE id=?;');
$stmt=$db->prepare('SELECT system_account, id FROM users WHERE dateadded<?;');
$stmt->execute([$last_month]);
$all=$stmt->fetchAll(PDO::FETCH_NUM);
foreach($all as $tmp){
	//check modification times
	if(filemtime("/home/$tmp[0]/")>$last_month){
		continue;
	}
	if(filemtime("/home/$tmp[0]/data/")>$last_month){
		continue;
	}
	if(filemtime("/home/$tmp[0]/www/")>$last_month){
		continue;
	}
	$count_www=count(scandir("/home/$tmp[0]/www/"));
	//check data empty and www no more than 1 file
	if($count_www>3 || count(scandir("/home/$tmp[0]/data/"))>2){
		continue;
	}
	//check www empty or index unmodified
	if($count_www===3){
		if(!file_exists("/home/$tmp[0]/www/index.hosting.html") || !in_array(md5_file("/home/$tmp[0]/www/index.hosting.html"), INDEX_MD5S)){
			continue;
		}
	}
	//no data found, safe to delete
	$del->execute([substr($tmp[1], 0, 16)]);
}
