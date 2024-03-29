<?php
require('../common.php');
$db = get_db_instance();
$user=check_login();
if(!empty($_POST['sftp_pass'])){
	$_SESSION['sftp_pass']=$_POST['sftp_pass'];
}
if(empty($_SESSION['sftp_pass'])){
	send_login();
	exit;
}
$ssh=ssh2_connect('127.0.0.1') or die (_('No Connection to SFTP server!'));
if(@!ssh2_auth_password($ssh, $user['system_account'], $_SESSION['sftp_pass'])){
	send_login();
	exit;
}
$sftp = ssh2_sftp($ssh);
//prepare reusable data
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
if(!is_dir("ssh2.sftp://$sftp$dir")){
	$dir=rtrim($dir, '/');
	if($tmpfile = @fopen("ssh2.sftp://$sftp$dir", 'r')){
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
		fclose($tmpfile);
	}else{
		send_not_found();
	}
	exit;
}

if(isset($_POST['mkdir']) && !empty($_POST['name'])){
	if($error=check_csrf_error()){
		die($error);
	}
	ssh2_sftp_mkdir($sftp, "$dir/$_POST[name]", 0750);
}

if(isset($_POST['mkfile']) && !empty($_POST['name'])){
	if($error=check_csrf_error()){
		die($error);
	}
	file_put_contents("ssh2.sftp://$sftp$dir$_POST[name]", '');
}

if(isset($_POST['delete']) && !empty($_POST['files'])){
	if($error=check_csrf_error()){
		die($error);
	}
	foreach($_POST['files'] as $file){
		sftp_recursive_delete($sftp, $dir, $file);
	}
}

if(isset($_POST['rename_2']) && !empty($_POST['files'])){
	if($error=check_csrf_error()){
		die($error);
	}
	foreach($_POST['files'] as $old=>$new){
		@ssh2_sftp_rename($sftp, "$dir/$old", "$dir/$new");
	}
}

if(isset($_POST['rename']) && !empty($_POST['files'])){
	if($error=check_csrf_error()){
		die($error);
	}
	send_rename($dir);
	exit;
}

if(isset($_POST['edit_2']) && !empty($_POST['files'])){
	if($error=check_csrf_error()){
		die($error);
	}
	foreach($_POST['files'] as $name=>$content){
		file_put_contents("ssh2.sftp://$sftp$dir/$name", $content);
	}
}

if(isset($_POST['edit']) && !empty($_POST['files'])){
	if($error=check_csrf_error()){
		die($error);
	}
	send_edit($sftp, $dir);
	exit;
}

if(isset($_POST['unzip']) && !empty($_POST['files'])){
	if($error=check_csrf_error()){
		die($error);
	}
	foreach($_POST['files'] as $file){
		if(!preg_match('/\.zip$/', $file)){
			continue;
		}
		ssh2_exec($ssh, 'cd '. escapeshellarg($dir) . ' && /usr/bin/unzip -qo ' . escapeshellarg($file));
	}
}


if(!empty($_FILES['files'])){
	if($error=check_csrf_error()){
		die($error);
	}
	$c=count($_FILES['files']['name']);
	for($i=0; $i<$c; ++$i){
		if($_FILES['files']['error'][$i]===UPLOAD_ERR_OK){
			$tmpfile = fopen($_FILES['files']['tmp_name'][$i], 'r');
			$upload = @fopen("ssh2.sftp://$sftp$dir/".$_FILES['files']['name'][$i], 'w');
			while($buffer=fread($tmpfile, 4096)){
				fwrite($upload, $buffer);
			}
			fclose($upload);
			fclose($tmpfile);
			unlink($_FILES['files']['tmp_name'][$i]);
		}
	}
}



$files=$dirs=[];
$dir_handle = opendir("ssh2.sftp://$sftp$dir");
while(($file = readdir($dir_handle)) !== false){
	if(in_array($file, ['.', '..'], true)){
		continue;
	}
	$stat = stat("ssh2.sftp://$sftp$dir/$file");
	if(is_dir("ssh2.sftp://$sftp$dir/$file")){
		$dirs[$file]=['name'=>"$file/", 'mtime' => $stat['mtime'], 'size'=>'-'];
	}else{
		$files[$file]=['name'=>$file, 'mtime' => $stat['mtime'], 'size' => $stat['size']];
	}
}
closedir($dir_handle);

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
$style = '.list td:nth-child(3){word-break:break-all;} .list td:nth-child(5){text-align:right;} .list tr{height:28px;}
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
.sh{min-width:22px; background:no-repeat url(data:img/gif;base64,R0lGODlhFAAWAPH/AAAAADMzM5mZmf///yH5BAUAAAQALAAAAAAUABYAAANgGLq89JCEQaudIb5pO88R11UiuFXAkJIXxAEwjAYATZ9UuuZxjPc7imAoAOBUyBHRKBk5hUzR01L8AXuVanPa0b6usWyU2x2rwDLokTzw8tDiNdnNVksCxLx+eIOg0Q8JADs=);}';
print_header(sprintf(_('FileManager - Index of %s'), $dir), $style);
$dir=htmlspecialchars($dir);
?>
<h1><?php printf(_('Index of %s'), $dir); ?></h1>
<?php if($dir!=='/'){ ?>
    <p><?php echo _('Upload up to 1GB and up to 100 files at once'); ?> <form action="files.php" enctype="multipart/form-data" method="post"><input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>"><input name="files[]" type="file" multiple><input type="hidden" name="path" value="<?php echo $dir; ?>"><button type="submit"><?php echo _('Upload'); ?></button></form></p><br>
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
<button type="submit" name="mkdir"><?php echo _('Create directory'); ?></button>
<button type="submit" name="mkfile"><?php echo _('Create file'); ?></button>
<input type="text" name="name" aria-label="<?php echo _('Filename'); ?>" placeholder="<?php echo _('Filename'); ?>"><br><br>
<input type="hidden" name="path" value="<?php echo $dir; ?>">
<button type="submit" name="delete"><?php echo _('Delete'); ?></button>
<button type="submit" name="rename"><?php echo _('Rename'); ?></button>
<button type="submit" name="edit"><?php echo _('Edit'); ?></button>
<button type="submit" name="unzip"><?php echo _('Unzip'); ?></button><br>
<table class="list"><tr>
<th></th><th></th>
<th><a href="files.php?path=<?php echo $dir; ?>&amp;C=N&amp;O=<?php echo $fileurl; ?>"><?php echo _('File'); ?></a></th>
<th><a href="files.php?path=<?php echo $dir; ?>&amp;C=M&amp;O=<?php echo $dateurl; ?>"><?php echo _('Last Modified'); ?></a></th>
<th><a href="files.php?path=<?php echo $dir; ?>&amp;C=S&amp;O=<?php echo $sizeurl; ?>"><?php echo _('Size'); ?></a></th>
</tr>
<tr><td colspan="4"><hr></td></tr>
<tr><td id="checkAllParent"></td><td class="back"></td><td colspan="3"><a href="files.php?path=<?php echo substr($dir, 0, strrpos(rtrim($dir, '/'), '/'))."/&amp;C=$sort&amp;O=$order"?>"><?php echo _('Parent Directory'); ?></a></td></tr>
<?php
foreach($list as $element){
	get_properties($element['name'], $icon, $element['size']);
	echo '<tr><td><input type="checkbox" class="fileCheck" name="files[]" value="'.htmlspecialchars($element['name'])."\"></td><td class=\"$icon\"></td><td><a href=\"files.php?path=$dir".str_replace('%2F', '/', rawurlencode($element['name'])).'">'.htmlspecialchars($element['name']).'</a></td><td>'.date("Y-m-d H:i", $element['mtime'])."</td><td>$element[size]</td></tr>";
}
?>
<tr><td colspan="4"><hr></td></tr>
</table>
<button type="submit" name="delete"><?php echo _('Delete'); ?></button>
<button type="submit" name="rename"><?php echo _('Rename'); ?></button>
<button type="submit" name="edit"><?php echo _('Edit'); ?></button>
<button type="submit" name="unzip"><?php echo _('Unzip'); ?></button><br><br>
</form>
<script>
document.getElementById('checkAllParent').innerHTML = '<input type="checkbox" onclick="toggle(this);">';
function toggle(source) {
  let checkboxes = document.getElementsByClassName('fileCheck');
  for(let i=0, n=checkboxes.length;i<n;i++) {
    checkboxes[i].checked = source.checked;
  }
}
</script>
</body></html>
<?php

function get_properties($name, &$icon, &$size): void
{
	if( str_ends_with( $name, '/' ) ){
		$icon='dir';
	}else{
		$extension=strtolower(substr($name, strrpos($name, '.')+1));
		$icon = TYPES[ $extension ] ?? 'ukwn';
		$size = bytes_to_human_readable($size);
	}
}

function send_not_found(): void
{
	header("HTTP/1.1 404 Not Found");
	print_header(_('FileManager - 404 Not Found'));
	echo '<p>'.sprintf(_('The requested file %s was not found on your account.'), htmlspecialchars($_REQUEST['path'])).'</p>';
	echo '<p><a href="files.php">'._('Go back to home directory').'</a></p>';
	echo '</body></html>';
}

function send_login(): void
{
	print_header(_('FileManager - Login'));
?>
<p><?php echo _('Please type in your system account password:'); ?> <form action="files.php" method="post"><input name="sftp_pass" type="password" autofocus><input type="submit" value="Login"></form></p>
<p><a href="home.php"><?php echo _('Go back to dashboard'); ?></a></p>
</body></html>
<?php
}

function sftp_recursive_delete($sftp, $dir, $file): void
{
	if(is_dir("ssh2.sftp://$sftp$dir/$file")){
		$dir_handle = opendir("ssh2.sftp://$sftp$dir/$file");
		while(($list = readdir($dir_handle)) !== false){
			if(in_array($list, ['.', '..'], true)){
				continue;
			}
			sftp_recursive_delete($sftp, "$dir/$file", $list);
		}
		closedir($dir_handle);
		rmdir("ssh2.sftp://$sftp$dir/$file");
	}else{
		unlink("ssh2.sftp://$sftp$dir/$file");
	}
}

function send_rename($dir): void
{
	print_header(_('FileManager - Rename file'));
	echo '<form action="files.php" method="post">';
	echo '<input type="hidden" name="csrf_token" value="'.$_SESSION['csrf_token'].'">';
	echo '<input type="hidden" name="path" value="'.htmlspecialchars($dir).'">';
	echo '<table>';
	foreach($_POST['files'] as $file){
		echo '<tr><td>'.htmlspecialchars($file).'</td><td><input type="text" name="files['.htmlspecialchars($file).']" value='.htmlspecialchars($file).'></td></tr>';
	}
	echo '</table>';
	echo '<button type="submit" name="rename_2">'._('Rename').'</button></form>';
	echo '<p><a href="files.php?path='.htmlspecialchars($dir).'">'._('Go back').'</a></p>';
	echo '</body></html>';
}

function send_edit($sftp, $dir): void
{
	print_header(_('FileManager - Edit file'));
	echo '<form action="files.php" method="post">';
	echo '<input type="hidden" name="csrf_token" value="'.$_SESSION['csrf_token'].'">';
	echo '<input type="hidden" name="path" value="'.htmlspecialchars($dir).'">';
	echo '<table>';
	foreach($_POST['files'] as $file){
		if(is_file("ssh2.sftp://$sftp$dir/$file")){
			echo '<tr><td>'.htmlspecialchars($file).'</td><td><textarea name="files['.htmlspecialchars($file).']" rows="20" cols="70">';
			echo htmlspecialchars(file_get_contents("ssh2.sftp://$sftp$dir/$file"));
			echo '</textarea></td></tr>';
		}
	}
	echo '</table>';
	echo '<button type="submit" name="edit_2">'._('Save').'</button></form>';
	echo '<p><a href="files.php?path='.htmlspecialchars($dir).'">'._('Go back').'</a></p>';
	echo '</body></html>';
}
