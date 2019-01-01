<?php
include('../common.php');
try{
	$db=new PDO('mysql:host=' . DBHOST . ';dbname=' . DBNAME, DBUSER, DBPASS, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_WARNING, PDO::ATTR_PERSISTENT=>PERSISTENT]);
}catch(PDOException $e){
	die('No Connection to MySQL database!');
}
session_start();
$user=check_login();
if(!isset($_REQUEST['old']) || $_REQUEST['old']==0){
	$old='';
}else{
	$old='.1';
}
if(!isset($_REQUEST['type']) || $_REQUEST['type']==='access'){
	$type='access';
}else{
	$type='error';
}
header('Content-Type: text/plain; charset=UTF-8');
header("Content-disposition: filename=\"$type.log\"");
header('Pragma: no-cache');
header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
header('Expires: 0');
header("X-Accel-Redirect: /nginx/{$type}_$user[system_account].log$old");
