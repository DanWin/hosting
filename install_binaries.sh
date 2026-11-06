#!/bin/sh
set -e

export COMPOSER_ALLOW_SUPERUSER=1
export LANG=C.UTF-8
export PATH="/usr/local/bin:/usr/bin:/bin:/usr/local/sbin:/usr/sbin:/sbin"
# install all required packages
DEBIAN_FRONTEND=noninteractive apt-get update
DEBIAN_FRONTEND=noninteractive apt-get --no-install-recommends install -y apt-transport-tor bash-completion bind9 brotli bzip2 ca-certificates clamav-daemon clamav-freshclam curl dovecot-imapd dovecot-lmtpd dovecot-pop3d git hardlink haveged iptables jailkit libio-socket-ip-perl libnginx-mod-http-brotli-filter libnginx-mod-http-brotli-static libnginx-mod-stream libsasl2-modules locales locales-all logrotate lsb-release mariadb-server nano nginx postfix postfix-mysql quota quotatool redis rspamd rsync ssh tor unzip util-linux vim wget xz-utils zip zopfli
# build dependencies
DEBIAN_FRONTEND=noninteractive apt-get --no-install-recommends install -y autoconf automake bison g++ gcc ghostscript libpam0g-dev gnupg libaom-dev $(apt-cache search --names-only 'libargon2(-0)?-dev' | awk '{print $1;}' | head -n1) binutils-dev libbrotli-dev libbz2-dev libcurl4-openssl-dev libdjvulibre-dev libedit-dev $(apt-cache search --names-only 'libenchant(-2)?-dev' | awk '{print $1;}' | head -n1) libffi-dev $(apt-cache search --names-only libfreetype6?-dev | awk '{print $1;}' | head -n1) libfftw3-dev libfribidi-dev libgd-dev libgmp-dev libgpg-error-dev libgpgme-dev libgraphviz-dev libgs-dev libharfbuzz-dev libheif-dev libjbig-dev libjbig2dec0-dev libjxl-dev libkrb5-dev libldap2-dev liblmdb-dev liblqr-1-0-dev libmariadb-dev libonig-dev libopenexr-dev libopenjp2-7-dev libpango1.0-dev libpng-dev libpspell-dev libqdbm-dev libraqm-dev libraw-dev libreadline-dev librsvg2-dev libsasl2-dev libsodium-dev libssh2-1-dev libssl-dev libsqlite3-dev libsystemd-dev libtidy-dev libtool libwebp-dev libwmf-dev libxml2-dev libxpm-dev libxslt1-dev libzip-dev libzstd-dev make poppler-utils re2c zlib1g-dev

# build uw-imap c-client from source (removed from trixie, needed for PHP --with-imap / SquirrelMail)
if [ ! -e /usr/local/lib/libc-client.a ]; then
	cd /tmp
	curl -sSL https://deb.debian.org/debian/pool/main/u/uw-imap/uw-imap_2007f~dfsg.orig.tar.gz | tar xz
	curl -sSL https://deb.debian.org/debian/pool/main/u/uw-imap/uw-imap_2007f~dfsg-7.debian.tar.xz | tar xJ -C imap-2007f~dfsg
	cd imap-2007f~dfsg
	for p in debian/patches/*.patch; do patch -p1 < "$p" 2>/dev/null; done
	# fix OpenSSL 3 include paths
	sed -i 's|#include <ssl.h>|#include <openssl/ssl.h>|; s|#include <err.h>|#include <openssl/err.h>|; s|#include <pem.h>|#include <openssl/pem.h>|; s|#include <buffer.h>|#include <openssl/buffer.h>|; s|#include <bio.h>|#include <openssl/bio.h>|; s|#include <crypto.h>|#include <openssl/crypto.h>|; s|#include <rand.h>|#include <openssl/rand.h>|; s|#include <x509v3.h>|#include <openssl/x509v3.h>|' src/osdep/unix/ssl_unix.c
	touch ip6
	echo y | make lnp EXTRACFLAGS="-fPIC -Wno-error=implicit-function-declaration -Wno-error=implicit-int -Wno-error=incompatible-pointer-types" SSLTYPE=unix
	cp c-client/c-client.a /usr/local/lib/libc-client.a
	mkdir -p /usr/local/include/c-client
	cp c-client/*.h /usr/local/include/c-client/
	ln -sf /usr/local/include/c-client /usr/include/c-client
	cd "$OLDPWD"
	rm -rf /tmp/imap-2007f~dfsg
fi

# install nvm
curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.41.0/install.sh | bash
export NVM_DIR="$HOME/.nvm"
[ -s "$NVM_DIR/nvm.sh" ] && \. "$NVM_DIR/nvm.sh"  # This loads nvm

# install nodejs
nvm install node --latest-npm --default
for old_version in $(nvm ls --no-alias --no-colors | grep -v '\->' | awk '{print $1;}'); do nvm uninstall "$old_version"; done
nvm cache clear

#install yarn
npm i -g yarn

# initial repository clones
if [ ! -e ImageMagick ]; then
	git clone https://github.com/ImageMagick/ImageMagick
fi
if [ ! -e php-src ]; then
	git clone https://github.com/php/php-src
fi
cd php-src/ext
if [ ! -e apcu ]; then
	git clone https://github.com/krakjoe/apcu
fi
if [ ! -e php-ext-brotli ]; then
	git clone https://github.com/kjdev/php-ext-brotli
fi
if [ ! -e imagick ]; then
	git clone https://github.com/Imagick/imagick
fi
if [ ! -e php-gnupg ]; then
	git clone https://github.com/php-gnupg/php-gnupg
fi
if [ ! -e php-rar ]; then
	git clone https://github.com/cataphract/php-rar
fi
if [ ! -e igbinary ]; then
	git clone https://github.com/igbinary/igbinary
fi
if [ ! -e msgpack-php ]; then
	git clone https://github.com/msgpack/msgpack-php
fi
cd ../..

export PROC_LIMIT=$(free -g | grep Mem | awk -v nproc=$(nproc) '{print (($2 + 1) < nproc) ? ($2 + 1) : nproc;}')
#start build
cd ImageMagick
git fetch --all
git checkout 7.1.2-35
CXXFLAGS='-O3 -mtune=native -march=native' CFLAGS='-O3 -mtune=native -march=native' ./configure --without-perl --without-magick-plus-plus --disable-openmp --with-fftw --with-gslib --with-gvc --with-rsvg --with-wmf
make -j $PROC_LIMIT install
make distclean
ldconfig
cd ..
ln -fs /usr/include/qdbm/depot.h /usr/include/depot.h
cd php-src
cd ext
cd apcu && git fetch --all && git checkout v5.1.28 && cd ..
cd php-ext-brotli && git fetch --all && git checkout 0.21.1 && cd ..
cd imagick && git fetch --all && git checkout 3.8.1 && cd ..
cd php-gnupg && git fetch --all && git checkout master && cd ..
cd php-rar && git fetch --all && git reset --hard && git checkout v4.3.1
cd igbinary && git fetch --all && git checkout master && cd ..
cd msgpack-php && git fetch --all && git checkout v0.0.1 && cd ..
rm -rf ssh2-*
curl -sSf https://pecl.php.net/get/ssh2 | tar xzvf - --exclude package.xml
cd ..
git fetch --all
git fetch --all --tags
git checkout php-8.5.12
./buildconf -f
LIBS='-lgpg-error -lgomp' CXXFLAGS='-O3 -mtune=native -march=native -std=c++17' CFLAGS='-O3 -mtune=native -march=native' ./configure -C --enable-re2c-cgoto --prefix=/usr --with-config-file-scan-dir=/etc/php/8.5/fpm/conf.d --libdir=/usr/lib/php --libexecdir=/usr/lib/php --datadir=/usr/share/php/8.5 --program-suffix=8.5 --sysconfdir=/etc --localstatedir=/var --mandir=/usr/share/man --enable-fpm --enable-cli --disable-cgi --disable-phpdbg --with-fpm-systemd --with-fpm-user=www-data --with-fpm-group=www-data --with-layout=GNU --disable-dtrace --disable-short-tags --without-valgrind --disable-shared --disable-debug --disable-rpath --without-pear --with-openssl --enable-bcmath --with-bz2 --enable-calendar --with-curl --enable-dba --with-qdbm --with-lmdb --enable-exif --enable-ftp --enable-gd --with-external-gd --with-jpeg --with-webp --with-xpm --with-freetype --enable-gd-jis-conv --with-gettext --with-gmp --with-mhash --with-imap --with-imap-ssl --with-kerberos --enable-intl --with-ldap --with-ldap-sasl --enable-mbstring --with-mysqli --with-pdo-mysql --enable-mysqlnd --with-mysql-sock=/run/mysqld/mysqld.sock --with-zlib --with-libedit --with-readline --enable-shmop --enable-soap --enable-sockets --with-sodium --with-password-argon2 --with-tidy --with-xsl --with-enchant --with-pspell --with-zip --with-ffi --enable-apcu --enable-brotli --with-libbrotli --with-imagick --with-ssh2 --with-gnupg --enable-igbinary --with-msgpack --enable-sysvsem --enable-sysvmsg --enable-sysvshm
make -j $PROC_LIMIT install
make distclean
git reset --hard
git checkout php-8.4.27
./buildconf -f
LIBS='-lgpg-error -lgomp' CXXFLAGS='-O3 -mtune=native -march=native -std=c++17' CFLAGS='-O3 -mtune=native -march=native' ./configure -C --enable-re2c-cgoto --prefix=/usr --with-config-file-scan-dir=/etc/php/8.4/fpm/conf.d --libdir=/usr/lib/php --libexecdir=/usr/lib/php --datadir=/usr/share/php/8.4 --program-suffix=8.4 --sysconfdir=/etc --localstatedir=/var --mandir=/usr/share/man --enable-fpm --enable-cli --disable-cgi --disable-phpdbg --with-fpm-systemd --with-fpm-user=www-data --with-fpm-group=www-data --with-layout=GNU --disable-dtrace --disable-short-tags --without-valgrind --disable-shared --disable-debug --disable-rpath --without-pear --with-openssl --enable-bcmath --with-bz2 --enable-calendar --with-curl --enable-dba --with-qdbm --with-lmdb --enable-exif --enable-ftp --enable-gd --with-external-gd --with-jpeg --with-webp --with-xpm --with-freetype --enable-gd-jis-conv --with-gettext --with-gmp --with-mhash --with-imap --with-imap-ssl --with-kerberos --enable-intl --with-ldap --with-ldap-sasl --enable-mbstring --with-mysqli --with-pdo-mysql --enable-mysqlnd --with-mysql-sock=/run/mysqld/mysqld.sock --with-zlib --with-libedit --with-readline --enable-shmop --enable-soap --enable-sockets --with-sodium --with-password-argon2 --with-tidy --with-xsl --with-enchant --with-pspell --with-zip --with-ffi --enable-apcu --enable-brotli --with-libbrotli --with-imagick --with-ssh2 --with-gnupg --enable-igbinary --with-msgpack --enable-sysvsem --enable-sysvmsg --enable-sysvshm
make -j $PROC_LIMIT install
make distclean
git reset --hard
git checkout php-8.3.36
./buildconf -f
LIBS='-lgpg-error -lgomp' CXXFLAGS='-O3 -mtune=native -march=native -std=c++17' CFLAGS='-O3 -mtune=native -march=native' ./configure -C --enable-re2c-cgoto --prefix=/usr --with-config-file-scan-dir=/etc/php/8.3/fpm/conf.d --libdir=/usr/lib/php --libexecdir=/usr/lib/php --datadir=/usr/share/php/8.3 --program-suffix=8.3 --sysconfdir=/etc --localstatedir=/var --mandir=/usr/share/man --enable-fpm --enable-cli --disable-cgi --disable-phpdbg --with-fpm-systemd --with-fpm-user=www-data --with-fpm-group=www-data --with-layout=GNU --disable-dtrace --disable-short-tags --without-valgrind --disable-shared --disable-debug --disable-rpath --without-pear --with-openssl --enable-bcmath --with-bz2 --enable-calendar --with-curl --enable-dba --with-qdbm --with-lmdb --enable-exif --enable-ftp --enable-gd --with-external-gd --with-jpeg --with-webp --with-xpm --with-freetype --enable-gd-jis-conv --with-gettext --with-gmp --with-mhash --with-imap --with-imap-ssl --with-kerberos --enable-intl --with-ldap --with-ldap-sasl --enable-mbstring --with-mysqli --with-pdo-mysql --enable-mysqlnd --with-mysql-sock=/run/mysqld/mysqld.sock --with-zlib --with-libedit --with-readline --enable-shmop --enable-soap --enable-sockets --with-sodium --with-password-argon2 --with-tidy --with-xsl --with-enchant --with-pspell --with-zip --with-ffi --enable-apcu --enable-brotli --with-libbrotli --with-imagick --with-ssh2 --with-gnupg --enable-igbinary --with-msgpack --enable-sysvsem --enable-sysvmsg --enable-sysvshm
make -j $PROC_LIMIT install
make distclean
git reset --hard
git checkout php-8.2.34
./buildconf -f
LIBS='-lgpg-error -lgomp' CXXFLAGS='-O3 -mtune=native -march=native -std=c++17' CFLAGS='-O3 -mtune=native -march=native' ./configure -C --enable-re2c-cgoto --prefix=/usr --with-config-file-scan-dir=/etc/php/8.2/fpm/conf.d --libdir=/usr/lib/php --libexecdir=/usr/lib/php --datadir=/usr/share/php/8.2 --program-suffix=8.2 --sysconfdir=/etc --localstatedir=/var --mandir=/usr/share/man --enable-fpm --enable-cli --disable-cgi --disable-phpdbg --with-fpm-systemd --with-fpm-user=www-data --with-fpm-group=www-data --with-layout=GNU --disable-dtrace --disable-short-tags --without-valgrind --disable-shared --disable-debug --disable-rpath --without-pear --with-openssl --enable-bcmath --with-bz2 --enable-calendar --with-curl --enable-dba --with-qdbm --with-lmdb --enable-exif --enable-ftp --enable-gd --with-external-gd --with-jpeg --with-webp --with-xpm --with-freetype --enable-gd-jis-conv --with-gettext --with-gmp --with-mhash --with-imap --with-imap-ssl --with-kerberos --enable-intl --with-ldap --with-ldap-sasl --enable-mbstring --with-mysqli --with-pdo-mysql --enable-mysqlnd --with-mysql-sock=/run/mysqld/mysqld.sock --with-zlib --with-libedit --with-readline --enable-shmop --enable-soap --enable-sockets --with-sodium --with-password-argon2 --with-tidy --with-xsl --with-enchant --with-pspell --with-zip --with-ffi --enable-apcu --enable-brotli --with-libbrotli --with-imagick --with-ssh2 --with-gnupg --enable-igbinary --with-msgpack --enable-sysvsem --enable-sysvmsg --enable-sysvshm
make -j $PROC_LIMIT install
make distclean
git reset --hard
ln -fs /usr/bin/php8.5 /usr/bin/php
cd ..
ldconfig

# install composer
curl -sSL https://github.com/composer/composer/releases/download/2.11.1/composer.phar > /usr/bin/composer
chmod +x /usr/bin/composer
composer self-update

#rspamd dkim directory
mkdir -p /var/lib/rspamd/dkim
chown _rspamd: /var/lib/rspamd/dkim

# mysql encryption
if [ ! -e /etc/mysql/encryption/keyfile.enc ]; then
	mkdir -p /etc/mysql/encryption/
	openssl rand -hex 128 > /etc/mysql/encryption/keyfile.key
	echo "1;"$(openssl rand -hex 32) | openssl enc -aes-256-cbc -md sha1 -pass file:/etc/mysql/encryption/keyfile.key -out /etc/mysql/encryption/keyfile.enc
fi

# install php applications
if [ ! -e /var/www/html/phpmyadmin ]; then
	mkdir -p /var/www/html/phpmyadmin
	cd /var/www/html/phpmyadmin
	git clone -b STABLE https://github.com/phpmyadmin/phpmyadmin/ .
	composer install --no-dev --no-interaction --optimize-autoloader
	yarn
fi
if [ ! -e /var/www/html/adminer ]; then
	mkdir -p /var/www/html/adminer
	cd /var/www/html/adminer
	git clone https://github.com/vrana/adminer/ .
	git submodule update --init
fi
if [ ! -e /var/www/html/squirrelmail ]; then
	mkdir -p /var/www/html/squirrelmail
	cd /var/www/html/squirrelmail
	git clone https://github.com/RealityRipple/squirrelmail .
	mkdir -p /var/www/data/squirrelmail/data /var/www/data/squirrelmail/attach
	chown www-data:www-data -R /var/www/data
fi
#Disable sftp subsystem so we can override it
sed -i 's/^Subsystem/#Subsystem/' /etc/ssh/sshd_config
