General Information:
--------------------

This is a setup for a Tor based shared hosting server. It is provided as is and before putting it into production you should make changes according to your needs. This is a work in progress and you should carefully check the commit history for changes before updating.

Installation Instructions:
--------------------------

The configuration was tested with a standard Debian bullseye and Ubuntu 20.04 LTS installation. It's recommended you install Debian bullseye (or newer) on your server, but with a little tweaking you may also get this working on other distributions and/or versions. If you want to build it on a raspberry pi, please do not use the raspbian images as several things will break. Download an image for your pi model from [https://raspi.debian.net/daily-images/](https://raspi.debian.net/daily-images/) instead.

Uninstall packages that may interfere with this setup:
```
DEBIAN_FRONTEND=noninteractive apt-get purge -y apache2* dnsmasq* eatmydata exim4* imagemagick-6-common mysql-client* mysql-server* nginx* libnginx-mod* php7* resolvconf && systemctl disable systemd-resolved.service && systemctl stop systemd-resolved.service
```

If you have problems resolving hostnames after this step, temporarily switch to a public nameserver like 1.1.1.1 (from CloudFlare) or 8.8.8.8 (from Google)

```
rm /etc/resolv.conf && echo "nameserver 1.1.1.1" > /etc/resolv.conf
```

Install git and clone this repository

```
apt-get update && apt-get install git && git clone https://github.com/DanWin/hosting && cd hosting
```

Install custom optimized binaries
```
./install_binaries.sh
```

To get the latest mariadb version, you should follow these instructions to add the official repository for your distribution: (https://downloads.mariadb.org/mariadb/repositories/)

Add torproject to our repositories:
```
curl --socks5-hostname 127.0.0.1:9050 -sSL http://apow7mjfryruh65chtdydfmqfpj5btws7nbocgtaovhvezgccyjazpqd.onion/torproject.org/A3C4F0F979CAA22CDBA8F512EE8CBC9E886DDD89.asc > /etc/apt/trusted.gpg.d/torproject.gpg
echo "deb tor://apow7mjfryruh65chtdydfmqfpj5btws7nbocgtaovhvezgccyjazpqd.onion/torproject.org/ `lsb_release -cs` main" >> /etc/apt/sources.list
apt-get update && apt-get upgrade
```

Note that debian also has an onion service package archive, so you may want to edit /etc/apt/sources.list to load from there instead:
```
deb tor://2s4yqjx5ul6okpp3f2gaunr2syex5jgbfpfvhxxbbjwnrsvbk5v3qbid.onion/debian `lsb_release -cs` main
```

Copy (and modify according to your needs) the site files in `var/www` to `/var/www`, `usr/local` to `/usr/local`  and the configuration files in `etc` to `/etc` after installation has finished. Then restart some services:
```
systemctl daemon-reload && systemctl restart bind9.service && systemctl restart tor@default.service
```

Replace the default .onion domain with your domain:
```
sed -i "s/dhosting4xxoydyaivckq7tsmtgi4wfs3flpeyitekkmqwu4v4r46syd.onion/`cat /var/lib/tor/hidden_service/hostname`/g" /etc/postfix/sql/alias.cf /etc/postfix/sender_login_maps /etc/postfix/main.cf /var/www/skel/www/index.hosting.html /var/www/common.php /etc/postfix/canonical /etc/postfix-clearnet/canonical
```

For your clearnet domain, you need to add it to `relay_domains` in `/etc/postfix/main.cf` and edit the default domain in the following files:
```
/var/www/common.php
/etc/postfix/canonical
/etc/postfix-clearnet/canonical
```

This setup has two postfix instances, one for receiving and sending mail to other .onion services and one for rewriting addresses to pass them on to a clearnet facing mail relay. You may or may not want to create the second instance by running
```
postmulti -e init
postmulti -I postfix-clearnet -e create
postmulti -i clearnet -e enable
postmulti -i clearnet -p start
```
If you created an instance, uncomment the clearnet relay related config in etc/postfix/main.cf and make sure to copy and modify the configuration files from etc/postfix-clearnet too

If you encountered the following issue: `postfix: fatal: chdir(/var/spool/postfix-clearnet): No such file or directory` you can just copy the chroot from the default postfix instance like this `cd /var/spool/ && cp -a postfix/ postfix-clearnet/`

After copying (and modifying) the posfix configuration, you need to create databases out of the mapping files (also each time you update those files):
```
postalias /etc/aliases
postmap /etc/postfix/canonical /etc/postfix/sender_login_maps /etc/postfix/transport
postmap /etc/postfix-clearnet/canonical /etc/postfix-clearnet/sasl_password /etc/postfix-clearnet/transport #only if you have a second instance
```

To save temporary files in memory, add the following to `/etc/fstab`:
```
tmpfs /tmp tmpfs defaults,noatime 0 0
tmpfs /var/log/nginx tmpfs rw,user,noatime 0 0
```

To harden the system and hide pids from non-root users, also add the following:
```
proc /proc proc defaults,hidepid=2 0 0
```

And add the `noatime,usrjquota=aquota.user,jqfmt=vfsv1` options to the `/home` mountpoint (if not a separate partition, the `/` mointpoint and `noatime`to `/`. Then initialize quota (replace `/home` with `/`, if you do not have a separate partition):
```
mount -o remount /home
quotacheck -cMu /home
quotaon /home
```

In some cases, you might get an error, that quota is not supported. This is usually the case in virtual environments. Make sure you have the full kernel installed, not one with a `-virtual` package. They usually are `linux-image-amd64`, `linux-image-arm64` or `linux-image-generic`, depending on your distribution. Also make sure, you are running a real virtual machine (e.g. KVM). Some providers sell containerized VPSes (e.g. OpenVZ), which means you don't run your own kernel...

Enable the PHP-FPM default instances and nginx:
```
systemctl enable php7.4-fpm@default
systemctl enable php8.0-fpm@default
systemctl enable nginx
```

Install sodium_compat for v3 hidden_service support
```
cd /var/www && composer install
```

Create a mysql user for phpmyadmin and cofigure it in `/var/www/html/phpmyadmin/config.inc.php` and fill `$cfg['blowfish_secret']` with random characters:
```
mysql
CREATE USER 'phpmyadmin'@'%' IDENTIFIED BY 'MY_PASSWORD';
CREATE DATABASE phpmyadmin;
GRANT ALL PRIVILEGES ON phpmyadmin.* TO 'phpmyadmin'@'%';
FLUSH PRIVILEGES;
quit
mysql phpmyadmin < /var/www/html/phpmyadmin/sql/create_tables.sql
```

For web based mail management configure squirrelmail:
```
cd /var/www/html/squirrelmail && ./configure
```

Configuration options to change are:
```
D. > select dovecot
2. Server Settings > 1. Domain > Set your own .onion domain here
2. Server Settings > B. Update SMTP settings > 7. SMTP Authentication -> y -> plain -> n User are authenticated using their username + password
4. General Options > 1. Data Directory > /data/squirrelmail/data/
4. General Options > 2. Attachment Directory > /data/squirrelmail/attach/
4. General Options > 9. Allow editing of identity > n Users should not be able to fake email addresses > y They should be able to change display name > y They should be able to set a reply to mail > y additional headers are not required
10. Language settings > 4. Enable aggressive decoding
11. Tweaks > 2. Ask user info on first login > n (commonly confuses users)
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

Last but not least setup the database by running
```
php /var/www/setup.php
``` 

Enable systemd timers to regularly run various managing tasks:
```
systemctl enable hosting-del.timer && systemctl enable hosting.timer
```

Final step is to reboot wait about 5 minutes for all services to start and check if everything is working by creating a test account.
