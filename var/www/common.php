<?php
const DBHOST='localhost'; // Database host
const DBUSER='hosting'; // Database user
const DBPASS='MY_PASSWORD'; // Database password
const DBNAME='hosting'; // Database
const PERSISTENT=true; // Use persistent database conection true/false
const DBVERSION=10; //database layout version
const CAPTCHA=0; // Captcha difficulty (0=off, 1=simple, 2=moderate, 3=extreme)
const ADDRESS='dhosting4okcs22v.onion'; // our own address
const SERVERS=[ //servers and ports we are running on
'dhosting4okcs22v.onion'=>['sftp'=>22, 'ftp'=>21, 'pop3'=>'110', 'imap'=>'143', 'smtp'=>'25'],
'hosting.danwin1210.me'=>['sftp'=>222, 'ftp'=>21, 'pop3'=>'1995', 'imap'=>'1993', 'smtp'=>'1465']
];
const EMAIL_TO=''; //Send email notifications about new registrations to this address
const INDEX_MD5S=[ //MD5 sums of index.hosting.html files that should be considdered as unchanged for deletion
'd41d8cd98f00b204e9800998ecf8427e', //empty file
'7ae7e9bac6be76f00e0d95347111f037' //default file
];
const REQUIRE_APPROVAL=false; //require admin approval of new sites? true/false
const ADMIN_PASSWORD='MY_PASSWORD'; //password for admin interface
const SERVICE_INSTANCES=['2', '3', '4', '5', '6', '7', 'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z'];
const DISABLED_PHP_VERSIONS=[];
const PHP_VERSIONS=[2 => '7.1', 3 => '7.2', 4 => '7.3'];
const PHP_CONFIG='memory_limit = 256M
error_reporting = E_ALL
post_max_size = 10G
upload_max_filesize = 10G
max_file_uploads = 100
date.timezone = UTC
pdo_odbc.connection_pooling=off
odbc.allow_persistent = Off
ibase.allow_persistent = 0
mysqli.allow_persistent = Off
pgsql.allow_persistent = Off
opcache.enable=1
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=20000
opcache.use_cwd=1
opcache.validate_timestamps=1
opcache.revalidate_freq=2
opcache.revalidate_path=1
opcache.save_comments=1
opcache.optimization_level=0xffffffff
opcache.validate_permission=1
';
const NGINX_DEFAULT = 'server {
	listen unix:/var/run/nginx/suspended backlog=2048;
	add_header Content-Type text/html;
	location / {
		return 200 \'<html><head><title>Suspended</title></head><body>This domain has been suspended due to violation of <a href="http://' . ADDRESS . '">hosting rules</a>.</body></html>\';
	}
}
server {
	listen [::]:80 ipv6only=off fastopen=100 backlog=2048 default_server;
	listen unix:/var/run/nginx.sock backlog=2048 default_server;
	root /var/www/html;
	index index.php;
	server_name ' . ADDRESS . ' *.' . ADDRESS . ';
	location / {
		try_files $uri $uri/ =404;
		location ~ \.php$ {
			include snippets/fastcgi-php.conf;
			fastcgi_pass unix:/var/run/php/php7.2-fpm.sock;
		}
	}
	location /phpmyadmin {
		root /usr/share;
		location ~ \.php$ {
			include snippets/fastcgi-php.conf;
			fastcgi_pass unix:/run/php/php7.2-fpm.sock;
		}
	}
	location /adminer {
		root /usr/share/adminer;
		location ~ \.php$ {
			include snippets/fastcgi-php.conf;
			fastcgi_pass unix:/run/php/php7.2-fpm.sock;
		}
	}
	location /externals/jush/ {
		root /usr/share/adminer;
	}
	location /nginx/ {
		root /var/log/;
		internal;
	}
}
';

function get_onion($pkey){
	$keyData = openssl_pkey_get_details($pkey);
	return base32_encode(hex2bin(substr(sha1(substr(base64_decode(substr($keyData['key'], 27, -26)), 22)), 0, 20)));
}

function base32_encode($input) {
	$map = array(
		'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', //  7
		'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', // 15
		'q', 'r', 's', 't', 'u', 'v', 'w', 'x', // 23
		'y', 'z', '2', '3', '4', '5', '6', '7', // 31
	);
	if(empty($input)){
		return '';
	}
	$input = str_split($input);
	$binaryString = '';
	$c=count($input);
	for($i = 0; $i < $c; ++$i) {
		$binaryString .= str_pad(decbin(ord($input[$i])), 8, '0', STR_PAD_LEFT);
	}
	$fiveBitBinaryArray = str_split($binaryString, 5);
	$base32 = '';
	$i=0;
	$c=count($fiveBitBinaryArray);
	while($i < $c) {
		$base32 .= $map[bindec($fiveBitBinaryArray[$i])];
		++$i;
	}
	return $base32;
}

function send_captcha(){
	global $db;
	if(!CAPTCHA || !extension_loaded('gd')){
		return;
	}
	$captchachars='ABCDEFGHJKMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
	$length=strlen($captchachars)-1;
	$code='';
	for($i=0;$i<5;++$i){
		$code.=$captchachars[mt_rand(0, $length)];
	}
	$randid=mt_rand();
	$time=time();
	$stmt=$db->prepare('INSERT INTO captcha (id, time, code) VALUES (?, ?, ?);');
	$stmt->execute([$randid, $time, $code]);
	echo "<tr><td>Copy: ";
	if(CAPTCHA===1){
		$im=imagecreatetruecolor(55, 24);
		$bg=imagecolorallocate($im, 0, 0, 0);
		$fg=imagecolorallocate($im, 255, 255, 255);
		imagefill($im, 0, 0, $bg);
		imagestring($im, 5, 5, 5, $code, $fg);
		echo '<img width="55" height="24" src="data:image/gif;base64,';
	}elseif(CAPTCHA===2){
		$im=imagecreatetruecolor(55, 24);
		$bg=imagecolorallocate($im, 0, 0, 0);
		$fg=imagecolorallocate($im, 255, 255, 255);
		imagefill($im, 0, 0, $bg);
		imagestring($im, 5, 5, 5, $code, $fg);
		$line=imagecolorallocate($im, 255, 255, 255);
		for($i=0;$i<2;++$i){
			imageline($im, 0, mt_rand(0, 24), 55, mt_rand(0, 24), $line);
		}
		$dots=imagecolorallocate($im, 255, 255, 255);
		for($i=0;$i<100;++$i){
			imagesetpixel($im, mt_rand(0, 55), mt_rand(0, 24), $dots);
		}
		echo '<img width="55" height="24" src="data:image/gif;base64,';
	}else{
		$im=imagecreatetruecolor(150, 200);
		$bg=imagecolorallocate($im, 0, 0, 0);
		$fg=imagecolorallocate($im, 255, 255, 255);
		imagefill($im, 0, 0, $bg);
		$line=imagecolorallocate($im, 100, 100, 100);
		for($i=0;$i<5;++$i){
			imageline($im, 0, mt_rand(0, 200), 150, mt_rand(0, 200), $line);
		}
		$dots=imagecolorallocate($im, 200, 200, 200);
		for($i=0;$i<1000;++$i){
			imagesetpixel($im, mt_rand(0, 150), mt_rand(0, 200), $dots);
		}
		$chars=[];
		for($i=0;$i<10;++$i){
			$found=false;
			while(!$found){
				$x=mt_rand(10, 140);
				$y=mt_rand(10, 180);
				$found=true;
				foreach($chars as $char){
					if($char['x']>=$x && ($char['x']-$x)<25){
						$found=false;
					}elseif($char['x']<$x && ($x-$char['x'])<25){
						$found=false;
					}
					if(!$found){
						if($char['y']>=$y && ($char['y']-$y)<25){
							break;
						}elseif($char['y']<$y && ($y-$char['y'])<25){
							break;
						}else{
							$found=true;
						}
					}
				}
			}
			$chars[]=['x', 'y'];
			$chars[$i]['x']=$x;
			$chars[$i]['y']=$y;
			if($i<5){
				imagechar($im, 5, $chars[$i]['x'], $chars[$i]['y'], $captchachars[mt_rand(0, $length)], $fg);
			}else{
				imagechar($im, 5, $chars[$i]['x'], $chars[$i]['y'], $code[$i-5], $fg);
			}
		}
		$follow=imagecolorallocate($im, 200, 0, 0);
		imagearc($im, $chars[5]['x']+4, $chars[5]['y']+8, 16, 16, 0, 360, $follow);
		for($i=5;$i<9;++$i){
			imageline($im, $chars[$i]['x']+4, $chars[$i]['y']+8, $chars[$i+1]['x']+4, $chars[$i+1]['y']+8, $follow);
		}
		echo '<img width="150" height="200" src="data:image/gif;base64,';
	}
	ob_start();
	imagegif($im);
	imagedestroy($im);
	echo base64_encode(ob_get_clean()).'"></td>';
	echo "<td><input type=\"hidden\" name=\"challenge\" value=\"$randid\"><input type=\"text\" name=\"captcha\" autocomplete=\"off\"></td></tr>";
}

function check_login(){
	global $db;
	if(empty($_SESSION['hosting_username'])){
		header('Location: login.php');
		session_destroy();
		exit;
	}
	$stmt=$db->prepare('SELECT * FROM users WHERE username=?;');
	$stmt->execute([$_SESSION['hosting_username']]);
	if(!$user=$stmt->fetch(PDO::FETCH_ASSOC)){
		header('Location: login.php');
		session_destroy();
		exit;
	}
	return $user;
}

function get_system_hash($pass){
	$chars='ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789./';
	$salt='';
	for($i=0;$i<16;++$i){
		$salt.=$chars[random_int(0, strlen($chars)-1)];
	}
	return crypt($pass, '$6$'.$salt.'$');
}

function check_captcha_error(){
	global $db;
	if(CAPTCHA){
		if(!isset($_REQUEST['challenge'])){
			return 'Error: Wrong Captcha';
		}else{
			$stmt=$db->prepare('SELECT code FROM captcha WHERE id=?;');
			$stmt->execute([$_REQUEST['challenge']]);
			$stmt->bindColumn(1, $code);
			if(!$stmt->fetch(PDO::FETCH_BOUND)){
				return 'Error: Captcha expired';
			}else{
				$time=time();
				$stmt=$db->prepare('DELETE FROM captcha WHERE id=? OR time<?;');
				$stmt->execute([$_REQUEST['challenge'], $time-3600]);
				if($_REQUEST['captcha']!==$code){
					if(strrev($_REQUEST['captcha'])!==$code){
						return 'Error: Wrong captcha';
					}
				}
			}
		}
	}
	return false;
}

function rewrite_torrc(PDO $db, string $key){
$torrc="ClientUseIPv6 1
ClientUseIPv4 1
SOCKSPort 0
MaxClientCircuitsPending 1024
NumEntryGuards 6
NumDirectoryGuards 6
NumPrimaryGuards 6
";
	$stmt=$db->prepare('SELECT onions.onion, users.system_account, onions.num_intros, onions.enable_smtp, onions.version, onions.max_streams, onions.enabled FROM onions LEFT JOIN users ON (users.id=onions.user_id) WHERE onions.onion LIKE ? AND onions.enabled IN (1, -2) AND users.id NOT IN (SELECT user_id FROM new_account);');
	$stmt->execute(["$key%"]);
	while($tmp=$stmt->fetch(PDO::FETCH_NUM)){
if($tmp[6]==1){
	$socket=$tmp[1];
}else{
	$socket='suspended';
}
		$torrc.="HiddenServiceDir /var/lib/tor-instances/$key/hidden_service_$tmp[0].onion/
HiddenServiceNumIntroductionPoints $tmp[2]
HiddenServiceVersion $tmp[4]
HiddenServiceMaxStreamsCloseCircuit 1
HiddenServiceMaxStreams $tmp[5]
HiddenServicePort 80 unix:/var/run/nginx/$socket
";
		if($tmp[3]){
			$torrc.="HiddenServicePort 25\n";
		}
	}
	file_put_contents("/etc/tor/instances/$key/torrc", $torrc);
	exec("service tor@$key reload");
}
