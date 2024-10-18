#!/bin/sh
set -e

export COMPOSER_ALLOW_SUPERUSER=1
export LANG=C.UTF-8
export PATH="/usr/local/bin:/usr/bin:/bin:/usr/local/sbin:/usr/sbin:/sbin"
# install all required packages
DEBIAN_FRONTEND=noninteractive apt-get update
DEBIAN_FRONTEND=noninteractive apt-get --no-install-recommends install -y apt-transport-tor bash-completion bind9 brotli bzip2 ca-certificates clamav-daemon clamav-freshclam curl dovecot-imapd dovecot-lmtpd dovecot-pop3d git hardlink haveged iptables jailkit libio-socket-ip-perl libnginx-mod-http-brotli libnginx-mod-stream libsasl2-modules locales locales-all logrotate lsb-release mariadb-server nano nginx postfix postfix-mysql quota quotatool redis rspamd rsync ssh tor unzip util-linux vim wget xz-utils zip zopfli
# build dependencies
DEBIAN_FRONTEND=noninteractive apt-get --no-install-recommends install -y autoconf automake bison g++ gcc ghostscript gnupg libaom-dev $(apt-cache search --names-only 'libargon2(-0)?-dev' | awk '{print $1;}' | head -n1) binutils-dev libbrotli-dev libbz2-dev libc-client2007e-dev libcurl4-openssl-dev libdjvulibre-dev libedit-dev $(apt-cache search --names-only 'libenchant(-2)?-dev' | awk '{print $1;}' | head -n1) libffi-dev $(apt-cache search --names-only libfreetype6?-dev | awk '{print $1;}' | head -n1) libfftw3-dev libfribidi-dev libgd-dev libgmp-dev libgpg-error-dev libgpgme-dev libgraphviz-dev libgs-dev libharfbuzz-dev libheif-dev libjbig-dev libjbig2dec0-dev libjxl-dev libkrb5-dev libldap2-dev liblmdb-dev liblqr-1-0-dev libmariadb-dev libonig-dev libopenexr-dev libopenjp2-7-dev libpango1.0-dev libpng-dev libpspell-dev libqdbm-dev libraqm-dev libraw-dev libreadline-dev librsvg2-dev libsasl2-dev libsodium-dev libssh2-1-dev libssl-dev libsqlite3-dev libsystemd-dev libtidy-dev libtool libwebp-dev libwmf-dev libxml2-dev libxpm-dev libxslt1-dev libzip-dev libzstd-dev make poppler-utils re2c zlib1g-dev

# install nvm
curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.40.1/install.sh | bash
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
	git clone https://github.com/php-gnupg/php-gnupg --recurse-submodules
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
git checkout 7.1.1-39
CXXFLAGS='-O3 -mtune=native -march=native' CFLAGS='-O3 -mtune=native -march=native' ./configure --without-perl --without-magick-plus-plus --disable-openmp --with-fftw --with-gslib --with-gvc --with-rsvg --with-wmf
make -j $PROC_LIMIT install
make distclean
ldconfig
cd ..
ln -fs /usr/include/qdbm/depot.h /usr/include/depot.h
cd php-src
cd ext
cd apcu && git fetch --all && git checkout v5.1.24 && cd ..
cd php-ext-brotli && git fetch --all && git checkout 0.15.0 && cd ..
cd imagick && git fetch --all && git checkout 3.7.0 && cd ..
cd php-gnupg && git fetch --all --recurse-submodules && git checkout gnupg-1.5.1 --recurse-submodules && cd ..
cd php-rar && git fetch --all && git reset --hard && git checkout ab26d285759e4c917879967b09976a44829ed570
cat <<EOF | git apply -
From 9be22919015ec050678917aadacb28904317ea46 Mon Sep 17 00:00:00 2001
From: Remi Collet <remi@remirepo.net>
Date: Thu, 15 Sep 2022 10:28:06 +0200
Subject: [PATCH 1/2] ignore more build artefacts

---
 .gitignore | 2 ++
 1 file changed, 2 insertions(+)

diff --git a/.gitignore b/.gitignore
index 86886db..288c25b 100644
--- a/.gitignore
+++ b/.gitignore
@@ -11,6 +11,7 @@
 /modules
 /missing
 /.deps
+*.dep
 /.libs
 /Makefile
 /Makefile.fragments
@@ -36,6 +37,7 @@
 /libtool
 /mkinstalldirs
 /ltmain.sh
+/ltmain.sh.backup
 /.cproject
 /.project
 /.settings

From 02331ca1cc1e8638c34e024566f4b391a6c863c5 Mon Sep 17 00:00:00 2001
From: Remi Collet <remi@remirepo.net>
Date: Thu, 15 Sep 2022 10:28:23 +0200
Subject: [PATCH 2/2] fix __toString prototype for PHP 8.2

---
 rararch.c  | 9 ++++++++-
 rarentry.c | 9 ++++++++-
 2 files changed, 16 insertions(+), 2 deletions(-)

diff --git a/rararch.c b/rararch.c
index 7cbfa26..9cad093 100644
--- a/rararch.c
+++ b/rararch.c
@@ -970,6 +970,13 @@ ZEND_END_ARG_INFO()
 
 ZEND_BEGIN_ARG_INFO(arginfo_rararchive_void, 0)
 ZEND_END_ARG_INFO()
+
+#if PHP_VERSION_ID >= 80200
+ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_rararchive_tostring, 0, 0, IS_STRING, 0)
+ZEND_END_ARG_INFO()
+#else
+#define arginfo_rararchive_tostring arginfo_rararchive_void
+#endif
 /* }}} */
 
 static zend_function_entry php_rararch_class_functions[] = {
@@ -984,7 +991,7 @@ static zend_function_entry php_rararch_class_functions[] = {
 	PHP_ME_MAPPING(isBroken,		rar_broken_is,			arginfo_rararchive_void,		ZEND_ACC_PUBLIC)
 	PHP_ME_MAPPING(setAllowBroken,	rar_allow_broken_set,	arginfo_rararchive_setallowbroken, ZEND_ACC_PUBLIC)
 	PHP_ME_MAPPING(close,			rar_close,				arginfo_rararchive_void,		ZEND_ACC_PUBLIC)
-	PHP_ME(rararch,					__toString,				arginfo_rararchive_void,		ZEND_ACC_PUBLIC)
+	PHP_ME(rararch,					__toString,				arginfo_rararchive_tostring,	ZEND_ACC_PUBLIC)
 	PHP_ME_MAPPING(__construct,		rar_bogus_ctor,			arginfo_rararchive_void,		ZEND_ACC_PRIVATE | ZEND_ACC_CTOR)
 #if PHP_MAJOR_VERSION >= 8
 	PHP_ME(rararch,					getIterator,			arginfo_rararchive_getiterator,	ZEND_ACC_PUBLIC)
diff --git a/rarentry.c b/rarentry.c
index 5e680f6..cb5bdaa 100644
--- a/rarentry.c
+++ b/rarentry.c
@@ -735,6 +735,13 @@ ZEND_END_ARG_INFO()
 
 ZEND_BEGIN_ARG_INFO(arginfo_rar_void, 0)
 ZEND_END_ARG_INFO()
+
+#if PHP_VERSION_ID >= 80200
+ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_rar_tostring, 0, 0, IS_STRING, 0)
+ZEND_END_ARG_INFO()
+#else
+#define arginfo_rar_tostring arginfo_rar_void
+#endif
 /* }}} */
 
 static zend_function_entry php_rar_class_functions[] = {
@@ -755,7 +762,7 @@ static zend_function_entry php_rar_class_functions[] = {
 	PHP_ME(rarentry,		getRedirType,		arginfo_rar_void,	ZEND_ACC_PUBLIC)
 	PHP_ME(rarentry,		isRedirectToDirectory,	arginfo_rar_void,	ZEND_ACC_PUBLIC)
 	PHP_ME(rarentry,		getRedirTarget,	arginfo_rar_void,	ZEND_ACC_PUBLIC)
-	PHP_ME(rarentry,		__toString,			arginfo_rar_void,	ZEND_ACC_PUBLIC)
+	PHP_ME(rarentry,		__toString,			arginfo_rar_tostring,	ZEND_ACC_PUBLIC)
 	PHP_ME_MAPPING(__construct,	rar_bogus_ctor,	arginfo_rar_void,	ZEND_ACC_PRIVATE | ZEND_ACC_CTOR)
 	{NULL, NULL, NULL}
 };
EOF

cd ..
cd igbinary && git fetch --all && git checkout 3.2.16 && cd ..
cd msgpack-php && git fetch --all && git checkout msgpack-2.2.0 && cd ..
rm -rf ssh2-*
curl -sSf https://pecl.php.net/get/ssh2 | tar xzvf - --exclude package.xml
cd ..
git fetch --all
git fetch --all --tags
git checkout php-8.3.12
./buildconf -f
LIBS='-lgpg-error' CXXFLAGS='-O3 -mtune=native -march=native' CFLAGS='-O3 -mtune=native -march=native' ./configure -C --enable-re2c-cgoto --prefix=/usr --with-config-file-scan-dir=/etc/php/8.3/fpm/conf.d --libdir=/usr/lib/php --libexecdir=/usr/lib/php --datadir=/usr/share/php/8.3 --program-suffix=8.3 --sysconfdir=/etc --localstatedir=/var --mandir=/usr/share/man --enable-fpm --enable-cli --disable-cgi --disable-phpdbg --with-fpm-systemd --with-fpm-user=www-data --with-fpm-group=www-data --with-layout=GNU --disable-dtrace --disable-short-tags --without-valgrind --disable-shared --disable-debug --disable-rpath --without-pear --with-openssl --enable-bcmath --with-bz2 --enable-calendar --with-curl --enable-dba --with-qdbm --with-lmdb --enable-exif --enable-ftp --enable-gd --with-external-gd --with-jpeg --with-webp --with-xpm --with-freetype --enable-gd-jis-conv --with-gettext --with-gmp --with-mhash --with-imap --with-imap-ssl --with-kerberos --enable-intl --with-ldap --with-ldap-sasl --enable-mbstring --with-mysqli --with-pdo-mysql --enable-mysqlnd --with-mysql-sock=/run/mysqld/mysqld.sock --with-zlib --with-libedit --with-readline --enable-shmop --enable-soap --enable-sockets --with-sodium --with-password-argon2 --with-tidy --with-xsl --with-enchant --with-pspell --with-zip --with-ffi --enable-apcu --enable-brotli --with-libbrotli --with-imagick --with-ssh2 --with-gnupg --enable-rar --enable-igbinary --with-msgpack --enable-sysvsem --enable-sysvmsg --enable-sysvshm
make -j $PROC_LIMIT install
make distclean
git reset --hard
git checkout php-8.2.24
./buildconf -f
LIBS='-lgpg-error' CXXFLAGS='-O3 -mtune=native -march=native' CFLAGS='-O3 -mtune=native -march=native' ./configure -C --enable-re2c-cgoto --prefix=/usr --with-config-file-scan-dir=/etc/php/8.2/fpm/conf.d --libdir=/usr/lib/php --libexecdir=/usr/lib/php --datadir=/usr/share/php/8.2 --program-suffix=8.2 --sysconfdir=/etc --localstatedir=/var --mandir=/usr/share/man --enable-fpm --enable-cli --disable-cgi --disable-phpdbg --with-fpm-systemd --with-fpm-user=www-data --with-fpm-group=www-data --with-layout=GNU --disable-dtrace --disable-short-tags --without-valgrind --disable-shared --disable-debug --disable-rpath --without-pear --with-openssl --enable-bcmath --with-bz2 --enable-calendar --with-curl --enable-dba --with-qdbm --with-lmdb --enable-exif --enable-ftp --enable-gd --with-external-gd --with-jpeg --with-webp --with-xpm --with-freetype --enable-gd-jis-conv --with-gettext --with-gmp --with-mhash --with-imap --with-imap-ssl --with-kerberos --enable-intl --with-ldap --with-ldap-sasl --enable-mbstring --with-mysqli --with-pdo-mysql --enable-mysqlnd --with-mysql-sock=/run/mysqld/mysqld.sock --with-zlib --with-libedit --with-readline --enable-shmop --enable-soap --enable-sockets --with-sodium --with-password-argon2 --with-tidy --with-xsl --with-enchant --with-pspell --with-zip --with-ffi --enable-apcu --enable-brotli --with-libbrotli --with-imagick --with-ssh2 --with-gnupg --enable-rar --enable-igbinary --with-msgpack --enable-sysvsem --enable-sysvmsg --enable-sysvshm
make -j $PROC_LIMIT install
make distclean
git reset --hard
git checkout php-8.1.30
./buildconf -f
LIBS='-lgpg-error' CXXFLAGS='-O3 -mtune=native -march=native' CFLAGS='-O3 -mtune=native -march=native' ./configure -C --enable-re2c-cgoto --prefix=/usr --with-config-file-scan-dir=/etc/php/8.1/fpm/conf.d --libdir=/usr/lib/php --libexecdir=/usr/lib/php --datadir=/usr/share/php/8.1 --program-suffix=8.1 --sysconfdir=/etc --localstatedir=/var --mandir=/usr/share/man --enable-fpm --enable-cli --disable-cgi --disable-phpdbg --with-fpm-systemd --with-fpm-user=www-data --with-fpm-group=www-data --with-layout=GNU --disable-dtrace --disable-short-tags --without-valgrind --disable-shared --disable-debug --disable-rpath --without-pear --with-openssl --enable-bcmath --with-bz2 --enable-calendar --with-curl --enable-dba --with-qdbm --with-lmdb --enable-exif --enable-ftp --enable-gd --with-external-gd --with-jpeg --with-webp --with-xpm --with-freetype --enable-gd-jis-conv --with-gettext --with-gmp --with-mhash --with-imap --with-imap-ssl --with-kerberos --enable-intl --with-ldap --with-ldap-sasl --enable-mbstring --with-mysqli --with-pdo-mysql --enable-mysqlnd --with-mysql-sock=/run/mysqld/mysqld.sock --with-zlib --with-libedit --with-readline --enable-shmop --enable-soap --enable-sockets --with-sodium --with-password-argon2 --with-tidy --with-xsl --with-enchant --with-pspell --with-zip --with-ffi --enable-apcu --enable-brotli --with-libbrotli --with-imagick --with-ssh2 --with-gnupg --enable-rar --enable-igbinary --with-msgpack --enable-sysvsem --enable-sysvmsg --enable-sysvshm
make -j $PROC_LIMIT install
make distclean
git reset --hard
ln -fs /usr/bin/php8.3 /usr/bin/php
cd ..
ldconfig

# install composer
curl -sSL https://github.com/composer/composer/releases/download/2.8.1/composer.phar > /usr/bin/composer
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
	cat <<EOF | git apply -
From 12196a325f6714bfb94c328e457bd023bfbaa171 Mon Sep 17 00:00:00 2001
From: Peter Nikolow <peter@mobiliodevelopment.com>
Date: Tue, 31 May 2022 13:22:14 +0300
Subject: [PATCH] Update .gitmodules

Fix github recursive clone
---
 .gitmodules | 4 ++--
 1 file changed, 2 insertions(+), 2 deletions(-)

diff --git a/.gitmodules b/.gitmodules
index 5810a5e7..7688bddb 100644
--- a/.gitmodules
+++ b/.gitmodules
@@ -1,9 +1,9 @@
 [submodule "jush"]
 	path = externals/jush
-	url = git://github.com/vrana/jush
+	url = https://github.com/vrana/jush
 [submodule "JsShrink"]
 	path = externals/JsShrink
-	url = git://github.com/vrana/JsShrink
+	url = https://github.com/vrana/JsShrink
 [submodule "designs/hydra"]
 	path = designs/hydra
 	url = https://github.com/Niyko/Hydra-Dark-Theme-for-Adminer
EOF
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
