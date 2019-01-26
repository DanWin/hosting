General Information:
--------------------

This is a setup for a TOR based shared hosting server. It is provided as is and before putting it into production you should make changes according to your needs. This is a work in progress and you should carefully check the commit history for changes before updating.

Installation Instructions:
--------------------------

The configuration was tested with a standard Debian sid and Ubuntu 16.04 LTS installation. It's recommended you install Debian sid on your server, but with a little tweaking you may also get this working on other distributions and/or versions.

Uninstall packages that may interfere with this setup:
```
apt-get purge apache2* resolvconf exim4* && systemctl disable systemd-resolved.service
```

If you are on Ubuntu, add the following PPA:
```
LC_ALL=C.UTF-8 add-apt-repository ppa:ondrej/php
```
On debian this may be worth a look: https://deb.sury.org/

To get the latest tor version, you should follow these instructions to add the official tor repository for your distribution: (https://www.torproject.org/docs/debian)

To get the latest mariadb version, you should follow these instructions to add the official tor repository for your distribution: (https://downloads.mariadb.org/mariadb/repositories/)

Add yarn + nodejs to our repositories:
```
apt-key adv --recv 1655A0AB68576280
curl -sS https://dl.yarnpkg.com/debian/pubkey.gpg | apt-key add -
echo "deb https://dl.yarnpkg.com/debian/ stable main" >> /etc/apt/sources.list
echo "deb https://deb.nodesource.com/node_11.x sid main" >> /etc/apt/sources.list
```

The following command will install all required packages:
```
apt-get --no-install-recommends install apt-transport-tor aspell clamav-daemon clamav-freshclam clamav-milter composer curl dovecot-imapd dovecot-pop3d git dnsmasq haveged hunspell iptables locales-all logrotate mariadb-server nano nginx-full postfix postfix-mysql \
php7.3-bcmath php7.3-bz2 php7.3-cli php7.3-curl php7.3-dba php7.3-enchant php7.3-fpm php7.3-gd php7.3-gmp php7.3-imap php7.3-intl php7.3-json php7.3-mbstring php7.3-mysql php7.3-opcache php7.3-pspell php7.3-readline php7.3-recode php7.3-soap php7.3-sqlite3 php7.3-tidy php7.3-xml php7.3-xmlrpc php7.3-xsl php7.3-zip \
php-apcu php-gnupg php-imagick quota quotatool rsync sasl2-bin ssh subversion tor unzip vim vsftpd wget yarn zip && apt-get --no-install-recommends install adminer
```

Note that both, debian and the torproject have hidden service package archives, so you may want to edit /etc/apt/sources.list to load from those instead:
```
deb tor+http://vwakviie2ienjx6t.onion/debian sid main
deb tor+http://sdscoq7snqtznauu.onion/torproject.org sid main
```

Copy (and modify according to your needs) the site files in `var/www` to `/var/www` and the configuration files in `etc` to `/etc` after installation has finished. Then restart some services:
```
systemctl daemon-reload && service tor restart && service dnsmasq restart
```

Now there should be an onion domain in `/var/lib/tor/hidden_service/hostname`:
```
cat /var/lib/tor/hidden_service/hostname
```

Replace the default domain with your domain in the following files:
```
/etc/postfix/sql/alias.cf
/etc/postfix/sender_login_maps
/etc/postfix/main.cf
/var/www/skel/www/index.hosting.html
/var/www/common.php
/etc/postfix/canonical
/etc/postfix-clearnet/canonical
```

In `/etc/postfix(-clearnet)/canonical` don't change the line that has `hosting.danwin1210.me` in it. It is a clearnet/tor address rewriting rule, and if you have your own clearnet domain, you should copy this and modify your copy to preserve sending mail to my host via tor and not via clearnet:

To allow sasl authentication add the `postfix` user to the `sasl` group:
```
usermod -aG sasl postfix
```

This setup has two postfix instances, one for receiving and sending mail to other .onion services and one for rewriting addresses to pass them on to a clearnet facing mail relay. You may or may not want to create the second instance by running
```
postmulti -e init
postmulti -I postfix-clearnet -e create
postmulti -i clearnet -e enable
postmulti -i clearnet -p start
```
If you created an instance, uncomment the clearnet relay related config in etc/postfix/main.cf and make sure to copy and modify the configuration files from etc/postfix-clearnet too

After copying (and modifying) the posfix configuration, you need to create databases out of the mapping files (also each time you update those files):
```
postalias /etc/aliases
postmap /etc/postfix/canonical /etc/postfix/sender_login_maps /etc/postfix/transport
postmap /etc/postfix-clearnet/canonical /etc/postfix-clearnet/sasl_password /etc/postfix-clearnet/transport #only if you have a second instance
```

To save temporary files in memory, add the following to `/etc/fstab`:
```
tmpfs /tmp tmpfs defaults 0 0
tmpfs /var/log/nginx tmpfs rw,user 0 0
```

As time syncronisation is important, you should configure ntp servers in `/etc/systemd/timesyncd.conf` and make them match with the entries in `/etc/rc.local` iptables configuration

To create all required tor and php instances run the following commands:
```
for instance in 2 3 4 5 6 7 a b c d e f g h i j k l m n o p q r s t u v w x y z; do(tor-instance-create $instance) done
for instance in default 2 3 4 5 6 7 a b c d e f g h i j k l m n o p q r s t u v w x y z; do(systemctl enable php7.3-fpm@$instance;) done
```

Edit `/etc/fstab` and add the `usrjquota=aquota.user,jqfmt=vfsv1` option to the /home mountpoint. Then initialize quota:
```
mount -o remount /home
quotacheck -cu /home
quotaon /home
```
For web base database administration, check out the latest phpmyadmin:
```
cd /var/www/html/ && git clone -b STABLE https://github.com/phpmyadmin/phpmyadmin/ && cd phpmyadmin && composer install --no-dev && yarn
```

Once installed create a mysql user for phpmyadmin and cofigure it in `/var/www/html/phpmyadmin/config.inc.php` and fill `$cfg['blowfish_secret']` with random characters:
```
mysql
CREATE USER 'phpmyadmin'@'%' IDENTIFIED BY 'MY_PASSWORD';
GRANT ALL PRIVILEGES ON phpmyadmin.* TO 'phpmyadmin'@'%';
FLUSH PRIVILEGES;
quit
```

For web based mail management grab the latest squirrelmail and install it in `/var/www/html/squirrelmail`:
```
cd /var/www/html/ && svn checkout https://svn.code.sf.net/p/squirrelmail/code/trunk/squirrelmail && cd squirrelmail && ./configure && mkdir /var/local/squirrelmail /var/local/squirrelmail/data /var/local/squirrelmail/attach && chown www-data:www-data /var/local/squirrelmail /var/local/squirrelmail/data /var/local/squirrelmail/attach
```

Once it is downloaded, it will ask you for configuration. Things to change are:
```
D. > select dovecot
2. Server Settings > 1. Domain > Set your own .onion domain here
2. Server Settings > B. Update SMTP settings > 7. SMTP Authentication -> y -> plain -> n User are authenticated using their username + password
4. General Options > 9. Allow editing of identity > n Users should not be able to fake email addresses > y They should be able to change display name > y They should be able to set a reply to mail > y additional headers are not required
10. Language settings > 4. Enable aggressive decoding
11. Tweaks > 2. Ask user info on first login > n (commonly confuses users)
11. Tweaks > 4. Use php recode functions > y
11. Tweaks > 5. Use php iconv functions > y
```

Create a mysql user with all permissions for our hosting management:
```
mysql
CREATE USER 'hosting'@'%' IDENTIFIED BY 'MY_PASSWORD';
GRANT ALL PRIVILEGES ON *.* TO 'hosting'@'%' WITH GRANT OPTION;
FLUSH PRIVILEGES;
quit
```

Then edit the database configuration in `/var/www/common.php` and `/etc/postfix/sql/alias.cf`

Install sodium_compat for v3 hidden_service support
```
cd /var/www && composer install
```

Last but not least setup the database by running
```
php /var/www/setup.php
``` 

Enable systemd timers to regularly run various managing tasks:
```
systemctl enable hosting-del.timer && systemctl enable hosting.timer
```

Final step is to reboot wait about 5 minutes for all services to start and check if everything is working by creating a test account.

Live demo:
----------

If you want to see the setup in action or create your own site on my server, you can visit my [TOR hidden service](http://dhosting4xxoydyaivckq7tsmtgi4wfs3flpeyitekkmqwu4v4r46syd.onion) or via [my clearnet proxy](https://hosting.danwin1210.me) if you don't have TOR installed.
