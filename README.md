General Information:
--------------------

This is a setup for a TOR based shared hosting server. It is provided as is and before putting it into production you should make changes according to your needs

Installation Instructions:
--------------------------

The configuration was designed for a standard Debian unstable installation. It's recommended you install Debian unstable on your sever, but with a little tweaking you may also get this working on other distributions and/or versions.
The following command will install all required packages:
```
apt-get --no-install-recommends install apt-transport-tor aspell curl dovecot-imapd dovecot-pop3d git haveged hunspell iptables locales-all logrotate mariadb-server nginx-light postfix postfix-mysql php7.0-bcmath php7.0-bz2 php7.0-curl php7.0-dba php7.0-enchant php7.0-fpm php7.0-gd php7.0-gmp php7.0-imap php7.0-json php7.0-mbstring php7.0-mcrypt php7.0-mysql php7.0-opcache php7.0-pspell php7.0-readline php7.0-recode php7.0-soap php7.0-sqlite3 php7.0-tidy php7.0-xml php7.0-xmlrpc php7.0-xsl php7.0-zip php7.1-bcmath php7.1-bz2 php7.1-cli php7.1-curl php7.1-dba php7.1-enchant php7.1-fpm php7.1-gd php7.1-gmp php7.1-imap php7.1-intl php7.1-json php7.1-mbstring php7.1-mcrypt php7.1-mysql php7.1-opcache php7.1-pspell php7.1-pspell php7.1-readline php7.1-recode php7.1-soap php7.1-sqlite3 php7.1-tidy php7.1-xml php7.1-xmlrpc php7.1-xsl php7.1-zip phpmyadmin php-imagick sasl2-bin ssh subversion tor vsftpd && apt-get --no-install-recommends install adminer
```

For optimum spell checking capabilities you can optionally install the following packages:
```
apt-get install aspell-am aspell-ar aspell-ar-large aspell-bg aspell-bn aspell-br aspell-ca aspell-cs aspell-cy aspell-da aspell-de aspell-de-1901 aspell-de-alt aspell-doc aspell-el aspell-en aspell-eo aspell-eo-cx7 aspell-es aspell-et aspell-eu aspell-eu-es aspell-fa aspell-fo aspell-fr aspell-ga aspell-gl-minimos aspell-gu aspell-he aspell-hi aspell-hr aspell-hsb aspell-hu aspell-hy aspell-is aspell-it aspell-kk aspell-kn aspell-ku aspell-lt aspell-lv aspell-ml aspell-mr aspell-nl aspell-no aspell-or aspell-pa aspell-pl aspell-pt aspell-pt-br aspell-pt-pt aspell-ro aspell-ru aspell-sk aspell-sl aspell-sv aspell-ta aspell-te aspell-tl aspell-uk aspell-uz hunspell-af hunspell-an hunspell-ar hunspell-be hunspell-bg hunspell-bn hunspell-bo hunspell-br hunspell-bs hunspell-ca hunspell-cs hunspell-da hunspell-de-at hunspell-de-ch hunspell-de-de hunspell-el hunspell-en-au hunspell-en-ca hunspell-en-gb hunspell-en-med hunspell-en-us hunspell-en-za hunspell-es hunspell-eu hunspell-eu-es hunspell-fr hunspell-fr-comprehensive hunspell-gd hunspell-gl hunspell-gu hunspell-he hunspell-hi hunspell-hr hunspell-hu hunspell-is hunspell-it hunspell-kk hunspell-kmr hunspell-ko hunspell-lo hunspell-lt hunspell-ml hunspell-ne hunspell-nl hunspell-no hunspell-oc hunspell-pl hunspell-pt-br hunspell-pt-pt hunspell-ro hunspell-ru hunspell-se hunspell-si hunspell-sk hunspell-sl hunspell-sr hunspell-sv hunspell-sw hunspell-te hunspell-th hunspell-tools hunspell-uk hunspell-uz hunspell-vi
```

Copy (and modify according to your needs) the configuration files in etc to /etc after installation has finished.

If you copied over the new etc/apt/sources.list file, we need to update our repository data and install a new keyring package for authenticating packages from torproject (you will need to confirm this):
```
apt-get update && apt-get install deb.torproject.org-keyring
```

To allow sasl authentication, add postfix to the sasl group:
```
usermod -aG sasl postfix
```

This setup has two postfix instances, one for receiving and sending mail to other .onion services and one for rewriting addresses to pass them on to a clearnet facing mail relay. You may or may not want to create the second instance by running
```
postmulti -e init
postmulti -I clearnet -e create
postmulti -I clearnet -e enable
postmulti -i clearnet -p start
```

After copying (and modifying) the posfix configuration, you need to create databases out of the mapping files (also each time you update those files):
```
postmap /etc/postfix/canonical /etc/postfix/sender_login_maps /etc/postfix/transport
```

To save temporary files in memory, add the following to /etc/fstab
```
tmpfs /tmp tmpfs defaults 0 0
tmpfs /var/log/nginx tmpfs rw,user 0 0
```

If you expect a large number of registrations (10.000 or more), you should make sure your system has enough UIDs to assign. The easiest way to do so is by limiting newusers to one ID per user by adding the following to /etc/login.defs
```
SUB_GID_COUNT 1
SUB_UID_COUNT 1
```

As time syncronisation is important, you should configure ntp servers in /etc/systemd/timesyncd.conf and make them match with the entries in /etc/rc.local iptables configuration

To create all required tor and php instances run the following command:
```
for instance in 2 3 4 5 6 7 a b c d e f g h i j k l m n o p q r s t u v w x y z; do(tor-instance-create $instance; ln -s /etc/systemd/system/php7.0-fpm@.service "/etc/systemd/system/multi-user.target.wants/php7.0-fpm@$instance.service"; ln -s /etc/systemd/system/php7.1-fpm@.service "/etc/systemd/system/multi-user.target.wants/php7.1-fpm@$instance.service";) done
```

And to get a list of all tor user ids to add in /etc/rc.local run the following:
```
for instance in 2 3 4 5 6 7 a b c d e f g h i j k l m n o p q r s t u v w x y z; do(id "_tor-$instance") done && id debian-tor
```

For web based mail management grab the latest squirrelmail and install it in /var/www/html/squirrelmail:
```
cd /var/www/html/ && svn checkout https://svn.code.sf.net/p/squirrelmail/code/trunk/squirrelmail && cd squirrelmail && ./configure
```

Create a mysql user with all permissions for our hosting management:
```
mysql
CREATE USER 'hosting'@'localhost' IDENTIFIED BY 'MY_PASSWORD';
GRANT ALL PRIVILEGES ON *.* TO 'hosting'@'localhost' WITH GRANT OPTION;
```

Then edit the database configuration in /var/www/common.php and last but not least setup the database by running
```
php /var/www/setup.php
``` 

Enable systemd timers to regularly run various managing tasks:
```
ln -s /etc/systemd/system/hosting-del.timer /etc/systemd/system/multi-user.target.wants/hosting-del.timer
ln -s /etc/systemd/system/hosting.timer /etc/systemd/system/multi-user.target.wants/hosting.timer
```

Live demo:
----------

If you want to see the setup in action or create your own site on my server, you can visit my [TOR hidden service](http://dhosting4okcs22v.onion) or via a tor2web proxy like [this one](https://danwin1210.me/hosting/) if you don't have TOR installed.
