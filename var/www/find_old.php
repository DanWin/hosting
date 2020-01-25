<?php
require('common.php');
$db = get_db_instance();

//update quota usage
$stmt=$db->query('SELECT id, system_account FROM users WHERE id NOT IN (SELECT user_id FROM new_account) AND todelete!=1;');
$all_accounts=$stmt->fetchAll(PDO::FETCH_ASSOC);
$update=$db->prepare('UPDATE disk_quota SET quota_size_used = ?, quota_files_used = ? WHERE user_id = ?;');
foreach($all_accounts as $tmp){
	$system_account = sanitize_system_account($tmp['system_account']);
	if($system_account === false){
		echo "ERROR: Account $tmp[system_account] looks strange\n";
		continue;
	}
	$quota = shell_exec('quota -pu ' . escapeshellarg($tmp['system_account']));
	$quota_array = explode("\n", $quota);
	if(!empty($quota_array[2])){
		$quota_size=(int) preg_replace('~^\s+[^\s]+\s+([^\s]+).*~', '$1', $quota_array[2]);
		$quota_files=(int) preg_replace('~^\s+[^\s]+\s+[^\s]+\s+[^\s]+\s+[^\s]+\s+[^\s]+\s+([^\s]+).*~', '$1', $quota_array[2]);
		$update->execute([$quota_size, $quota_files, $tmp['id']]);
	}
}

//delete tmp files older than 24 hours
foreach($all_accounts as $tmp){
	$system_account = sanitize_system_account($tmp['system_account']);
	if($system_account === false){
		echo "ERROR: Account $tmp[system_account] looks strange\n";
		continue;
	}
	exec('find '.escapeshellarg("/home/$tmp[system_account]/tmp").' -path '.escapeshellarg("/home/$tmp[system_account]/tmp/*").' -cmin +1440 -delete');
}
exec("find /var/www/tmp -path '/var/www/tmp/*' -cmin +1440 -delete");

//delete unused accounts older than 30 days
$last_month=time()-60*60*24*30;
$del=$db->prepare('UPDATE users SET todelete=1 WHERE id=?;');
$stmt=$db->prepare('SELECT system_account, id FROM users WHERE dateadded<? AND id NOT IN (SELECT user_id FROM new_account) AND todelete!=1;');
$stmt->execute([$last_month]);
$all=$stmt->fetchAll(PDO::FETCH_ASSOC);
foreach($all as $tmp){
	$system_account = sanitize_system_account($tmp['system_account']);
	if($system_account === false){
		echo "ERROR: Account $tmp[system_account] looks strange\n";
		continue;
	}
	//check modification times
	if(filemtime("/home/$tmp[system_account]/data/")>$last_month){
		continue;
	}
	if(filemtime("/home/$tmp[system_account]/www/")>$last_month){
		continue;
	}
	$count_www=count(scandir("/home/$tmp[system_account]/www/"));
	//check data empty and www no more than 1 file
	if($count_www>3 || count(scandir("/home/$tmp[system_account]/data/"))>2){
		continue;
	}
	//check www empty or index unmodified
	if($count_www===3){
		if(!(
			( file_exists("/home/$tmp[system_account]/www/index.hosting.html") && in_array(md5_file("/home/$tmp[system_account]/www/index.hosting.html"), INDEX_MD5S, true) ) ||
			( file_exists("/home/$tmp[system_account]/www/index.html") && in_array(md5_file("/home/$tmp[system_account]/www/index.html"), INDEX_MD5S, true) ) ||
			( file_exists("/home/$tmp[system_account]/www/index.htm") && in_array(md5_file("/home/$tmp[system_account]/www/index.htm"), INDEX_MD5S, true) ) ||
			( file_exists("/home/$tmp[system_account]/www/index.php") && in_array(md5_file("/home/$tmp[system_account]/www/index.php"), INDEX_MD5S, true) )
		)){
			continue;
		}
	}
	//no data found, safe to delete
	$del->execute([substr($tmp['id'], 0, 16)]);
}
