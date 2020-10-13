<?php
require_once(__DIR__ . '/vendor/autoload.php');
const DBHOST='localhost'; // Database host
const DBUSER='hosting'; // Database user
const DBPASS='MY_PASSWORD'; // Database password
const DBNAME='hosting'; // Database
const PERSISTENT=true; // Use persistent database conection true/false
const DBVERSION=18; //database layout version
const CAPTCHA=1; // Captcha difficulty (0=off, 1=simple, 2=moderate, 3=extreme)
const ADDRESS='dhosting4xxoydyaivckq7tsmtgi4wfs3flpeyitekkmqwu4v4r46syd.onion'; // our own address
const CANONICAL_URL='https://hosting.danwin1210.me'; // our preferred domain for search engines
const SERVERS=[ //servers and ports we are running on
'dhosting4xxoydyaivckq7tsmtgi4wfs3flpeyitekkmqwu4v4r46syd.onion'=>['sftp'=>22, 'pop3'=>'110', 'imap'=>'143', 'smtp'=>'25'],
'hosting.danwin1210.me'=>['sftp'=>22, 'pop3'=>'995', 'imap'=>'993', 'smtp'=>'465']
];
const EMAIL_TO=''; //Send email notifications about new registrations to this address
const INDEX_MD5S=[ //MD5 sums of index.hosting.html files that should be considdered as unchanged for deletion
'd41d8cd98f00b204e9800998ecf8427e', //empty file
'7ae7e9bac6be76f00e0d95347111f037', //default file
'703fac6634bf637f942db8906092d0ab', //new default file
'e109a5a44969c2a109aee0ea3565529e', //TOR HTML Site
'31ff0d6a1d280d610a700f3c1ec6d857', //MyHacker test page
];
const REQUIRE_APPROVAL=false; //require admin approval of new sites? true/false
const ENABLE_SHELL_ACCESS=true; //allows users to login via ssh, when disabled only sftp is allowed - run setup.php to migrate existing accounts
const ADMIN_PASSWORD='MY_PASSWORD'; //password for admin interface
const SERVICE_INSTANCES=['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's']; //one character per instance - run multiple tor+php-fpm instances for load balancing, remove all but one instance if you expect less than 200 accounts. If tor starts using 100% cpu and failing circuits every few hours after a restart, add more instances. In my experience this happens around 250 hidden services per instance - run setup.php after change
const DISABLED_PHP_VERSIONS=[]; //php versions still installed on the system but no longer offered for new accounts
const PHP_VERSIONS=[4 => '7.3', 5 => '7.4', 6 => '8.0']; //currently active php versions
const DEFAULT_PHP_VERSION='7.4'; //default php version
const PHP_CONFIG='zend_extension=opcache.so
memory_limit = 256M
error_reporting = E_ALL
display_errors = Off
log_errors = On
expose_php = Off
variables_order = "GPCS"
request_order = "GP"
post_max_size = 10G
upload_max_filesize = 10G
max_file_uploads = 100
date.timezone = UTC
pdo_odbc.connection_pooling = Off
odbc.allow_persistent = Off
mysqli.allow_persistent = Off
pgsql.allow_persistent = Off
opcache.enable = 1
opcache.interned_strings_buffer = 8
opcache.max_accelerated_files = 20000
opcache.use_cwd = 1
opcache.validate_timestamps = 1
opcache.revalidate_freq = 2
opcache.revalidate_path = 1
opcache.save_comments = 1
opcache.optimization_level = 0x7fffffff
opcache.validate_permission = 1
opcache.validate_root = 1
opcache.jit_buffer_size = 64M
session.use_strict_mode = 1
';
const NGINX_DEFAULT = 'server {
	listen unix:/var/run/nginx/suspended backlog=2048;
	add_header Content-Type text/html;
	location / {
		return 200 \'<html><head><title>Suspended</title></head><body>This domain has been suspended due to violation of our <a href="http://' . ADDRESS . '">hosting rules</a>.</body></html>\';
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
			fastcgi_param DOCUMENT_ROOT /html;
			fastcgi_param SCRIPT_FILENAME /html$fastcgi_script_name;
			fastcgi_pass unix:/var/run/php/7.4-hosting;
		}
	}
	location /squirrelmail {
		location ~ \.php$ {
			include snippets/fastcgi-php.conf;
			fastcgi_param DOCUMENT_ROOT /html;
			fastcgi_param SCRIPT_FILENAME /html$fastcgi_script_name;
			fastcgi_pass unix:/var/run/php/7.4-squirrelmail;
		}
	}
	location /phpmyadmin {
		location ~ \.php$ {
			include snippets/fastcgi-php.conf;
			fastcgi_param DOCUMENT_ROOT /html;
			fastcgi_param SCRIPT_FILENAME /html$fastcgi_script_name;
			fastcgi_pass unix:/run/php/8.0-phpmyadmin;
		}
	}
	location /adminer {
		root /var/www/html/adminer;
		location ~ \.php$ {
			include snippets/fastcgi-php.conf;
			fastcgi_param DOCUMENT_ROOT /html/adminer;
			fastcgi_param SCRIPT_FILENAME /html/adminer$fastcgi_script_name;
			fastcgi_pass unix:/run/php/8.0-adminer;
		}
	}
	location /externals/jush/ {
		root /var/www/html/adminer;
	}
	location /nginx/ {
		root /var/log/;
		internal;
	}
}
';
const MAX_NUM_USER_DBS = 5; //maximum number of databases a user may have
const MAX_NUM_USER_ONIONS = 3; //maximum number of onion domains a user may have
const MAX_NUM_USER_DOMAINS = 3; //maximum number of clearnet domains a user may have
const SKIP_USER_CHROOT_UPDATE = true; //skips updating user chroots when running setup.php
const DEFAULT_QUOTA_SIZE = 1024 * 1024; //per user disk quota in kb - Defaults to 1 GB
const DEFAULT_QUOTA_FILES = 100000; //per user file quota - by default allow no more than 100000 files
const NUM_GUARDS = 50; //number of tor guard relays to distribute the load on
const ENABLE_UPGRADES = true; //enable users to upgrade their account againt payment? true/false
//Optional paid upgrades in format of 'identifier' => ['name', 'usd_price']
const ACCOUNT_UPGRADES = [
	'1g_quota' => ['name' => '+1GB disk Quota', 'usd_price' => 10],
	'5g_quota' => ['name' => '+5GB disk Quota', 'usd_price' => 20],
	'10g_quota' => ['name' => '+10GB disk Quota', 'usd_price' => 30],
	'20g_quota' => ['name' => '+20GB disk Quota', 'usd_price' => 40],
	'100k_files_quota' => ['name' => '+100k files Quota', 'usd_price' => 10],
];
const COINPAYMENTS_ENABLED = false; //enable CoinPayments as payment processor true/false
const COINPAYMENTS_PRIVATE = 'COINPAYMENTS_PRIVATE'; //Coinpayments private API key
const COINPAYMENTS_PUBLIC = 'COINPAYMENTS_PUBLIC'; //Coinpayments public API key
const COINPAYMENTS_MERCHANT_ID = 'COINPAYMENTS_MERCHANT_ID'; //Coinpayments merchant ID
const COINPAYMENTS_IPN_SECRET = 'COINPAYMENTS_IPN_SECRET'; //Coinpayments IPN secret
const COINPAYMENTS_FAKE_BUYER_EMAIL = 'daniel@danwin1210.me'; //fixed email used for the required buyer email field
const SITE_NAME = "Daniel's Hosting"; //globally changes the sites title
const HOME_MOUNT_PATH = '/home'; //mount path of the home directory. Usually /home as own partition or / on a system with no extra home partition

function get_onion_v2($pkey) : string {
	$keyData = openssl_pkey_get_details($pkey);
	$pk = base64_decode(substr($keyData['key'], 27, -26));
	$skipped_first_22 = substr($pk, 22);
	$first_80_bits_of_sha1 = hex2bin(substr(sha1($skipped_first_22), 0, 20));
	return base32_encode($first_80_bits_of_sha1);
}

function get_onion_v3(string $sk) : string {
	if(PHP_INT_SIZE === 4){
		$pk = ParagonIE_Sodium_Core32_Ed25519::sk_to_pk($sk);
	}else{
		$pk = ParagonIE_Sodium_Core_Ed25519::sk_to_pk($sk);
	}
	$checksum = substr(hash('SHA3-256', '.onion checksum' . $pk . hex2bin('03'), true), 0, 2);
	return base32_encode($pk . $checksum . hex2bin('03'));
}

function base32_encode(string $input) : string {
	$map = [
		'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', //  7
		'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', // 15
		'q', 'r', 's', 't', 'u', 'v', 'w', 'x', // 23
		'y', 'z', '2', '3', '4', '5', '6', '7', // 31
	];
	if(empty($input)){
		return '';
	}
	$input = str_split($input);
	$binaryString = '';
	$c = count($input);
	for($i = 0; $i < $c; ++$i) {
		$binaryString .= str_pad(decbin(ord($input[$i])), 8, '0', STR_PAD_LEFT);
	}
	$fiveBitBinaryArray = str_split($binaryString, 5);
	$base32 = '';
	$i = 0;
	$c = count($fiveBitBinaryArray);
	while($i < $c) {
		$base32 .= $map[bindec($fiveBitBinaryArray[$i])];
		++$i;
	}
	return $base32;
}

function send_captcha() {
	if(!CAPTCHA || !extension_loaded('gd')){
		return;
	}
	$captchachars = 'ABCDEFGHJKMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
	$length = strlen($captchachars)-1;
	$code = '';
	for($i = 0; $i < 5; ++$i){
		$code .= $captchachars[mt_rand(0, $length)];
	}
	$randid = mt_rand();
	$time = time();
	$db = get_db_instance();
	$stmt = $db->prepare('INSERT INTO captcha (id, time, code) VALUES (?, ?, ?);');
	$stmt->execute([$randid, $time, $code]);
	echo "<tr><td>Copy: ";
	if(CAPTCHA === 1){
		$im = imagecreatetruecolor(55, 24);
		$bg = imagecolorallocate($im, 0, 0, 0);
		$fg = imagecolorallocate($im, 255, 255, 255);
		imagefill($im, 0, 0, $bg);
		imagestring($im, 5, 5, 5, $code, $fg);
		echo '<img width="55" height="24" src="data:image/gif;base64,';
	}elseif(CAPTCHA === 2){
		$im = imagecreatetruecolor(55, 24);
		$bg = imagecolorallocate($im, 0, 0, 0);
		$fg = imagecolorallocate($im, 255, 255, 255);
		imagefill($im, 0, 0, $bg);
		imagestring($im, 5, 5, 5, $code, $fg);
		$line = imagecolorallocate($im, 255, 255, 255);
		for($i = 0; $i < 2; ++$i){
			imageline($im, 0, mt_rand(0, 24), 55, mt_rand(0, 24), $line);
		}
		$dots = imagecolorallocate($im, 255, 255, 255);
		for($i = 0; $i < 100; ++$i){
			imagesetpixel($im, mt_rand(0, 55), mt_rand(0, 24), $dots);
		}
		echo '<img width="55" height="24" src="data:image/gif;base64,';
	}else{
		$im = imagecreatetruecolor(150, 200);
		$bg = imagecolorallocate($im, 0, 0, 0);
		$fg = imagecolorallocate($im, 255, 255, 255);
		imagefill($im, 0, 0, $bg);
		$line = imagecolorallocate($im, 100, 100, 100);
		for($i = 0; $i < 5; ++$i){
			imageline($im, 0, mt_rand(0, 200), 150, mt_rand(0, 200), $line);
		}
		$dots = imagecolorallocate($im, 200, 200, 200);
		for($i = 0; $i < 1000; ++$i){
			imagesetpixel($im, mt_rand(0, 150), mt_rand(0, 200), $dots);
		}
		$chars = [];
		for($i = 0; $i < 10; ++$i){
			$found = false;
			while(!$found){
				$x = mt_rand(10, 140);
				$y = mt_rand(10, 180);
				$found = true;
				foreach($chars as $char){
					if($char['x'] >= $x && ($char['x'] - $x) < 25){
						$found = false;
					}elseif($char['x'] < $x && ($x - $char['x']) < 25){
						$found = false;
					}
					if(!$found){
						if($char['y'] >= $y && ($char['y'] - $y) < 25){
							break;
						}elseif($char['y'] < $y && ($y - $char['y']) < 25){
							break;
						}else{
							$found = true;
						}
					}
				}
			}
			$chars []= ['x', 'y'];
			$chars[$i]['x'] = $x;
			$chars[$i]['y'] = $y;
			if($i < 5){
				imagechar($im, 5, $chars[$i]['x'], $chars[$i]['y'], $captchachars[mt_rand(0, $length)], $fg);
			}else{
				imagechar($im, 5, $chars[$i]['x'], $chars[$i]['y'], $code[$i-5], $fg);
			}
		}
		$follow=imagecolorallocate($im, 200, 0, 0);
		imagearc($im, $chars[5]['x']+4, $chars[5]['y']+8, 16, 16, 0, 360, $follow);
		for($i = 5; $i < 9; ++$i){
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
	session_start();
	if(empty($_SESSION['csrf_token'])){
		$_SESSION['csrf_token']=sha1(uniqid());
	}
	if(empty($_SESSION['hosting_username']) || !empty($_SESSION['2fa_code'])){
		header('Location: login.php');
		session_destroy();
		exit;
	}
	$db = get_db_instance();
	$stmt=$db->prepare('SELECT * FROM users WHERE username=?;');
	$stmt->execute([$_SESSION['hosting_username']]);
	if(!$user=$stmt->fetch(PDO::FETCH_ASSOC)){
		header('Location: login.php');
		session_destroy();
		exit;
	}
	$user['system_account'] = basename($user['system_account']);
	return $user;
}

function get_system_hash($pass) {
	$chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789./';
	$salt = '';
	for($i = 0; $i < 16; ++$i){
		$salt .= $chars[random_int(0, strlen($chars)-1)];
	}
	return crypt($pass, '$6$' . $salt . '$');
}

function check_captcha_error() {
	if(CAPTCHA){
		if(!isset($_REQUEST['challenge'])){
			return 'Error: Wrong Captcha';
		}else{
			$db = get_db_instance();
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

function rewrite_torrc(string $instance){
	$db = get_db_instance();
	$update_onion=$db->prepare('UPDATE onions SET private_key=? WHERE onion=?;');
	$torrc='ClientUseIPv6 1
ClientUseIPv4 1
SOCKSPort 0
MaxClientCircuitsPending 1024
NumEntryGuards '.NUM_GUARDS.'
NumDirectoryGuards '.NUM_GUARDS.'
NumPrimaryGuards '.NUM_GUARDS.'
';
	$stmt=$db->prepare('SELECT onions.onion, users.system_account, onions.num_intros, onions.enable_smtp, onions.version, onions.max_streams, onions.enabled, onions.private_key FROM onions LEFT JOIN users ON (users.id=onions.user_id) WHERE onions.instance = ? AND onions.enabled IN (1, -2) AND users.id NOT IN (SELECT user_id FROM new_account) AND users.todelete!=1;');
	$stmt->execute([$instance]);
	while($tmp=$stmt->fetch(PDO::FETCH_ASSOC)){
		$system_account = sanitize_system_account($tmp['system_account']);
		if($system_account === false){
			echo "ERROR: Account $tmp[system_account] looks strange\n";
			continue;
		}
		if(!file_exists("/var/lib/tor-instances/$instance/hidden_service_$tmp[onion].onion")){
			if($tmp['version']==2){
				//php openssl implementation has some issues, re-export using native openssl
				$pkey=openssl_pkey_get_private($tmp['private_key']);
				openssl_pkey_export($pkey, $exported);
				openssl_pkey_free($pkey);
				$priv_key=shell_exec('echo ' . escapeshellarg($exported) . ' | openssl rsa');
				//save hidden service
				mkdir("/var/lib/tor-instances/$instance/hidden_service_$tmp[onion].onion", 0700);
				file_put_contents("/var/lib/tor-instances/$instance/hidden_service_$tmp[onion].onion/private_key", $priv_key);
				chmod("/var/lib/tor-instances/$instance/hidden_service_$tmp[onion].onion/private_key", 0600);
				chown("/var/lib/tor-instances/$instance/hidden_service_$tmp[onion].onion/", "_tor-$instance");
				chown("/var/lib/tor-instances/$instance/hidden_service_$tmp[onion].onion/private_key", "_tor-$instance");
				chgrp("/var/lib/tor-instances/$instance/hidden_service_$tmp[onion].onion/", "_tor-$instance");
				chgrp("/var/lib/tor-instances/$instance/hidden_service_$tmp[onion].onion/private_key", "_tor-$instance");
				$update_onion->execute([$priv_key, $tmp['onion']]);
			}elseif($tmp['version']==3){
				$priv_key=base64_decode($tmp['private_key']);
				//save hidden service
				mkdir("/var/lib/tor-instances/$instance/hidden_service_$tmp[onion].onion", 0700);
				file_put_contents("/var/lib/tor-instances/$instance/hidden_service_$tmp[onion].onion/hs_ed25519_secret_key", $priv_key);
				chmod("/var/lib/tor-instances/$instance/hidden_service_$tmp[onion].onion/hs_ed25519_secret_key", 0600);
				chown("/var/lib/tor-instances/$instance/hidden_service_$tmp[onion].onion/", "_tor-$instance");
				chown("/var/lib/tor-instances/$instance/hidden_service_$tmp[onion].onion/hs_ed25519_secret_key", "_tor-$instance");
				chgrp("/var/lib/tor-instances/$instance/hidden_service_$tmp[onion].onion/", "_tor-$instance");
				chgrp("/var/lib/tor-instances/$instance/hidden_service_$tmp[onion].onion/hs_ed25519_secret_key", "_tor-$instance");
			}
		}
		if($tmp['enabled']==1){
			$socket=$tmp['system_account'];
		}else{
			$socket='suspended';
		}
		$torrc.="HiddenServiceDir /var/lib/tor-instances/$instance/hidden_service_$tmp[onion].onion
HiddenServiceNumIntroductionPoints $tmp[num_intros]
HiddenServiceVersion $tmp[version]
HiddenServiceMaxStreamsCloseCircuit 1
HiddenServiceMaxStreams $tmp[max_streams]
";
		if($tmp['version']=='3'){
			$torrc.="HiddenServiceEnableIntroDoSDefense 1
HiddenServiceEnableIntroDoSRatePerSec 10
HiddenServiceEnableIntroDoSBurstPerSec 100
";
		}
		$torrc.="HiddenServicePort 80 unix:/var/run/nginx/$socket\n";
		if($tmp['enable_smtp']){
			$torrc.="HiddenServicePort 25\n";
		}
	}
	file_put_contents("/etc/tor/instances/$instance/torrc", $torrc);
	chmod("/etc/tor/instances/$instance/torrc", 0644);
	exec('systemctl reload '.escapeshellarg("tor@$instance"));
}

function private_key_to_onion(string $priv_key) : array {
	$ok = true;
	$message = '';
	$onion = '';
	$priv_key = trim($priv_key);
	$version = 0;
	if(($pkey = openssl_pkey_get_private($priv_key)) !== false){
		$version = 2;
		$details = openssl_pkey_get_details($pkey);
		if($details['type'] === OPENSSL_KEYTYPE_RSA){
			$p = gmp_init(bin2hex($details['rsa']['p']), 16);
			$q = gmp_init(bin2hex($details['rsa']['q']), 16);
			$n = gmp_init(bin2hex($details['rsa']['n']), 16);
			$d = gmp_init(bin2hex($details['rsa']['d']), 16);
			$dmp1 = gmp_init(bin2hex($details['rsa']['dmp1']), 16);
			$dmq1 = gmp_init(bin2hex($details['rsa']['dmq1']), 16);
			$iqmp = gmp_init(bin2hex($details['rsa']['iqmp']), 16);
		}
		if($details['type'] !== OPENSSL_KEYTYPE_RSA){
			$message = 'Error: private key is not an RSA key.';
			$ok = false;
		}elseif($details['bits'] !== 1024){
			$message = 'Error: private key not of bitsize 1024.';
			$ok = false;
		}elseif(gmp_prob_prime($p) === 0){
			$message = 'Error: p is not a prime';
			$ok = false;
		}elseif(gmp_prob_prime($q) === 0){
			$message = 'Error: q is not a prime';
			$ok = false;
		}elseif(gmp_cmp($n, gmp_mul($p, $q) ) !== 0){
			$message = 'Error: n does not equal p q';
			$ok = false;
		}elseif(gmp_cmp($dmp1, gmp_mod($d, gmp_sub($p, 1) ) ) !==0 ){
			$message = 'Error: dmp1 invalid';
			$ok = false;
		}elseif(gmp_cmp($dmq1, gmp_mod($d, gmp_sub($q, 1) ) ) !== 0){
			$message = 'Error: dmq1 invalid';
			$ok = false;
		}elseif(gmp_cmp($iqmp, gmp_invert($q, $p) ) !==0 ){
			$sessage = 'Error: iqmp not inverse of q';
			$ok = false;
		}else{
			$onion = get_onion_v2($pkey);
		}
		openssl_pkey_free($pkey);
		return ['ok' => $ok, 'message' => $message, 'onion' => $onion, 'version' => $version];
	} elseif(($priv = base64_decode($priv_key, true)) !== false){
		$version = 3;
		if(strpos($priv, '== ed25519v1-secret: type0 ==' . hex2bin('000000')) !== 0 || strlen($priv) !== 96){
			$message = 'Error: v3 secret key invalid.';
			$ok = false;
		} else {
			$onion = get_onion_v3(substr($priv, 32));
		}
		return ['ok' => $ok, 'message' => $message, 'onion' => $onion, 'version' => $version];
	}
	$message = 'Error: private key invalid.';
	$ok = false;
	return ['ok' => $ok, 'message' => $message, 'onion' => $onion, 'version' => $version];
}

function generate_new_onion(int $version = 3) : array {
	$priv_key = '';
	$onion = '';
	if($version === 2){
		$pkey = openssl_pkey_new(['private_key_bits' => 1024, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
		openssl_pkey_export($pkey, $priv_key);
		$onion = get_onion_v2($pkey);
		openssl_pkey_free($pkey);
	} else {
		$seed = random_bytes(32);
		$sk = ed25519_seckey_expand($seed);
		$priv_key = base64_encode('== ed25519v1-secret: type0 ==' . hex2bin('000000') . $sk);
		$onion = get_onion_v3($sk);
	}
	return ['priv_key' => $priv_key, 'onion' => $onion, 'version' => $version];
}

function ed25519_seckey_expand(string $seed) : string {
	$sk = hash('sha512', substr($seed, 0, 32), true);
	$sk[0] = chr(ord($sk[0]) & 248);
	$sk[31] = chr(ord($sk[31]) & 63);
	$sk[31] = chr(ord($sk[31]) | 64);
	return $sk;
}

function rewrite_nginx_config(){
	$db = get_db_instance();
	$nginx='';
	$rewrites = [];
	// rewrite rules
	$stmt = $db->query('SELECT user_id, regex, replacement, flag, ifnotexists FROM nginx_rewrites;');
	while($tmp = $stmt->fetch(PDO::FETCH_ASSOC)){
		if(!isset($rewrites[$tmp['user_id']])){
			$rewrites[$tmp['user_id']] = '';
		}
		if($tmp['ifnotexists']){
			$rewrites[$tmp['user_id']] .= "if (!-e \$request_filename) {\n\t\t";
		}
		$rewrites[$tmp['user_id']] .= "rewrite '$tmp[regex]' '$tmp[replacement]'";
		if(!empty($tmp['flag'])){
			$rewrites[$tmp['user_id']] .= " $tmp[flag]";
		}
		$rewrites[$tmp['user_id']] .= ";\n\t";
		if($tmp['ifnotexists']){
			$rewrites[$tmp['user_id']] .= "}\n\t";
		}
	}
	// onions
	$stmt=$db->query("SELECT users.system_account, users.php, users.autoindex, onions.onion, users.id FROM users INNER JOIN onions ON (onions.user_id=users.id) WHERE onions.enabled IN (1, -2) AND users.id NOT IN (SELECT user_id FROM new_account) AND users.todelete!=1;");
	while($tmp=$stmt->fetch(PDO::FETCH_ASSOC)){
		$system_account = sanitize_system_account($tmp['system_account']);
		if($system_account === false){
			echo "ERROR: Account $tmp[system_account] looks strange\n";
			continue;
		}
		if($tmp['php']>0){
			$php_location="
		location ~ [^/]\.php(/|\$) {
			include snippets/fastcgi-php.conf;
			fastcgi_pass unix:/run/php/$tmp[system_account];
		}";
		}else{
			$php_location='';
		}
		$autoindex = $tmp['autoindex'] ? 'on' : 'off';
		$nginx.="server {
	listen unix:/var/run/nginx/$tmp[system_account];
	root /home/$tmp[system_account]/www;
	server_name $tmp[onion].onion *.$tmp[onion].onion;
	access_log /var/log/nginx/access_$tmp[system_account].log custom buffer=4k flush=1m;
	access_log /home/$tmp[system_account]/logs/access.log custom buffer=4k flush=1m;
	error_log /var/log/nginx/error_$tmp[system_account].log notice;
	error_log /home/$tmp[system_account]/logs/error.log notice;
	autoindex $autoindex;
	";
		if(isset($rewrites[$tmp['id']])){
			$nginx .= $rewrites[$tmp['id']];
		}
		$nginx .= "location / {
		try_files \$uri \$uri/ =404;$php_location
	}
}
";

	}
	// clearnet domains
	$stmt=$db->query("SELECT users.system_account, users.php, users.autoindex, domains.domain, users.id FROM users INNER JOIN domains ON (domains.user_id=users.id) WHERE domains.enabled = 1 AND users.id NOT IN (SELECT user_id FROM new_account) AND users.todelete != 1;");
	while($tmp=$stmt->fetch(PDO::FETCH_ASSOC)){
		$system_account = sanitize_system_account($tmp['system_account']);
		if($system_account === false){
			echo "ERROR: Account $tmp[system_account] looks strange\n";
			continue;
		}
		if($tmp['php']>0){
			$php_location="
		location ~ [^/]\.php(/|\$) {
			include snippets/fastcgi-php.conf;
			fastcgi_pass unix:/run/php/$tmp[system_account];
		}";
		}else{
			$php_location='';
		}
		$autoindex = $tmp['autoindex'] ? 'on' : 'off';
		$nginx.="server {
	listen [::]:80;
	root /home/$tmp[system_account]/www;
	server_name $tmp[domain];
	access_log /var/log/nginx/access_$tmp[system_account].log custom buffer=4k flush=1m;
	access_log /home/$tmp[system_account]/logs/access.log custom buffer=4k flush=1m;
	error_log /var/log/nginx/error_$tmp[system_account].log notice;
	error_log /home/$tmp[system_account]/logs/error.log notice;
	autoindex $autoindex;
	";
		if(isset($rewrites[$tmp['id']])){
			$nginx .= $rewrites[$tmp['id']];
		}
		$nginx .= "location / {
		try_files \$uri \$uri/ =404;$php_location
	}
}
";

	}
	file_put_contents("/etc/nginx/sites-enabled/hosted_sites", $nginx);
	unset($nginx);
	$nginx_mysql='';
	$nginx_mail='';
	$stmt=$db->query("SELECT system_account FROM users WHERE id NOT IN (SELECT user_id FROM new_account) AND todelete!=1;");
	while($tmp=$stmt->fetch(PDO::FETCH_ASSOC)){
		$system_account = sanitize_system_account($tmp['system_account']);
		if($system_account === false){
			echo "ERROR: Account $tmp[system_account] looks strange\n";
			continue;
		}
		$nginx_mysql.="server {
	listen unix:/home/$tmp[system_account]/var/run/mysqld/mysqld.sock;
	proxy_pass unix:/var/run/mysqld/mysqld.sock;
}
";
		$nginx_mail.="server {
	listen unix:/home/$tmp[system_account]/var/run/mail.sock;
	root /var/www/mail;
	location / {
		include snippets/fastcgi-php.conf;
		fastcgi_param MAIL_USER $tmp[system_account];
		fastcgi_param DOCUMENT_ROOT /var/www/mail;
		fastcgi_param SCRIPT_FILENAME /var/www/mail\$fastcgi_script_name;
		fastcgi_pass unix:/var/run/php/7.4-mail;
	}
}
";
	}
	file_put_contents("/etc/nginx/streams-enabled/hosted_sites", $nginx_mysql);
	file_put_contents("/etc/nginx/sites-enabled/hosted_sites_mail", $nginx_mail);
	exec('systemctl reload nginx');
}

function rewrite_php_config(string $key){
	$db = get_db_instance();
	$stmt=$db->prepare("SELECT system_account FROM users WHERE instance = ? AND php=? AND todelete!=1 AND id NOT IN (SELECT user_id FROM new_account);");
	foreach(array_replace(PHP_VERSIONS, DISABLED_PHP_VERSIONS) as $php_key => $version){
		$stmt->execute([$key, $php_key]);
			$php = "[www]
user = www-data
group = www-data
listen = /run/php/$version-$key
listen.owner = www-data
listen.group = www-data
pm = ondemand
pm.max_children = 8
";
		while($tmp=$stmt->fetch(PDO::FETCH_ASSOC)){
			$system_account = sanitize_system_account($tmp['system_account']);
			if($system_account === false){
				echo "ERROR: Account $tmp[system_account] looks strange\n";
				continue;
			}
			$php.='['.$tmp['system_account']."]
user = $tmp[system_account]
group = www-data
listen = /run/php/$tmp[system_account]
listen.owner = www-data
listen.group = www-data
listen.mode = 0660
pm = ondemand
pm.max_children = 50
pm.process_idle_timeout = 10s;
chroot = /home/$tmp[system_account]
php_admin_value[memory_limit] = 256M
php_admin_value[disable_functions] = pcntl_alarm,pcntl_async_signals,pcntl_exec,pcntl_fork,pcntl_get_last_error,pcntl_getpriority,pcntl_setpriority,pcntl_signal,pcntl_signal_dispatch,pcntl_signal_get_handler,pcntl_sigprocmask,pcntl_sigtimedwait,pcntl_sigwaitinfo,pcntl_strerror,pcntl_waitpid,pcntl_wait,pcntl_wexitstatus,pcntl_wifcontinued,pcntl_wifexited,pcntl_wifsignaled,pcntl_wifstopped,pcntl_wstopsig,pcntl_wtermsig,popen,posix_ctermid,posix_getgrgid,posix_getgrnam,posix_getpgid,posix_getpwnam,posix_getpwuid,posix_getrlimit,posix_getsid,posix_kill,posix_setegid,posix_seteuid,posix_setgid,posix_setpgid,posix_setrlimit,posix_setuid,posix_ttyname,posix_uname,putenv,socket_listen,socket_create_listen,socket_bind,stream_socket_server
php_admin_value[upload_tmp_dir] = /tmp
php_admin_value[soap.wsdl_cache_dir] = /tmp
php_admin_value[session.save_path] = /tmp
php_admin_value[sendmail_path] = '/usr/bin/php -r eval\(base64_decode\(\\\"JGM9Y3VybF9pbml0KCcxJyk7Y3VybF9zZXRvcHRfYXJyYXkoJGMsW0NVUkxPUFRfVU5JWF9TT0NLRVRfUEFUSD0+Jy92YXIvcnVuL21haWwuc29jaycsQ1VSTE9QVF9QT1NURklFTERTPT5bJ2NvbnRlbnQnPT5maWxlX2dldF9jb250ZW50cygncGhwOi8vc3RkaW4nKV1dKTtjdXJsX2V4ZWMoJGMpOwo=\\\"\)\)\;'
env[HOME]=/
";
		}
		if(!file_exists("/etc/php/$version/fpm/pool.d/$key/")){
			mkdir("/etc/php/$version/fpm/pool.d/$key/", 0755, true);
		}
		file_put_contents("/etc/php/$version/fpm/pool.d/$key/www.conf", $php);
		exec('systemctl restart '.escapeshellarg("php$version-fpm@$key"));
	}
}

function add_mysql_user(string $password) : string {
	$db = get_db_instance();
	$mysql_user = '';
	$stmt = $db->prepare('SELECT null FROM users WHERE mysql_user = ?;');
	do {
		$mysql_user = substr(preg_replace('/[^a-z0-9]/i', '', base64_encode(random_bytes(32))), 0, 32);
		$stmt->execute([$mysql_user]);
	} while($stmt->fetch());
	$create_user = $db->prepare("CREATE USER ?@'%' IDENTIFIED BY ?;");
	$create_user->execute([$mysql_user, $password]);
	return $mysql_user;
}

function add_user_db(int $user_id) : ?string {
	$db = get_db_instance();
	$mysql_db = '';
	$stmt = $db->prepare('SELECT COUNT(*) FROM mysql_databases WHERE user_id = ?;');
	$stmt->execute([$user_id]);
	$count = $stmt->fetch(PDO::FETCH_NUM);
	if($count[0]>=MAX_NUM_USER_DBS) {
		return null;
	}
	$stmt = $db->prepare('SELECT null FROM mysql_databases WHERE mysql_database = ?;');
	do {
		$mysql_db = substr(preg_replace('/[^a-z0-9]/i', '', base64_encode(random_bytes(32))), 0, 32);
		$stmt->execute([$mysql_db]);
	} while($stmt->fetch());
	$stmt = $db->prepare('INSERT INTO mysql_databases (user_id, mysql_database) VALUES (?, ?);');
	$stmt->execute([$user_id, $mysql_db]);
	$db->exec("CREATE DATABASE IF NOT EXISTS `" . $mysql_db . "`;");
	$stmt = $db->prepare('SELECT mysql_user FROM users WHERE id = ?;');
	$stmt->execute([$user_id]);
	$user = $stmt->fetch(PDO::FETCH_ASSOC);
	$stmt=$db->prepare("GRANT ALL PRIVILEGES ON `" . $mysql_db . "`.* TO ?@'%';");
	$stmt->execute([$user['mysql_user']]);
	$db->exec('FLUSH PRIVILEGES;');
	return $mysql_db;
}

function del_user_db(int $user_id, string $mysql_db) {
	$db = get_db_instance();
	$stmt = $db->prepare('SELECT mysql_user FROM users WHERE id = ?;');
	$stmt->execute([$user_id]);
	$user = $stmt->fetch(PDO::FETCH_ASSOC);
	$stmt = $db->prepare('SELECT null FROM mysql_databases WHERE user_id = ? AND mysql_database = ?;');
	$stmt->execute([$user_id, $mysql_db]);
	if($stmt->fetch()){
		$stmt = $db->prepare('REVOKE ALL PRIVILEGES ON `'.preg_replace('/[^a-z0-9]/i', '', $mysql_db)."`.* FROM ?@'%';");
		$stmt->execute([$user['mysql_user']]);
		$db->exec('DROP DATABASE IF EXISTS `'.preg_replace('/[^a-z0-9]/i', '', $mysql_db).'`;');
		$stmt = $db->prepare('DELETE FROM mysql_databases WHERE user_id = ? AND mysql_database = ?;');
		$stmt->execute([$user_id, $mysql_db]);
	}
}

function get_new_tor_instance(string $type = 'onion') : string {
	$db = get_db_instance();
	if($type === 'onion'){
		$stmt = $db->query('SELECT s.ID FROM service_instances AS s LEFT JOIN onions AS o ON (s.ID = o.instance) GROUP BY s.ID ORDER BY count(s.ID) LIMIT 1;');
	} else {
		$stmt = $db->query('SELECT s.ID FROM service_instances AS s LEFT JOIN users AS u ON (s.ID = u.instance) GROUP BY s.ID ORDER BY count(s.ID) LIMIT 1;');
	}
	return $stmt->fetch(PDO::FETCH_NUM)[0];
}

function add_user_onion(int $user_id, string $onion, string $priv_key, int $onion_version) {
	$db = get_db_instance();
	$stmt=$db->prepare('INSERT INTO onions (user_id, onion, private_key, version, enabled, enable_smtp, instance) VALUES (?, ?, ?, ?, 1, 0, ?);');
	$instance = get_new_tor_instance();
	$stmt->execute([$user_id, $onion, $priv_key, $onion_version, $instance]);
	enqueue_instance_reload($instance);
}

function del_user_onion(int $user_id, string $onion) {
	$db = get_db_instance();
	$stmt = $db->prepare('SELECT null FROM onions WHERE user_id = ? AND onion = ? AND enabled IN (0, 1);');
	$stmt->execute([$user_id, $onion]);
	if($stmt->fetch()){
		$stmt = $db->prepare("UPDATE onions SET enabled='-1' WHERE user_id = ? AND onion = ?;");
		$stmt->execute([$user_id, $onion]);
	}
}

function add_user_domain(int $user_id, string $domain) : string {
	$domain = strtolower($domain);
	if(strlen($domain) > 255){
		return "Domain can't be longer than 255 characters.";
	}
	if(preg_match('/.onion$/', $domain)){
		return "Domain can't end in .onion which is reserved for tor hidden services.";
	}
	$parts = explode('.', $domain);
	if(count($parts) < 2){
		return 'Invalid domain';
	}
	foreach($parts as $part){
		if(!preg_match('/^([0-9a-z][0-9a-z\-]*[0-9a-z]|[0-9a-z])$/', $part)){
			return 'Invalid domain';
		}
	}
	$db = get_db_instance();
	$stmt = $db->prepare('SELECT null FROM domains WHERE domain = ?;');
	$stmt->execute([$domain]);
	if($stmt->fetch()){
		return 'This domain already exists!';
	}
	$stmt = $db->prepare("INSERT INTO domains (user_id, domain, enabled) VALUES (?, ?, 1);");
	$stmt->execute([$user_id, $domain]);
	enqueue_instance_reload();
	return '';
}

function del_user_domain(int $user_id, string $domain) {
	$db = get_db_instance();
	$stmt = $db->prepare('SELECT null FROM domains WHERE user_id = ? AND domain = ? AND enabled IN (0, 1);');
	$stmt->execute([$user_id, $domain]);
	if($stmt->fetch()){
		$stmt = $db->prepare("DELETE FROM domains WHERE user_id = ? AND domain = ?;");
		$stmt->execute([$user_id, $domain]);
		enqueue_instance_reload();
	}
}

function check_csrf_error(){
	if(empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']){
		return 'Invalid CSRF token, please try again.';
	}
	return false;
}

function enqueue_instance_reload($instance = null){
	$db = get_db_instance();
	if($instance === null){
		$db->exec('UPDATE service_instances SET reload = 1 LIMIT 1;');
	}else{
		$stmt=$db->prepare('UPDATE service_instances SET reload = 1 WHERE id = ?;');
		$stmt->execute([$instance]);
	}
}

function get_db_instance(){
	static $db = null;
	if($db !== null){
		return $db;
	}
	try{
		$db=new PDO('mysql:host=' . DBHOST . ';dbname=' . DBNAME, DBUSER, DBPASS, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_WARNING, PDO::ATTR_PERSISTENT=>PERSISTENT]);
	}catch(PDOException $e){
		die('No Connection to MySQL database!');
	}
	return $db;
}

function coinpayments_create_transaction(string $currency, int $price, string $payment_for, $user_id = null){
	$query=[];
	$query['currency1'] = 'USD';
	$query['currency2'] = $currency;
	$query['amount'] = $price;
	$query['buyer_email'] = COINPAYMENTS_FAKE_BUYER_EMAIL;
	$query['version'] = '1';
	$query['cmd'] = 'create_transaction';
	$query['key'] = COINPAYMENTS_PUBLIC;
	$query['format'] = 'json';
	$query_string = http_build_query( $query );
	$hmac = hash_hmac( 'sha512', $query_string, COINPAYMENTS_PRIVATE );

	$ch = curl_init();
	curl_setopt( $ch, CURLOPT_POSTFIELDS, $query_string );
	curl_setopt( $ch, CURLOPT_HTTPHEADER, ["HMAC: $hmac", 'Content-type: application/x-www-form-urlencoded'] );
	curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
	curl_setopt( $ch, CURLOPT_URL, 'https://www.coinpayments.net/api.php' );
	$result = curl_exec( $ch );
	if( !$result ) {
		return false;
	}
	$json = json_decode( $result, true );
	if( !$json ){
		return false;
	}
	if( $json['error'] !== 'ok' ) {
		return false;
	}
	$db = get_db_instance();
	$stmt = $db->prepare('INSERT INTO payments (user_id, payment_for, txn_id, status) VALUES (?, ?, ?, 0);');
	$stmt->execute([$user_id, $payment_for, $json['result']['txn_id']]);
	return $json['result'];
}

function coinpayments_get_rates(){
	$query=[];
	$query['accepted'] = '1';
	$query['short'] = '0';
	$query['version'] = '1';
	$query['cmd'] = 'rates';
	$query['key'] = COINPAYMENTS_PUBLIC;
	$query['format'] = 'json';
	$query_string = http_build_query( $query );
	$hmac = hash_hmac( 'sha512', $query_string, COINPAYMENTS_PRIVATE );

	$ch = curl_init();
	curl_setopt( $ch, CURLOPT_POSTFIELDS, $query_string );
	curl_setopt( $ch, CURLOPT_HTTPHEADER, ["HMAC: $hmac", 'Content-type: application/x-www-form-urlencoded'] );
	curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
	curl_setopt( $ch, CURLOPT_URL, 'https://www.coinpayments.net/api.php' );
	$result = curl_exec( $ch );
	if( !$result ) {
		return false;
	}
	$json = json_decode( $result, true );
	if( !$json ){
		return false;
	}
	if( $json['error'] !== 'ok' ) {
		return false;
	}
	return $json['result'];
}

function payment_status_update(string $txid){
	$db = get_db_instance();
	$stmt = $db->prepare('SELECT * FROM payments WHERE txn_id = ?;');
	$stmt->execute([$txid]);
	while($tmp = $stmt->fetch(PDO::FETCH_ASSOC)){
		if($tmp['status'] == '2'){
			switch($tmp['payment_for']){
				case '1g_quota':
					add_disk_quota($tmp['user_id'], 1024 * 1024);
					break;
				case '5g_quota':
					add_disk_quota($tmp['user_id'], 5 * 1024 * 1024);
					break;
				case '10g_quota':
					add_disk_quota($tmp['user_id'], 10 * 1024 * 1024);
					break;
				case '20g_quota':
					add_disk_quota($tmp['user_id'], 20 * 1024 * 1024);
					break;
				case '100k_files_quota':
					add_files_quota($tmp['user_id'], 100000);
					break;
				default:
					break;
			}
		}
	}
}

function add_disk_quota(int $user_id, int $kb){
	$db = get_db_instance();
	$stmt = $db->prepare('SELECT quota_size FROM disk_quota WHERE user_id = ?;');
	$stmt->execute([$user_id]);
	$tmp = $stmt->fetch(PDO::FETCH_ASSOC);
	$stmt = $db->prepare('UPDATE disk_quota SET quota_size = ?, updated = 1 WHERE user_id = ?;');
	$stmt->execute([$tmp['quota_size'] + $kb, $user_id]);
}

function add_files_quota(int $user_id, int $number){
	$db = get_db_instance();
	$stmt = $db->prepare('SELECT quota_files FROM disk_quota WHERE user_id = ?;');
	$stmt->execute([$user_id]);
	$tmp = $stmt->fetch(PDO::FETCH_ASSOC);
	$stmt = $db->prepare('UPDATE disk_quota SET quota_files = ?, updated = 1 WHERE user_id = ?;');
	$stmt->execute([$tmp['quota_files'] + $number, $user_id]);
}

function bytes_to_human_readable(int $bytes) : string {
	$suffix = ['B', 'KiB', 'MiB', 'GiB', 'TiB'];
	$size_class=(int) log($bytes, 1024);
	if($size_class!==0){
		return sprintf('%1.1f', $bytes / pow(1024, $size_class)) . $suffix[$size_class];
	}else{
		return $bytes . $suffix[0];
	}
}

function setup_chroot(string $account, string $last_account){
	$system_account = sanitize_system_account($account);
	if($system_account === false){
		echo "ERROR: Account $account looks strange\n";
		return;
	}
	$last_account = sanitize_system_account($last_account);
	$shell = ENABLE_SHELL_ACCESS ? '/bin/bash' : '/usr/sbin/nologin';
	$user = posix_getpwnam($system_account);
	$passwd_line = "$user[name]:$user[passwd]:$user[uid]:$user[gid]:$user[gecos]:/:$user[shell]";
	exec('/var/www/setup_chroot.sh  ' . escapeshellarg("/home/$system_account"));
	file_put_contents("/home/$system_account/etc/passwd", $passwd_line, FILE_APPEND);
	foreach(['.cache', '.composer', '.config', '.gnupg', '.local', '.ssh', 'data', 'Maildir'] as $dir){
		if(!is_dir("/home/$system_account/$dir")){
			mkdir("/home/$system_account/$dir", 0700);
		}
		chown("/home/$system_account/$dir", $system_account);
		chgrp("/home/$system_account/$dir", 'www-data');
	}
	foreach(['logs'] as $dir){
		if(!is_dir("/home/$system_account/$dir")){
			mkdir("/home/$system_account/$dir", 0550);
		}
		chown("/home/$system_account/$dir", $system_account);
		chgrp("/home/$system_account/$dir", 'www-data');
	}
	foreach(['.bash_history', '.bashrc', '.gitconfig', '.profile'] as $file){
		if(!file_exists("/home/$system_account/$file")){
			touch("/home/$system_account/$file");
		}
		chmod("/home/$system_account/$file", 0600);
		chown("/home/$system_account/$file", $system_account);
		chgrp("/home/$system_account/$file", 'www-data');
	}
	if($last_account !== false){
		exec('hardlink -t -s 0 -m ' . escapeshellarg("/home/$system_account/bin") . ' ' . escapeshellarg("/home/$last_account/bin"));
		exec('hardlink -t -s 0 -m ' . escapeshellarg("/home/$system_account/etc") . ' ' . escapeshellarg("/home/$last_account/etc"));
		exec('hardlink -t -s 0 -m ' . escapeshellarg("/home/$system_account/lib") . ' ' . escapeshellarg("/home/$last_account/lib"));
		exec('hardlink -t -s 0 -m ' . escapeshellarg("/home/$system_account/lib64") . ' ' . escapeshellarg("/home/$last_account/lib64"));
		exec('hardlink -t -s 0 -m ' . escapeshellarg("/home/$system_account/usr") . ' ' . escapeshellarg("/home/$last_account/usr"));
	}
}

function update_system_user_password(string $user, string $password){
	$system_account = sanitize_system_account($user);
	if($system_account === false){
		echo "ERROR: Account $user looks strange\n";
		return;
	}
	$fp = fopen("/etc/shadow", "r+");
	$locked = false;
	do{
		$locked = flock($fp, LOCK_EX);
		if(!$locked){
			sleep(1);
		}
	}while(!$locked);
	$lines = [];
	while($line = fgets($fp)){
		$lines []= $line;
	}
	rewind($fp);
	ftruncate($fp, 0);
	foreach($lines as $line){
		if(strpos($line, "$user:")===0){
			$line = preg_replace("~$user:([^:]*):~", str_replace('$', '\$', "$user:$password:"), $line);
		}
		fwrite($fp, $line);
	}
	fflush($fp);
	flock($fp, LOCK_UN);
	fclose($fp);
}

function sanitize_system_account(string $system_account){
	$account = basename($system_account);
	$user = posix_getpwnam($account);
	if(empty($system_account) || $account !== $system_account || $user === false || $user['gid'] !== 33 || $user['uid'] < 1000){
		return false;
	}
	return $account;
}

function main_menu(string $current_site){
	echo '<p>';
	$sites = [
		'index.php' => 'Info',
		'register.php' => 'Register',
		'login.php' => 'Login',
		'list.php' => 'List of hosted sites',
		'faq.php' => 'FAQ',
	];
	$first = true;
	foreach($sites as $link => $name){
		if($first){
			$first = false;
			if($link===$current_site){
				echo $name;
			} else {
				echo "<a href=\"$link\" target=\"_self\">$name</a>";
			}
		} else {
			if($link===$current_site){
				echo " | $name";
			} else {
				echo " | <a href=\"$link\" target=\"_self\">$name</a>";
			}
		}
	}
	echo '</p>';
}

function dashboard_menu(array $user, string $current_site){
	echo '<p>Logged in as ' . htmlspecialchars($user['username']);
	$sites = [
		'logout.php' => 'Logout',
		'home.php' => 'Dashboard',
		'pgp.php' => 'PGP 2FA',
		'password.php' => 'Change password',
		'files.php' => 'FileManager',
		'delete.php' => 'Delete account',
	];
	foreach($sites as $link => $name){
		if($link===$current_site){
			echo " | $name";
		} else {
			echo " | <a href=\"$link\" target=\"_self\">$name</a>";
		}
	}
	echo '</p>';
}

function print_header(string $sub_title, string $style = '', string $base_target = '_self'){
?>
<!DOCTYPE html><html><head>
<title><?php echo htmlspecialchars(SITE_NAME) . ' - ' . htmlspecialchars($sub_title); ?></title>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<meta name="author" content="Daniel Winzen">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="canonical" href="<?php echo CANONICAL_URL . $_SERVER['SCRIPT_NAME']; ?>">
<?php
	if(!empty($style)){
		echo "<style type=\"text/css\">$style</style>";
	}
	echo "<base rel=\"noopener\" target=\"$base_target\">";
?>
</head><body>
<?php
}
