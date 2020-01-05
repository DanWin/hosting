<?php
$head = true;
$content = str_replace("\r\n", "\n", $_POST['content']);
$lines = explode("\n", $content);
$mail = '';
foreach($lines as $line){
	if($head && stripos(ltrim($line), 'FROM')===0){
		continue;
	}
	if($head && $line===''){
		$head = false;
		$mail .= "From: $_SERVER[MAIL_USER]\r\n";
	}
	$mail .= "$line\r\n";
}
exec('echo ' . escapeshellarg($mail). ' | sendmail -t -f ' . $_SERVER['MAIL_USER']);
