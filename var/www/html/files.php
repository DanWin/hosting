<?php
include('../common.php');
try{
	$db=new PDO('mysql:host=' . DBHOST . ';dbname=' . DBNAME, DBUSER, DBPASS, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_WARNING, PDO::ATTR_PERSISTENT=>PERSISTENT]);
}catch(PDOException $e){
	die('No Connection to MySQL database!');
}
session_start();
$user=check_login();
if(!empty($_POST['ftp_pass'])){
	$_SESSION['ftp_pass']=$_POST['ftp_pass'];
}
if(empty($_SESSION['ftp_pass'])){
	send_login();
	exit;
}
$ftp=ftp_connect('127.0.0.1') or die ('No Connection to FTP server!');
if(@!ftp_login($ftp, $user[system_account], $_SESSION['ftp_pass'])){
	send_login();
	exit;
}
//prepare reusable data
const SUFFIX=['B', 'KiB', 'MiB', 'GiB'];
const TYPES=[
'jpg'=>'img',
'psd'=>'img',
'jpeg'=>'img',
'png'=>'img',
'svg'=>'img',
'gif'=>'img',
'bmp'=>'img',
'ico'=>'img',
'm4v'=>'vid',
'webm'=>'vid',
'avi'=>'vid',
'flv'=>'vid',
'mpg'=>'vid',
'mpeg'=>'vid',
'wmv'=>'vid',
'ogm'=>'vid',
'ogv'=>'vid',
'mp4'=>'vid',
'mov'=>'vid',
'3gp'=>'vid',
'm4a'=>'snd',
'mp3'=>'snd',
'flac'=>'snd',
'ogg'=>'snd',
'oga'=>'snd',
'wav'=>'snd',
'wma'=>'snd',
'bin'=>'bin',
'exe'=>'bin',
'tgz'=>'zip',
'gz'=>'zip',
'zip'=>'zip',
'bz'=>'zip',
'bz2'=>'zip',
'xz'=>'zip',
'rar'=>'zip',
'tar'=>'zip',
'7z'=>'zip',
'xlsx'=>'doc',
'xsl'=>'doc',
'xml'=>'doc',
'doc'=>'doc',
'docx'=>'doc',
'css'=>'doc',
'html'=>'doc',
'htm'=>'doc',
'shtml'=>'doc',
'pdf'=>'doc',
'mobi'=>'doc',
'epub'=>'doc',
'odt'=>'doc',
'ods'=>'doc',
'odp'=>'doc',
'txt'=>'txt',
'csv'=>'txt',
'md'=>'txt',
'sh'=>'sh',
'js'=>'sh',
'pl'=>'sh',
'py'=>'sh',
'php'=>'sh',
'phtml'=>'sh',
'asp'=>'sh',
];
if(!isset($_REQUEST['C']) || !in_array($_REQUEST['C'], array('M', 'N', 'S'))){
	$sort='N';
}else{
	$sort=$_REQUEST['C'];
}
if(!isset($_REQUEST['O']) || !in_array($_REQUEST['O'], array('A', 'D'))){
	$order='A';
}else{
	$order=$_REQUEST['O'];
}
if(!empty($_REQUEST['path'])){
	$dir='/'.trim(rawurldecode($_REQUEST['path']),'/').'/';
	$dir=str_replace('..', '\.\.', $dir);
	$dir=preg_replace('~//+~', '/', $dir);
}else{
	$dir='/www/';
}
if(@!ftp_chdir($ftp, $dir)){
	$dir=rtrim($dir, '/');
	if(@ftp_fget($ftp, $tmpfile=tmpfile(), $dir, FTP_BINARY)){
		//output file
		header('Content-Type: ' . mime_content_type($tmpfile));
		header('Content-Disposition: filename="'.basename($dir).'"');
		header('Content-Length: ' . fstat($tmpfile)['size']);
		header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0, private');
		header('Expires: 0');
		header('Pragma: no-cache');
		rewind($tmpfile);
		while (($buffer = fgets($tmpfile, 4096)) !== false) {
			echo $buffer;
		}
	}else{
		send_not_found();
	}
	fclose($tmpfile);
	exit;
}

if(!empty($_POST['mkdir']) && !empty($_POST['name'])){
	if($error=check_csrf_error()){
		die($error);
	}
	ftp_mkdir($ftp, $_POST['name']);
}

if(!empty($_POST['mkfile']) && !empty($_POST['name'])){
	if($error=check_csrf_error()){
		die($error);
	}
	$tmpfile='/tmp/'.uniqid();
	touch($tmpfile);
	ftp_put($ftp, $_POST['name'], $tmpfile, FTP_BINARY);
	unlink($tmpfile);
}

if(!empty($_POST['delete']) && !empty($_POST['files'])){
	if($error=check_csrf_error()){
		die($error);
	}
	foreach($_POST['files'] as $file){
		ftp_recursive_delete($ftp, $file);
	}
}

if(!empty($_POST['rename_2']) && !empty($_POST['files'])){
	if($error=check_csrf_error()){
		die($error);
	}
	foreach($_POST['files'] as $old=>$new){
		ftp_rename($ftp, $old, $new);
	}
}

if(!empty($_POST['rename']) && !empty($_POST['files'])){
	if($error=check_csrf_error()){
		die($error);
	}
	send_rename($dir);
	exit;
}

if(!empty($_POST['edit_2']) && !empty($_POST['files'])){
	if($error=check_csrf_error()){
		die($error);
	}
	$tmpfile='/tmp/'.uniqid();
	foreach($_POST['files'] as $name=>$content){
		file_put_contents($tmpfile, $content);
		ftp_put($ftp, $name, $tmpfile, FTP_BINARY);
	}
	unlink($tmpfile);
}

if(!empty($_POST['edit']) && !empty($_POST['files'])){
	if($error=check_csrf_error()){
		die($error);
	}
	send_edit($ftp, $dir);
	exit;
}

if(!empty($_POST['unzip']) && !empty($_POST['files'])){
	if($error=check_csrf_error()){
		die($error);
	}
	$zip = new ZipArchive();
	foreach($_POST['files'] as $file){
		if(!preg_match('/\.zip$/', $file)){
			continue;
		}
		$tmpfile='/tmp/'.uniqid().'.zip';
		if(!ftp_get($ftp, $tmpfile, $file, FTP_BINARY)){
			continue;
		}
		//prevent zip-bombs
		$size=0;
		$resource=zip_open($tmpfile);
		if(!is_resource($resource)){
			unlink($tmpfile);
			continue;
		}
		while($dir_resource=zip_read($resource)) {
			$size+=zip_entry_filesize($dir_resource);
		}
		zip_close($resource);
		if($size<=1073741824){ //1GB limit
			$zip->open($tmpfile);
			$tmpdir='/tmp/'.uniqid().'/';
			mkdir($tmpdir);
			$zip->extractTo($tmpdir);
			ftp_recursive_upload($ftp, $tmpdir);
			rmdir($tmpdir);
			$zip->close();
		}
		unlink($tmpfile);
	}
}


if(!empty($_FILES['files'])){
	if($error=check_csrf_error()){
		die($error);
	}
	$c=count($_FILES['files']['name']);
	for($i=0; $i<$c; ++$i){
		if($_FILES['files']['error'][$i]===UPLOAD_ERR_OK){
			ftp_put($ftp, $dir.$_FILES['files']['name'][$i], $_FILES['files']['tmp_name'][$i], FTP_BINARY);
			unlink($_FILES['files']['tmp_name'][$i]);
		}
	}
}



$files=$dirs=[];
$list=ftp_rawlist($ftp, '.');
foreach($list as $file){
	preg_match('/^([^\s]*)\s+([^\s]*)\s+([^\s]*)\s+([^\s]*)\s+([^\s]*)\s+([^\s]*)\s+([^\s]*)\s+([^\s]*)\s+(.*)$/', $file, $match);
	if($match[0][0]==='d'){
		$dirs[$match[9]]=['name'=>"$match[9]/", 'mtime'=>strtotime("$match[6] $match[7] $match[8]"), 'size'=>'-'];
	}else{
		$files[$match[9]]=['name'=>$match[9], 'mtime'=>ftp_mdtm($ftp, $match[9]), 'size'=>$match[5]];
	}
}

//sort our files
if($sort==='M'){
	$list=array_merge($dirs, $files);
	usort($list, function($a, $b) {
		if ($a['mtime'] === $b['mtime']) {
			return 0;
		}
		return ($a['mtime'] < $b['mtime']) ? -1 : 1;
	});
}elseif($sort==='S'){
	ksort($dirs, SORT_STRING | SORT_FLAG_CASE);
	usort($files, function($a, $b) {
		if ($a['size'] === $b['size']) {
			return 0;
		}
		return ($a['size'] < $b['size']) ? -1 : 1;
	});
	$list=array_merge($dirs, $files);
}else{
	$list=array_merge($dirs, $files);
	ksort($list, SORT_STRING | SORT_FLAG_CASE);
}

//order correctly
if($order==='D'){
	$list=array_reverse($list);
}

$dir=htmlspecialchars($dir);
?>
<!DOCTYPE html>
<html><head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta name="author" content="Daniel Winzen">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="canonical" href="<?php echo CANONICAL_URL . $_SERVER['SCRIPT_NAME']; ?>">
<title>Daniel's Hosting - FileManager - Index of <?php echo $dir; ?></title>
<style type="text/css">.list td:nth-child(3){word-break:break-all;} .list td:nth-child(5){text-align:right;} .list tr{height:28px;}
.back{min-width:22px; background:no-repeat url(data:img/gif;base64,R0lGODlhFAAWAPH/AAAAADMzM2ZmZpmZmSH5BAUAAAQALAAAAAAUABYAAANLSLrc/oKE8CoZM1O7os7c9WmcN04WdoKQdBIANypAHG5YbS/7kus1RlDxA+p4xqSRpmwCKE7nINqMwKi6wEAY1VaS3tBV/OiRz4sEADs=);}
.dir{min-width:22px; background:no-repeat url(data:img/gif;base64,R0lGODlhFAAWAPH/AAAAADMzM5lmM//MmSH5BAUAAAQALAAAAAAUABYAAANUSLrc/jDKSRm4+E4wuu9AxH1kpimAQHpqiQ5CLMcrHI71GgdXngs8nI8F7A1JReFxZzyygk4iNNpJUmFWmFbF3cJ4hNRsPA6Aw+a0es0LLEzwjDsBADs=);}
.img{min-width:22px; background:no-repeat url(data:img/gif;base64,R0lGODlhFAAWAPMLAAAAADMzM2YAAAAzZmZmZv8zMwCZMwCZzJmZmczMzP///wAAAAAAAAAAAAAAAAAAACH5BAUAAAsALAAAAAAUABYAAASQMMhJ57p4BcW730F2bV5JhhlZdio6KkUsF4mi2tg2y4ICBL/gaxfrAY5IwJDY4yCeCKUGNjNYDTUFVKqTGTgJa1bLVSRi3/CVlIi+EgIB9mrdJAbuaYe+ThzwZSx8BAEHf3k3CQFXhIaHgR2KE46PLytmlJV6JX6ZgJYedwOjpJ+blyWIAVCsrU9AGUmys1IRADs=);}
.snd{min-width:22px; background:no-repeat url(data:img/gif;base64,R0lGODlhFAAWAPIGAAAAADMzM2ZmZpmZmczMzP///wAAAAAAACH5BAUAAAYALAAAAAAUABYAAANQaLrc/g5I+KQNdFkgJKabIHpQKBJXyZ0b8EUcepFVG9w07N1K3uwvg48xFLpULxuyBygMisxP8zlUGjdBiTOHzW59Nx7RmmHuyoxwEM1eJAAAOw==);}
.zip{min-width:22px; background:no-repeat url(data:img/gif;base64,R0lGODlhFAAWAPIGAAAAADMzM2YAAP8zM5mZmf///wAAAAAAACH5BAUAAAYALAAAAAAUABYAAANuaGrRvTCuUAoNMrNKrY5d531KWHHkVgAB65IdIM8AfBZETtSRMJQ3XI43ECh8P9FtZxgUj86B8rSLGg1IZ7BCiD6x3sE2fM0Wt+aydYvLqotTLgCpNsZxNV/5emdirxB9PBqCMAE6Oi0pBjQ0EgkAOw==);}
.ukwn{min-width:22px; background:no-repeat url(data:img/gif;base64,R0lGODlhFAAWAPH/AAAAADMzM5mZmf///yH5BAUAAAQALAAAAAAUABYAAANoGLq89JCEQaudIb5pO88RNwgCYJoiuFHleYqDyrUuCgQ3sA6uMNannavSI+kknQoNQCrtLEsT6wj71Xy/JwWIzSItvY7zSzwlx9UklNoZitlg81vLk6/paiU8P5cEmoCBJDgRQIZBBAkAOw==);}
.vid{min-width:22px; background:no-repeat url(data:img/gif;base64,R0lGODlhFAAWAPIGAAAAADMzM2ZmZpmZmczMzP///wAAAAAAACH5BAUAAAYALAAAAAAUABYAAANmCLrcahAQ4toEIV7BOy8SlRmMMJzoAC5jCJjpCV7tFstuu8DxqtQUXmom0gRvqlxkUWg6nQxghUEzvjwdImapEKJ8W5LtppV6cVXSDgk2s5XiIxnOeNqj1qki7dLDDQGBgoOEgRAJADs=);}
.bin{min-width:22px; background:no-repeat url(data:img/gif;base64,R0lGODlhFAAWAPIFAAAAADMzM5mZmczMzP///wAAAAAAAAAAACH5BAUAAAUALAAAAAAUABYAAANpGLq89bAEQqudIb4JABkdMBATqXFeR6ilCaFr6rWuFN8qEOj8doOdUWjosxgpgqQA4AOKbhUl05aTHZe+KtSCpVpVxu7EKfSEp7TjOeshX9E469obf7Prc5g7r+6LA0qBgkk7EUOHiFMJADs=);}
.doc{min-width:22px; background:no-repeat url(data:img/gif;base64,R0lGODlhFAAWAPL/AAAAADMzM/8zM5lmM//MM2bM/5mZmf///yH5BAUAAAgALAAAAAAUABYAAARvMMhJJ7oYhcO730F2bV5JhtlZceSBjixBFDT7YedMFxwQ+ECYa1c7AI5IgDAwaDY9hqhBqWE5n9AotVXqHqZCbxdcNSbPHTJXnN72zsl2mC0vcwTmOEdNL/E7eHB1a3R/fXtbAVKLjFE/GXCRSBcRADs=);}
.txt{min-width:22px; background:no-repeat url(data:img/gif;base64,R0lGODlhFAAWAPH/AAAAADMzM5mZmf///yH5BAUAAAQALAAAAAAUABYAAANYGLq89JCEQaudIb5pO88R11UiuI3XBXFA61JAEM8nCrtujbeW4AuAmq3yC0puuxcFKBwSjaykcsA8OntQpPTZvFZF2un3iu1ul1kyuuv8Bn7wuE8WkdqNCQA7);}
.sh{min-width:22px; background:no-repeat url(data:img/gif;base64,R0lGODlhFAAWAPH/AAAAADMzM5mZmf///yH5BAUAAAQALAAAAAAUABYAAANgGLq89JCEQaudIb5pO88R11UiuFXAkJIXxAEwjAYATZ9UuuZxjPc7imAoAOBUyBHRKBk5hUzR01L8AXuVanPa0b6usWyU2x2rwDLokTzw8tDiNdnNVksCxLx+eIOg0Q8JADs=);}
</style>
</head><body>
<h1>Index of <?php echo $dir; ?></h1>
<?php if($dir!=='/'){ ?>
<p>Upload up to 1GB and up to 100 files at once <form action="files.php" enctype="multipart/form-data" method="post"><input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>"><input name="files[]" type="file" multiple><input type="hidden" name="path" value="<?php echo $dir; ?>"><input type="submit" value="Upload"></form></p><br>
<?php
}
$fileurl='A';
$sizeurl='A';
$dateurl='A';

if($order==='A'){
	if($sort==='N'){
		$fileurl='D';
	}elseif($sort==='S'){
		$sizeurl='D';
	}else{
		$dateurl='D';
	}
}
?>
<form action="files.php" method="post">
<input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
<input type="submit" name="mkdir" value="Create directory">
<input type="submit" name="mkfile" value="Create file">
<input type="text" name="name"><br><br>
<input type="hidden" name="path" value="<?php echo $dir; ?>">
<input type="submit" name="delete" value="Delete">
<input type="submit" name="rename" value="Rename">
<input type="submit" name="edit" value="Edit">
<input type="submit" name="unzip" value="Unzip"><br>
<table class="list"><tr>
<th></th><th></th>
<th><a href="files.php?path=<?php echo $dir; ?>&amp;C=N&amp;O=<?php echo $fileurl; ?>">File</a></th>
<th><a href="files.php?path=<?php echo $dir; ?>&amp;C=M&amp;O=<?php echo $dateurl; ?>">Last Modified</a></th>
<th><a href="files.php?path=<?php echo $dir; ?>&amp;C=S&amp;O=<?php echo $sizeurl; ?>">Size</a></th>
</tr>
<tr><td colspan="4"><hr></td></tr>
<tr><td id="checkAllParent"></td><td class="back"></td><td colspan="3"><a href="files.php?path=<?php echo substr($dir, 0, strrpos(rtrim($dir, '/'), '/'))."/&amp;C=$sort&amp;O=$order"?>">Parent Directory</a></td></tr>
<?php
foreach($list as $element){
	get_properties($element['name'], $icon, $element['size']);
	echo '<tr><td><input type="checkbox" class="fileCheck" name="files[]" value="'.htmlspecialchars($element['name'])."\"></td><td class=\"$icon\"></td><td><a href=\"files.php?path=$dir".str_replace('%2F', '/', rawurlencode($element['name'])).'">'.htmlspecialchars($element['name']).'</a></td><td>'.date("Y-m-d H:i", $element['mtime'])."</td><td>$element[size]</td></tr>";
}
?>
<tr><td colspan="4"><hr></td></tr>
</table>
<input type="submit" name="delete" value="Delete">
<input type="submit" name="rename" value="Rename">
<input type="submit" name="edit" value="Edit">
<input type="submit" name="unzip" value="Unzip"><br><br>
</form>
<script>
document.getElementById('checkAllParent').innerHTML = '<input type="checkbox" onclick="toggle(this);">';
function toggle(source) {
  checkboxes = document.getElementsByClassName('fileCheck');
  for(var i=0, n=checkboxes.length;i<n;i++) {
    checkboxes[i].checked = source.checked;
  }
}
</script>
</body></html>
<?php

function get_properties($name, &$icon, &$size){
	if(substr($name, -1, 1)==='/'){
		$icon='dir';
	}else{
		$extension=strtolower(substr($name, strrpos($name, '.')+1));
		if(isset(TYPES[$extension])){
			$icon=TYPES[$extension];
		}else{
			$icon='ukwn';
		}
		$class=(int) log($size, 1024);
		if($class!==0){
			$size=sprintf('%1.1f', $size / pow(1024, $class)) . SUFFIX[$class];
		}else{
			$size.=SUFFIX[0];
		}
	}
}

function send_not_found(){
	header("HTTP/1.1 404 Not Found");
	echo '<!DOCTYPE html><html><head>';
	echo '<title>404 Not Found</title>';
	echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
	echo '<meta name=viewport content="width=device-width, initial-scale=1">';
	echo '<link rel="canonical" href="'.CANONICAL_URL . $_SERVER['SCRIPT_NAME'].'">';
	echo '</head><body>';
	echo '<p>The requested file '.htmlspecialchars($_REQUEST['path']).' was not found on your account.</p>';
	echo '<p><a href="files.php">Go back to home directory</a>.</p>';
	echo '</body></html>';
}

function send_login(){
	echo '<!DOCTYPE html><html><head>';
	echo '<title>Daniel\'s Hosting - FileManager - Login</title>';
	echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
	echo '<meta name=viewport content="width=device-width, initial-scale=1">';
	echo '</head><body>';
	echo '<p>Please type in your system account password: <form action="files.php" method="post"><input name="ftp_pass" type="password" autofocus><input type="submit" value="Login"></form></p>';
	echo '<p><a href="home.php">Go back to dashboard</a>.</p>';
	echo '</body></html>';
}

function ftp_recursive_upload($ftp, $path){
	$dir = dir($path);
	while(($file = $dir->read()) !== false) {
		if(is_dir($dir->path.$file)) {
			if($file === '.' || $file === '..'){
				continue;
			}
			if(@!ftp_chdir($ftp, $file)){
				ftp_mkdir($ftp, $file);
				ftp_chdir($ftp, $file);
			}
			ftp_recursive_upload($ftp, $dir->path.$file.'/');
			ftp_chdir($ftp, '..');
			rmdir($dir->path.$file);
		}else{
			ftp_put($ftp, $file, $dir->path.$file, FTP_BINARY);
			unlink($dir->path.$file);
		}
	}
	$dir->close();
}

function ftp_recursive_delete($ftp, $file){
	if(@ftp_chdir($ftp, $file)){
		if($list = ftp_nlist($ftp, '.')){
			foreach($list as $tmp){
				ftp_recursive_delete($ftp, $tmp);
			}
		}
		ftp_chdir($ftp, '..');
		ftp_rmdir($ftp, $file);
	}else{
		ftp_delete($ftp, $file);
	}
}

function send_rename($dir){
	echo '<!DOCTYPE html><html><head>';
	echo '<title>Daniel\'s Hosting - FileManager - Rename file</title>';
	echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
	echo '<meta name=viewport content="width=device-width, initial-scale=1">';
	echo '</head><body>';
	echo '<form action="files.php" method="post">';
	echo '<input type="hidden" name="csrf_token" value="'.$_SESSION['csrf_token'].'">';
	echo '<input type="hidden" name="path" value="'.htmlspecialchars($dir).'">';
	echo '<table>';
	foreach($_POST['files'] as $file){
		echo '<tr><td>'.htmlspecialchars($file).'</td><td><input type="text" name="files['.htmlspecialchars($file).']" value='.htmlspecialchars($file).'></td></tr>';
	}
	echo '</table>';
	echo '<input type="submit" name="rename_2" value="rename"></form>';
	echo '<p><a href="files.php?path='.htmlspecialchars($dir).'">Go back</a>.</p>';
	echo '</body></html>';
}

function send_edit($ftp, $dir){
	echo '<!DOCTYPE html><html><head>';
	echo '<title>Daniel\'s Hosting - FileManager - Edit file</title>';
	echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
	echo '<meta name=viewport content="width=device-width, initial-scale=1">';
	echo '</head><body>';
	echo '<form action="files.php" method="post">';
	echo '<input type="hidden" name="csrf_token" value="'.$_SESSION['csrf_token'].'">';
	echo '<input type="hidden" name="path" value="'.htmlspecialchars($dir).'">';
	echo '<table>';
	$tmpfile='/tmp/'.uniqid();
	foreach($_POST['files'] as $file){
		echo '<tr><td>'.htmlspecialchars($file).'</td><td><textarea name="files['.htmlspecialchars($file).']" rows="10" cols="30">';
		if(ftp_get($ftp, $tmpfile, $file, FTP_BINARY)){
			echo htmlspecialchars(file_get_contents($tmpfile));
		}
		echo '</textarea></td></tr>';
	}
	if(file_exists($tmpfile)){
		unlink($tmpfile);
	}
	echo '</table>';
	echo '<input type="submit" name="edit_2" value="Save"></form>';
	echo '<p><a href="files.php?path='.htmlspecialchars($dir).'">Go back</a>.</p>';
	echo '</body></html>';
}
