#!/bin/sh
apt-get install --no-install-recommends -y bison ghostscript libreadline-dev librecode-dev libmariadb-dev libargon2-dev libbz2-dev libc-client2007e-dev libcurl4-openssl-dev libedit-dev re2c libxml2-dev libsqlite3-dev libsystemd-dev libwebp-dev libgmp-dev libkrb5-dev libldap2-dev libsasl2-dev libonig-dev libtidy-dev libxslt1-dev libenchant-dev libpspell-dev libzip-dev libxpm-dev libffi-dev libgd-dev libmagickwand-dev libssh2-1-dev liblmdb-dev libpng-dev poppler-utils libqdbm-dev
ln -s /usr/include/qdbm/depot.h /usr/include/depot.h
git clone https://github.com/php/php-src
cd php-src
git checkout PHP-7.4
cd ext
git clone https://github.com/krakjoe/apcu
git clone https://github.com/kjdev/php-ext-brotli
git clone https://github.com/Imagick/imagick
#git clone https://github.com/php-gnupg/php-gnupg && cd php-gnupg && git submodule update --init && cd ..
#git clone https://github.com/cataphract/php-rar
curl -sSf https://pecl.php.net/get/ssh2 | tar xzvf - --exclude package.xml
cd ..
./buildconf
CXXFLAGS='-O3 -mtune=native -march=native' CFLAGS='-O3 -mtune=native -march=native' ./configure -C --enable-re2c-cgoto --prefix=/usr --with-config-file-scan-dir=/etc/php/7.4/fpm/conf.d --libdir=/usr/lib/php --libexecdir=/usr/lib/php --datadir=/usr/share/php/7.4 --program-suffix=7.4 --sysconfdir=/etc --localstatedir=/var --mandir=/usr/share/man --enable-fpm --enable-cli --disable-cgi --disable-phpdbg --with-fpm-systemd --with-fpm-user=www-data --with-fpm-group=www-data --with-layout=GNU --disable-dtrace --disable-short-tags --without-valgrind --disable-shared --disable-debug --disable-rpath --without-pear --with-openssl --enable-bcmath --with-bz2 --enable-calendar --with-curl --enable-dba --with-qdbm --with-lmdb --enable-exif --enable-ftp --enable-gd --with-external-gd --with-jpeg --with-webp --with-xpm --with-freetype --enable-gd-jis-conv --with-gettext --with-gmp --with-mhash --with-imap --with-imap-ssl --with-kerberos --enable-intl --with-ldap --with-ldap-sasl --enable-mbstring --with-mysqli --with-pdo-mysql --enable-mysqlnd --with-mysql-sock=/var/run/mysqld/mysqld.sock --with-zlib --with-libedit --with-readline --enable-shmop --enable-soap --enable-sockets --with-sodium --with-password-argon2 --with-tidy --with-xmlrpc --with-xsl --with-enchant --with-pspell --with-zip --with-ffi --enable-apcu --enable-brotli --with-libbrotli --with-imagick --with-ssh2
make -j $(nproc) install
make distclean
git checkout PHP-7.3
cat | git apply - <<EOF
From: =?utf-8?b?T25kxZllaiBTdXLDvQ==?= <ondrej@sury.org>
Date: Mon, 22 Oct 2018 06:54:31 +0000
Subject: Use pkg-config for FreeType2 detection

---
 ext/gd/config.m4 | 30 +++++++++++++++++++-----------
 1 file changed, 19 insertions(+), 11 deletions(-)

diff --git a/ext/gd/config.m4 b/ext/gd/config.m4
index 498d870..d28c6ae 100644
--- a/ext/gd/config.m4
+++ b/ext/gd/config.m4
@@ -184,21 +184,29 @@ AC_DEFUN([PHP_GD_XPM],[
 AC_DEFUN([PHP_GD_FREETYPE2],[
   if test "\$PHP_FREETYPE_DIR" != "no"; then
 
-    for i in \$PHP_FREETYPE_DIR /usr/local /usr; do
-      if test -f "\$i/bin/freetype-config"; then
-        FREETYPE2_DIR=\$i
-        FREETYPE2_CONFIG="\$i/bin/freetype-config"
-        break
+    if test -z "\$PKG_CONFIG"; then
+      AC_PATH_PROG(PKG_CONFIG, pkg-config, no)
+    fi
+    if test -x "\$PKG_CONFIG" && \$PKG_CONFIG --exists freetype2 ; then
+      FREETYPE2_CFLAGS=\`\$PKG_CONFIG --cflags freetype2\`
+      FREETYPE2_LIBS=\`\$PKG_CONFIG --libs freetype2\`
+    else
+      for i in \$PHP_FREETYPE_DIR /usr/local /usr; do
+        if test -f "\$i/bin/freetype-config"; then
+          FREETYPE2_DIR=\$i
+          FREETYPE2_CONFIG="\$i/bin/freetype-config"
+          break
+        fi
+      done
+
+      if test -z "\$FREETYPE2_DIR"; then
+        AC_MSG_ERROR([freetype-config not found.])
       fi
-    done
 
-    if test -z "\$FREETYPE2_DIR"; then
-      AC_MSG_ERROR([freetype-config not found.])
+      FREETYPE2_CFLAGS=\`\$FREETYPE2_CONFIG --cflags\`
+      FREETYPE2_LIBS=\`\$FREETYPE2_CONFIG --libs\`
     fi
 
-    FREETYPE2_CFLAGS=\`\$FREETYPE2_CONFIG --cflags\`
-    FREETYPE2_LIBS=\`\$FREETYPE2_CONFIG --libs\`
-
     PHP_EVAL_INCLINE(\$FREETYPE2_CFLAGS)
     PHP_EVAL_LIBLINE(\$FREETYPE2_LIBS, GD_SHARED_LIBADD)
     AC_DEFINE(HAVE_LIBFREETYPE,1,[ ])
EOF
./buildconf
CXXFLAGS='-O3 -mtune=native -march=native' CFLAGS='-O3 -mtune=native -march=native' ./configure -C --enable-re2c-cgoto --prefix=/usr --with-config-file-scan-dir=/etc/php/7.3/fpm/conf.d --libdir=/usr/lib/php --libexecdir=/usr/lib/php --datadir=/usr/share/php/7.3 --program-suffix=7.3 --sysconfdir=/etc --localstatedir=/var --mandir=/usr/share/man --enable-fpm --enable-cli --disable-cgi --disable-phpdbg --with-fpm-systemd --with-fpm-user=www-data --with-fpm-group=www-data --with-layout=GNU --disable-dtrace --disable-short-tags --without-valgrind --disable-shared --disable-debug --disable-rpath --without-pear --with-openssl --enable-bcmath --with-bz2 --enable-calendar --with-curl --enable-dba --with-qdbm --with-lmdb --enable-exif --enable-ftp --with-gd=/usr --with-jpeg-dir=/usr --with-webp-dir=/usr --with-png-dir=/usr --with-zlib-dir=/usr --with-xpm-dir=/usr --with-freetype-dir=/usr --enable-gd-jis-conv --with-gettext --with-gmp --with-mhash --with-imap --with-imap-ssl --with-kerberos --enable-intl --with-ldap --with-ldap-sasl --enable-mbstring --with-mysqli --with-pdo-mysql --enable-mysqlnd --with-mysql-sock=/var/run/mysqld/mysqld.sock --with-zlib --with-libedit --with-readline --enable-shmop --enable-soap --enable-sockets --with-sodium --with-password-argon2 --with-tidy --with-xmlrpc --with-xsl --with-enchant --with-pspell --enable-zip --enable-apcu --enable-brotli --with-libbrotli --with-imagick --with-ssh2 --with-pcre-regex --with-pcre-jit
make -j $(nproc) install
make distclean
git reset --hard
git checkout PHP-7.2
cat | git apply - <<EOF
From: =?utf-8?b?T25kxZllaiBTdXLDvQ==?= <ondrej@sury.org>
Date: Mon, 22 Oct 2018 06:54:31 +0000
Subject: Use pkg-config for FreeType2 detection

---
 ext/gd/config.m4 | 30 +++++++++++++++++++-----------
 1 file changed, 19 insertions(+), 11 deletions(-)

diff --git a/ext/gd/config.m4 b/ext/gd/config.m4
index 498d870..d28c6ae 100644
--- a/ext/gd/config.m4
+++ b/ext/gd/config.m4
@@ -184,21 +184,29 @@ AC_DEFUN([PHP_GD_XPM],[
 AC_DEFUN([PHP_GD_FREETYPE2],[
   if test "\$PHP_FREETYPE_DIR" != "no"; then
 
-    for i in \$PHP_FREETYPE_DIR /usr/local /usr; do
-      if test -f "\$i/bin/freetype-config"; then
-        FREETYPE2_DIR=\$i
-        FREETYPE2_CONFIG="\$i/bin/freetype-config"
-        break
+    if test -z "\$PKG_CONFIG"; then
+      AC_PATH_PROG(PKG_CONFIG, pkg-config, no)
+    fi
+    if test -x "\$PKG_CONFIG" && \$PKG_CONFIG --exists freetype2 ; then
+      FREETYPE2_CFLAGS=\`\$PKG_CONFIG --cflags freetype2\`
+      FREETYPE2_LIBS=\`\$PKG_CONFIG --libs freetype2\`
+    else
+      for i in \$PHP_FREETYPE_DIR /usr/local /usr; do
+        if test -f "\$i/bin/freetype-config"; then
+          FREETYPE2_DIR=\$i
+          FREETYPE2_CONFIG="\$i/bin/freetype-config"
+          break
+        fi
+      done
+
+      if test -z "\$FREETYPE2_DIR"; then
+        AC_MSG_ERROR([freetype-config not found.])
       fi
-    done
 
-    if test -z "\$FREETYPE2_DIR"; then
-      AC_MSG_ERROR([freetype-config not found.])
+      FREETYPE2_CFLAGS=\`\$FREETYPE2_CONFIG --cflags\`
+      FREETYPE2_LIBS=\`\$FREETYPE2_CONFIG --libs\`
     fi
 
-    FREETYPE2_CFLAGS=\`\$FREETYPE2_CONFIG --cflags\`
-    FREETYPE2_LIBS=\`\$FREETYPE2_CONFIG --libs\`
-
     PHP_EVAL_INCLINE(\$FREETYPE2_CFLAGS)
     PHP_EVAL_LIBLINE(\$FREETYPE2_LIBS, GD_SHARED_LIBADD)
     AC_DEFINE(HAVE_LIBFREETYPE,1,[ ])
EOF
./buildconf
CXXFLAGS='-O3 -mtune=native -march=native' CFLAGS='-O3 -mtune=native -march=native' ./configure -C --enable-re2c-cgoto --prefix=/usr --with-config-file-scan-dir=/etc/php/7.2/fpm/conf.d --libdir=/usr/lib/php --libexecdir=/usr/lib/php --datadir=/usr/share/php/7.2 --program-suffix=7.2 --sysconfdir=/etc --localstatedir=/var --mandir=/usr/share/man --enable-fpm --enable-cli --disable-cgi --disable-phpdbg --with-fpm-systemd --with-fpm-user=www-data --with-fpm-group=www-data --with-layout=GNU --disable-dtrace --disable-short-tags --without-valgrind --disable-shared --disable-debug --disable-rpath --without-pear --with-openssl --enable-bcmath --with-bz2 --enable-calendar --with-curl --enable-dba --with-qdbm --with-lmdb --enable-exif --enable-ftp --with-gd=/usr --with-jpeg-dir=/usr --with-webp-dir=/usr --with-png-dir=/usr --with-zlib-dir=/usr --with-xpm-dir=/usr --with-freetype-dir=/usr --enable-gd-jis-conv --with-gettext --with-gmp --with-mhash --with-imap --with-imap-ssl --with-kerberos --enable-intl --with-ldap --with-ldap-sasl --enable-mbstring --with-mysqli --with-pdo-mysql --enable-mysqlnd --with-mysql-sock=/var/run/mysqld/mysqld.sock --with-zlib --with-libedit --with-readline --enable-shmop --enable-soap --enable-sockets --with-sodium --with-password-argon2 --with-tidy --with-xmlrpc --with-xsl --with-enchant --with-pspell --enable-zip --enable-apcu --enable-brotli --with-libbrotli --with-imagick --with-ssh2 --with-pcre-regex --with-pcre-jit
make -j $(nproc) install
make distclean
git reset --hard
