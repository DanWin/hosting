<?php
include('common.php');
$db = get_db_instance();

//update quota usage
$stmt=$db->query('SELECT id, system_account FROM users WHERE id NOT IN (SELECT user_id FROM new_account) AND todelete!=1;');
$update=$db->prepare('UPDATE disk_quota SET quota_size_used = ?, quota_files_used = ? WHERE user_id = ?;');
while($tmp=$stmt->fetch(PDO::FETCH_NUM)){
	$quota = shell_exec('quota -pu ' . escapeshellarg($tmp[1]));
	$quota_array = explode("\n", $quota);
	if(!empty($quota_array[2])){
		$quota_size=(int) preg_replace('~^\s+[^\s]+\s+([^\s]+).*~', '$1', $quota_array[2]);
		$quota_files=(int) preg_replace('~^\s+[^\s]+\s+[^\s]+\s+[^\s]+\s+[^\s]+\s+[^\s]+\s+([^\s]+).*~', '$1', $quota_array[2]);
		$update->execute([$quota_size, $quota_files, $tmp[0]]);
	}
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
