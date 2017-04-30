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
		$md5s=['2ff89413bebfd03f9241b0254ebfd782','d41d8cd98f00b204e9800998ecf8427e', '7ae7e9bac6be76f00e0d95347111f037'];
		if(!file_exists("/home/$tmp/www/index.hosting.html") || !in_array(md5_file("/home/$tmp/www/index.hosting.html"), $md5s)){
			continue;
		}
	}
	//no data found, safe to delete
	$stmt->execute([substr($tmp, 0, 16)]);
}
