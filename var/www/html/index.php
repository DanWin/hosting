<?php
require('../common.php');
header('Content-Type: text/html; charset=UTF-8');
print_header(_('Info'));
?>
<h1><?php echo _('Hosting - Info'); ?></h1>
<?php main_menu('index.php'); ?>
<p><?php echo _('Here you can get yourself a free web hosting account on my server.'); ?></p>
<h2><?php echo _('What you get:'); ?></h2>
<ul>
<li><?php echo _('Completely free anonymous Tor and clearnet web hosting'); ?></li>
<li><?php printf(_('Choose between PHP %s or no PHP support'), implode( ', ', PHP_VERSIONS )); ?></li>
<li><?php echo _('Nginx Webserver'); ?></li>
<li><?php echo _('SQLite support'); ?></li>
<li><?php printf(_('Up to %d MariaDB (MySQL) databases'), MAX_NUM_USER_DBS); ?></li>
<li><?php echo _('<a href="/phpmyadmin/" target="_blank">PHPMyAdmin</a> and <a href="/adminer/" target="_blank">Adminer</a> for web based database administration'); ?></li>
<li><?php echo _('Web-based file manager'); ?></li>
<li><?php echo _('SFTP access'); ?></li>
<li><?php echo _('command line access to shell via SSH'); ?></li>
<li><?php echo _('1GB disk quota and a maximum of 100.000 files'); echo ENABLE_UPGRADES ? _(' - upgradable') : ''; ?></li>
<li><?php printf(_('mail() can send e-mails from your_system_account@%1$s (your_system_account@%2$s for clearnet)'), ADDRESS, CLEARNET_ADDRESS); ?></li>
<li><?php echo _('Webmail and IMAP, POP3 and SMTP access to your mail account'); ?></li>
<li><?php echo _('Your own .onion domains'); ?></li>
<li><?php printf(_('Clearnet domains or a free subdomain of %s'), CLEARNET_SUBDOMAINS); ?></li>
<li><?php echo _('Empty/Unused accounts will be automatically deleted after a month of inactivity'); ?></li>
<li><?php echo _('PGP based Two-Factor Authentication (2FA)'); ?></li>
<li><?php printf(_('There is a missing feature, or you need a special configuration? Just <a href="%s">contact me</a> and I\'ll see what I can do.'), CONTACT_URL); ?></li>
<li><?php echo _('More to come…'); ?></li>
</ul>
<?php if (defined('COPYRIGHT') && COPYRIGHT !== ''): ?>
    <h2><?php echo _('Copyright'); ?></h2>
    <ul>
        <li><?php echo _(COPYRIGHT); ?></li>
    </ul>
<?php endif; ?>

<?php if (defined('DISCLAIMER') && DISCLAIMER !== ''): ?>
    <h2><?php echo _('Disclaimer'); ?></h2>
    <ul>
        <li><?php echo _(DISCLAIMER); ?></li>
    </ul>
<?php endif; ?>
<h2><?php echo _('Rules'); ?></h2>
<ul>
<li><?php echo _('No child pornography!'); ?></li>
<li><?php echo _('No terroristic propaganda!'); ?></li>
<li><?php echo _('No illegal content according to German law!'); ?></li>
<li><?php echo _('No malware! (e.g. botnets)'); ?></li>
<li><?php echo _('No phishing, scams or spam!'); ?></li>
<li><?php echo _('No mining without explicit user permission! (e.g. using coinhive)'); ?></li>
<li><?php echo _('No shops, markets or any other sites dedicated to making money! (This is a FREE hosting!)'); ?></li>
<li><?php echo _('No proxy scripts! (You are already using Tor and this will just burden the network)'); ?></li>
<li><?php echo _('No IP logger or similar de-anonymizer sites!'); ?></li>
<li><?php echo _('I preserve the right to delete any site for violating these rules and adding new rules at any time.'); ?></li>
<li><?php echo _('Should you not honor these rules, I will (have to) work together with Law Enforcement!'); ?></li>
</ul>
</body></html>
