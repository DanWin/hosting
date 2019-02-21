<?php
include('../common.php');
header('Content-Type: text/html; charset=UTF-8');
if(isset($_SERVER['HTTP_HOST']) && preg_match('/danwin1210\.(i2p|me)$/', $_SERVER['HTTP_HOST'])){
	if(preg_match('/\.me$/', $_SERVER['HTTP_HOST'])){
		$host='https://danwin1210.me';
	}else{
		$host='http://danwin1210.i2p';
	}
}else{
	$host='http://tt3j2x4k5ycaa5zt.onion';
}
?>
<!DOCTYPE html><html><head>
<title>Daniel's Hosting</title>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<meta name="author" content="Daniel Winzen">
<meta name="viewport" content="width=device-width, initial-scale=1">
</head><body>
<h1>Hosting - Info</h1>
<p>Info | <a href="register.php">Register</a> | <a href="login.php">Login</a> | <a href="list.php">List of hosted sites</a> | <a href="faq.php">FAQ</a></p>
<p><b>The waiting has an end - Happy new Year and Hosting!</b></p>
<p>This is a completely fresh installation with many changes done to the internals of how the hosting works. Not everything is working 100% yet, please be patient. To those coming here for the first time since 15th November and are wondering what happened to their account, see <a href="https://www.zdnet.com/article/popular-dark-web-hosting-provider-got-hacked-6500-sites-down/" target="_blank">here</a>.</p>
<p>Here you can get yourself a hosting account on my server.</p>
<p>What you will get:</p>
<ul>
<li>Completely free anonymous webhosting</li>
<li>Choose between PHP <?php echo implode(', ', PHP_VERSIONS); ?> or no PHP support</li>
<li>Nginx Webserver</li>
<li>SQLite support</li>
<li>MariaDB (MySQL) database support</li>
<li><a href="/phpmyadmin/" target="_blank">PHPMyAdmin</a> and <a href="/adminer/" target="_blank">Adminer</a> for web based database administration</li>
<li>Web-based file manager</li>
<li>FTP access</li>
<li>SFTP access</li>
<li>No disk quota, but please be fair about your disk usage - a quota will come soon</li>
<li>mail() can send e-mails from your.onion@<?php echo ADDRESS; ?> (your.onion@hosting.danwin1210.me for clearnet) - not yet working but will return in future, use <a href="https://github.com/PHPMailer/PHPMailer" target="_blank">https://github.com/PHPMailer/PHPMailer</a> or similar for now</li>
<li>Webmail and IMAP, POP3 and SMTP access to your mail account</li>
<li>Mail sent to anything@your.onion gets automatically redirected to your inbox</li>
<li>Your own .onion domain</li>
<li>On request your own clearnet domain or a free subdomain of danwin1210.me. I can setup an I2P domain as well.</li>
<li>There is a missing feature or you need a special configuration? Just <a href="<?php echo "$host/contact.php"; ?>">contact me</a> and I'll see what I can do.</li>
<li>Empty/Unused accounts will be automatically deleted after a month of inactivity</li>
<li>More to come…</li>
</ul>
<h2>Rules</h2>
<ul>
<li>No child pornography!</li>
<li>No terroristic propaganda!</li>
<li>No illegal content according to German law!</li>
<li>No malware! (e.g. botnets)</li>
<li>No phishing, scams or spam!</li>
<li>No mining without explicit user permission! (e.g. using coinhive)</li>
<li>No shops, markets or any other sites dedicated to making money! (This is a FREE hosting!)</li>
<li>No proxy scripts! (You are already using TOR and this will just burden the network)</li>
<li>No IP logger or similar de-anonymizer sites!</li>
<li>I preserve the right to delete any site for violating these rules and adding new rules at any time.</li>
<li>Should you not honor these rules, I will (have to) work together with Law Enfocements!</li>
</ul>
</body></html>
