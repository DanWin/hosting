<?php
require_once(__DIR__ . '/vendor/autoload.php');
const DBHOST='localhost'; // Database host
const DBUSER='hosting'; // Database user
const DBPASS='MY_PASSWORD'; // Database password
const DBNAME='hosting'; // Database
const PERSISTENT=true; // Use persistent database conection true/false
const DBVERSION=13; //database layout version
const CAPTCHA=0; // Captcha difficulty (0=off, 1=simple, 2=moderate, 3=extreme)
const ADDRESS='dhosting4xxoydyaivckq7tsmtgi4wfs3flpeyitekkmqwu4v4r46syd.onion'; // our own address
const SERVERS=[ //servers and ports we are running on
'dhosting4xxoydyaivckq7tsmtgi4wfs3flpeyitekkmqwu4v4r46syd.onion'=>['sftp'=>22, 'ftp'=>21, 'pop3'=>'110', 'imap'=>'143', 'smtp'=>'25'],
'hosting.danwin1210.me'=>['sftp'=>22, 'ftp'=>21, 'pop3'=>'1995', 'imap'=>'1993', 'smtp'=>'1465']
];
const EMAIL_TO=''; //Send email notifications about new registrations to this address
const INDEX_MD5S=[ //MD5 sums of index.hosting.html files that should be considdered as unchanged for deletion
'd41d8cd98f00b204e9800998ecf8427e', //empty file
'7ae7e9bac6be76f00e0d95347111f037', //default file
'703fac6634bf637f942db8906092d0ab', //new default file
];
const REQUIRE_APPROVAL=false; //require admin approval of new sites? true/false
const ENABLE_SHELL_ACCESS=true; //allows users to login via ssh, when disabled only (s)ftp is allowed - run setup.php to migrate existing accounts
const ADMIN_PASSWORD='MY_PASSWORD'; //password for admin interface
const SERVICE_INSTANCES=['2', '3', '4', '5', '6', '7', 'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z'];
const DISABLED_PHP_VERSIONS=[]; //php versions still installed on the system but no longer offered for new accounts
const PHP_VERSIONS=[4 => '7.3']; //currently active php versions
const DEFAULT_PHP_VERSION='7.3'; //default php version
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
opcache.validate_root=1
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
			fastcgi_param DOCUMENT_ROOT /html;
			fastcgi_param SCRIPT_FILENAME /html$fastcgi_script_name;
			fastcgi_pass unix:/var/run/php/7.3-hosting;
		}
	}
	location /squirrelmail {
		location ~ \.php$ {
			include snippets/fastcgi-php.conf;
			fastcgi_param DOCUMENT_ROOT $document_root;
			fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
			fastcgi_pass unix:/var/run/php/7.3-squirrelmail;
		}
	}
	location /phpmyadmin {
		root /usr/share;
		location ~ \.php$ {
			include snippets/fastcgi-php.conf;
			fastcgi_param DOCUMENT_ROOT /html;
			fastcgi_param SCRIPT_FILENAME /html$fastcgi_script_name;
			fastcgi_pass unix:/run/php/7.3-phpmyadmin;
		}
	}
	location /adminer {
		root /usr/share/adminer;
		location ~ \.php$ {
			include snippets/fastcgi-php.conf;
			fastcgi_param DOCUMENT_ROOT $document_root;
			fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
			fastcgi_pass unix:/run/php/7.3-adminer;
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
const MAX_NUM_USER_DBS = 5; //maximum number of databases a user may have
const MAX_NUM_USER_ONIONS = 3; //maximum number of onion domains a user may have
const MAX_NUM_USER_DOMAINS = 3; //maximum number of clearnet domains a user may have

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
	global $db;
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
	global $db;
	if(empty($_SESSION['csrf_token'])){
		$_SESSION['csrf_token']=sha1(uniqid());
	}
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

function get_system_hash($pass) {
	$chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789./';
	$salt = '';
	for($i = 0; $i < 16; ++$i){
		$salt .= $chars[random_int(0, strlen($chars)-1)];
	}
	return crypt($pass, '$6$' . $salt . '$');
}

function check_captcha_error() {
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
	$stmt=$db->prepare('SELECT onions.onion, users.system_account, onions.num_intros, onions.enable_smtp, onions.version, onions.max_streams, onions.enabled FROM onions LEFT JOIN users ON (users.id=onions.user_id) WHERE onions.onion LIKE ? AND onions.enabled IN (1, -2) AND users.id NOT IN (SELECT user_id FROM new_account) AND users.todelete!=1;');
	$stmt->execute(["$key%"]);
	while($tmp=$stmt->fetch(PDO::FETCH_NUM)){
if($tmp[6]==1){
	$socket=$tmp[1];
}else{
	$socket='suspended';
}
		$torrc.="HiddenServiceDir /var/lib/tor-instances/$key/hidden_service_$tmp[0].onion
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

function private_key_to_onion(string $priv_key) : array {
	$ok = true;
	$message = '';
	$onion = '';
	$priv_key = trim($priv_key);
	$version = 0;
	if(($pkey = openssl_pkey_get_private($priv_key)) !== false){
		$version = 2;
		$details=openssl_pkey_get_details($pkey);
		if($details['bits'] !== 1024){
			$message = 'Error: private key not of bitsize 1024.';
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

function rewrite_nginx_config(PDO $db){
	$nginx='';
	// onions
	$stmt=$db->query("SELECT users.system_account, users.php, users.autoindex, onions.onion FROM users INNER JOIN onions ON (onions.user_id=users.id) WHERE onions.enabled IN (1, -2) AND users.id NOT IN (SELECT user_id FROM new_account) AND users.todelete!=1;");
	while($tmp=$stmt->fetch(PDO::FETCH_ASSOC)){
		if($tmp['php']>0){
			$php_location="
		location ~ [^/]\.php(/|\$) {
			include snippets/fastcgi-php.conf;
			fastcgi_param DOCUMENT_ROOT /www;
			fastcgi_param SCRIPT_FILENAME /www\$fastcgi_script_name;
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
	disable_symlinks on from=/home/$tmp[system_account];
	autoindex $autoindex;
	location / {
		try_files \$uri \$uri/ =404;$php_location
	}
}
";

	}
	// clearnet domains
	$stmt=$db->query("SELECT users.system_account, users.php, users.autoindex, domains.domain FROM users INNER JOIN domains ON (domains.user_id=users.id) WHERE domains.enabled = 1 AND users.id NOT IN (SELECT user_id FROM new_account) AND users.todelete != 1;");
	while($tmp=$stmt->fetch(PDO::FETCH_ASSOC)){
		if($tmp['php']>0){
			$php_location="
		location ~ [^/]\.php(/|\$) {
			include snippets/fastcgi-php.conf;
			fastcgi_param DOCUMENT_ROOT /www;
			fastcgi_param SCRIPT_FILENAME /www\$fastcgi_script_name;
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
	disable_symlinks on from=/home/$tmp[system_account];
	autoindex $autoindex;
	location / {
		try_files \$uri \$uri/ =404;$php_location
	}
}
";

	}
	file_put_contents("/etc/nginx/sites-enabled/hosted_sites", $nginx);
	$nginx='';
	$stmt=$db->query("SELECT system_account FROM users WHERE id NOT IN (SELECT user_id FROM new_account) AND todelete!=1;");
	while($tmp=$stmt->fetch(PDO::FETCH_ASSOC)){
		$nginx.="server {
	listen unix:/home/$tmp[system_account]/var/run/mysqld/mysqld.sock;
	proxy_pass unix:/var/run/mysqld/mysqld.sock;
}
";
	}
	file_put_contents("/etc/nginx/streams-enabled/hosted_sites", $nginx);
	exec("service nginx reload");
}

function rewrite_php_config(PDO $db, string $key){
	$stmt=$db->prepare("SELECT system_account FROM users WHERE system_account LIKE ? AND php=? AND todelete!=1 AND id NOT IN (SELECT user_id FROM new_account);");
	foreach(array_replace(PHP_VERSIONS, DISABLED_PHP_VERSIONS) as $php_key => $version){
		$stmt->execute(["$key%", $php_key]);
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
";
		}
		file_put_contents("/etc/php/$version/fpm/pool.d/$key/www.conf", $php);
		exec("service php$version-fpm@$key restart");
	}
}

function add_mysql_user(PDO $db, string $password) : string {
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

function add_user_db(PDO $db, int $user_id) : ?string {
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

function del_user_db(PDO $db, int $user_id, string $mysql_db) {
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

function del_user_onion(PDO $db, int $user_id, string $onion) {
	$stmt = $db->prepare('SELECT null FROM onions WHERE user_id = ? AND onion = ? AND enabled IN (0, 1);');
	$stmt->execute([$user_id, $onion]);
	if($stmt->fetch()){
		$stmt = $db->prepare("UPDATE onions SET enabled='-1' WHERE user_id = ? AND onion = ?;");
		$stmt->execute([$user_id, $onion]);
	}
}

function add_user_domain(PDO $db, int $user_id, string $domain) : string {
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
	$stmt = $db->prepare('SELECT null FROM domains WHERE domain = ?;');
	$stmt->execute([$domain]);
	if($stmt->fetch()){
		return 'This domain already exists!';
	}
	$stmt = $db->prepare("INSERT INTO domains (user_id, domain, enabled) VALUES (?, ?, 1);");
	$stmt->execute([$user_id, $domain]);
	return '';
}

function del_user_domain(PDO $db, int $user_id, string $domain) {
	$stmt = $db->prepare('SELECT null FROM domains WHERE user_id = ? AND domain = ? AND enabled IN (0, 1);');
	$stmt->execute([$user_id, $domain]);
	if($stmt->fetch()){
		$stmt = $db->prepare("DELETE FROM domains WHERE user_id = ? AND domain = ?;");
		$stmt->execute([$user_id, $domain]);
	}
}

function check_csrf_error(){
	if(empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']){
		return 'Invalid CSRF token, please try again.';
	}
	return false;
}
