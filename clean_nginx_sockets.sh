#!/bin/sh
# Run this script whenever nginx doesn't start up due to stale sockets
rm -f /home/*/run/mysqld/mysqld.sock /home/*/run/mail.sock /run/nginx.sock /run/nginx/* /var/www/run/mysqld/mysqld.sock /var/www/run/mail.sock /var/spool/postfix/var/run/mysqld/mysqld.sock
