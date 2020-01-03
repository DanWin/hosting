<?php
include('../common.php');
header('Content-Type: text/html; charset=UTF-8');
header('X-Accel-Expires: 60');
?>
<!DOCTYPE html><html><head>
<title>Daniel's Hosting</title>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<meta name="author" content="Daniel Winzen">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="canonical" href="<?php echo CANONICAL_URL . $_SERVER['SCRIPT_NAME']; ?>">
<link rel="stylesheet" href="w3.css">
</head>
<style>
body {background-color: lightblue;}
h1 {color: white;text-align: center;}
p {font-family: verdana;font-size: 2vw;}
btn {font-family: verdana;font-size: 1.2vw;}
</style>
<body>
  <div class="w3-container w3-margin-left">
    <div class="w3-container w3-margin-right">
<div class="w3-container w3-deep-purple">
  <h1>Hosting - Info</h1>
</div>
<div class="w3-bar w3-blue">
  <a href="index.php" class="w3-bar-item w3-button w3-mobile">Home</a>
  <a href="register.php" class="w3-bar-item w3-button w3-mobile">Register</a>
  <a href="login.php" class="w3-bar-item w3-button w3-mobile">Login</a>
  <a href="list.php" class="w3-bar-item w3-button w3-mobile">List of hosted sites</a>
  <a href="faq.php" class="w3-bar-item w3-button w3-mobile">FAQ</a>
  <a href="mailto:<?php echo CONTACT_ME?>" class="w3-bar-item w3-button w3-mobile">Contact Daniel's hosting service</a>
</div>
<div class="w3-panel">
<div id="news">
<?php             error_reporting(E_ALL);

                  $entry = file("../news.txt");

                  foreach($entry as $view)
                  {
                     $entry = stripslashes($view);
                     $teile = explode("|", $view);
                  }
                      if($teile[1] == "")
                      {
                       echo "Server Status: All is normal! Namaste";
                      }
                      else
                      {

                      echo "$teile[0] from $teile[2]
                            <br>$teile[1]";
                      }
                  ?>
          </div></div>
<p>Here you can get yourself a hosting account on my server.</p>
<div class="w3-card-4">
<header class="w3-container w3-green">
  <h1>What you will get:</h1>
</header>
<div class="w3-container">
<li>Completely free anonymous Tor and clearnet webhosting</li>
<li>Choose between PHP <?php echo implode(', ', PHP_VERSIONS); ?> or no PHP support</li>
<li>Nginx Webserver</li>
<li>SQLite support</li>
<li>MariaDB (MySQL) database support</li>
<li><a href="/phpmyadmin/" target="_blank">PHPMyAdmin</a> and <a href="/adminer/" target="_blank">Adminer</a> for web based database administration</li>
<li>Web-based file manager</li>
<li>FTP access</li>
<li>SFTP access</li>
<li>1GB disk quota and a maximum of 100.000 files. If you need more, just <a href="mailto:<?php echo CONTACT_ME?>">contact me</a></li>
<li>mail() can send e-mails from <btn>your.onion@<?php echo ADDRESS; ?></btn><a href="https://github.com/PHPMailer/PHPMailer" target="_blank">https://github.com/PHPMailer/PHPMailer</a> or similar for now</li>
<li>Webmail and IMAP, POP3 and SMTP access to your mail account</li>
<li>Mail sent to anything@your.onion gets automatically redirected to your inbox</li>
<li>Your own .onion domains</li>
<li>There is a missing feature or you need a special configuration? Just <a href="mailto:<?php echo CONTACT_ME?>">contact me</a> and I'll see what I can do.</li>
<li>Empty/Unused accounts will be automatically deleted after a month of inactivity</li>
<li>More to come…</li>
</div>
<footer class="w3-container w3-yellow">
  <h5></h5>
</footer>
</div>
    <br>
<div class="w3-card-4">
<header class="w3-container w3-orange ">
  <h1>Rules</h1>
</header>
<div class="w3-container">
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
</div>
<footer class="w3-container w3-red">
  <h5></h5>
</footer>
</div>
</body>
</html>
