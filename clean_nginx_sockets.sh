#!/bin/sh
# Run this script whenever nginx doesn't start up due to stale sockets
rm -f /home/*/var/run/mysqld/mysqld.sock /home/*/var/run/mail.sock /run/nginx.sock /run/nginx/* /var/www/var/run/mysqld/mysqld.sock /var/www/var/run/mail.sock /var/spool/postfix/var/run/mysqld/mysqld.sock
