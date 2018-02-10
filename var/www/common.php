<?php
const DBHOST='localhost'; // Database host
const DBUSER='hosting'; // Database user
const DBPASS='MY_PASSWORD'; // Database password
const DBNAME='hosting'; // Database
const PERSISTENT=true; // Use persistent database conection true/false
const DBVERSION=1; //database layout version
const CAPTCHA=0; // Captcha difficulty (0=off, 1=simple, 2=moderate, 3=extreme)
const ADDRESS='dhosting4okcs22v.onion'; // our own address
const SERVERS=[ //servers and ports we are running on
'dhosting4okcs22v.onion'=>['sftp'=>22, 'ftp'=>21, 'pop3'=>'110', 'imap'=>'143', 'smtp'=>'25'],
'danwin1210.me'=>['sftp'=>22, 'ftp'=>21, 'pop3'=>'', 'imap'=>'', 'smtp'=>'']
];
const EMAIL_TO=''; //Send email notifications about new registrations to this address
const INDEX_MD5S=[ //MD5 sums of index.hosting.html files that should be considdered as unchanged for deletion
'd41d8cd98f00b204e9800998ecf8427e', //empty file
'7ae7e9bac6be76f00e0d95347111f037' //default file
];

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
	if(CAPTCHA===0 || !extension_loaded('gd')){
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
