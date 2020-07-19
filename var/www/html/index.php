<?php
require('../common.php');
header('Content-Type: text/html; charset=UTF-8');
header('X-Accel-Expires: 60');
print_header('Info');
?>
<h1>Hosting - Info</h1>
<?php main_menu('index.php'); ?>
<p>Here you can get yourself a free web hosting account on my server.</p>
<h2>What you get:</h2>
<ul>
<li>Completely free anonymous Tor and clearnet web hosting</li>
<li>Choose between PHP <?php echo implode(', ', PHP_VERSIONS); ?> or no PHP support</li>
<li>Nginx Webserver</li>
<li>SQLite support</li>
<li>Up to <?php echo MAX_NUM_USER_DBS; ?> MariaDB (MySQL) databases</li>
<li><a href="/phpmyadmin/" target="_blank">PHPMyAdmin</a> and <a href="/adminer/" target="_blank">Adminer</a> for web based database administration</li>
<li>Web-based file manager</li>
<li>SFTP access</li>
<li>command line access to shell via SSH</li>
<li>1GB disk quota and a maximum of 100.000 files<?php echo ENABLE_UPGRADES ? ' - upgradable' : ''; ?></li>
<li>mail() can send e-mails from your_system_account@<?php echo ADDRESS; ?> (your_system_account@hosting.danwin1210.me for clearnet)</li>
<li>Webmail and IMAP, POP3 and SMTP access to your mail account</li>
<li>Your own .onion domains</li>
<li>Clearnet domains or a free subdomain of danwin1210.me</li>
<li>Empty/Unused accounts will be automatically deleted after a month of inactivity</li>
<li>PGP based Two Factor Authentication (2FA)</li>
<li>There is a missing feature or you need a special configuration? Just <a href="https://danwin1210.me/contact.php">contact me</a> and I'll see what I can do.</li>
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
<li>Should you not honor these rules, I will (have to) work together with Law Enforcement!</li>
</ul>
</body></html>
