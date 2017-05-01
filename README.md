General Information:
--------------------

This is a setup for a TOR based shared hosting server. It is provided as is and before putting it into production you should make changes according to your needs

Installation Instructions:
--------------------------

The configuration was designed for a standard Debian unstable installation. It's recommended you install Debian unstable on your sever, but with a little tweaking you may also get this working on other distributions and/or versions.
Software you will need to install and configure by copying (and editing according to your needs) the configuration files supplied:
nginx, php7.0, php7.1, all php modules you want to support, sshd, vsftpd, phpmyadmin, adminer, mysql or mariadb, logrotate, tor, postfix, dovecot, saslauthd

This setup has two postfix instances, one for receiving and sending mail to other .onion services and one for rewriting addresses to pass them on to a clearnet facing mail relay. You may or may not want to create the second instance by running
```
postmulti -e init
postmulti -I clearnet -e create
postmulti -I clearnet -e enable
postmulti -i clearnet -p start
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

Add a user to the SQL database for managing hosted sites and grant all privileges to it. Then edit the database configuration in /var/www/common.php and last but not least setup the database by running
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
