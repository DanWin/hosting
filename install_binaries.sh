#!/bin/sh
git clone https://github.com/nginx/nginx
cd nginx
git clone https://github.com/google/ngx_brotli
# apply dynamic TLS record patch by CloudFlare
cat | git apply - <<EOF
--- a/src/event/ngx_event_openssl.c
+++ b/src/event/ngx_event_openssl.c
@@ -1267,6 +1267,7 @@ ngx_ssl_create_connection(ngx_ssl_t *ssl, ngx_connection_t *c, ngx_uint_t flags)
 
     sc->buffer = ((flags & NGX_SSL_BUFFER) != 0);
     sc->buffer_size = ssl->buffer_size;
+    sc->dyn_rec = ssl->dyn_rec;
 
     sc->session_ctx = ssl->ctx;
 
@@ -2115,6 +2116,41 @@ ngx_ssl_send_chain(ngx_connection_t *c, ngx_chain_t *in, off_t limit)
 
     for ( ;; ) {
 
+        /* Dynamic record resizing:
+           We want the initial records to fit into one TCP segment
+           so we don't get TCP HoL blocking due to TCP Slow Start.
+           A connection always starts with small records, but after
+           a given amount of records sent, we make the records larger
+           to reduce header overhead.
+           After a connection has idled for a given timeout, begin
+           the process from the start. The actual parameters are
+           configurable. If dyn_rec_timeout is 0, we assume dyn_rec is off. */
+
+        if (c->ssl->dyn_rec.timeout > 0 ) {
+
+            if (ngx_current_msec - c->ssl->dyn_rec_last_write >
+                c->ssl->dyn_rec.timeout)
+            {
+                buf->end = buf->start + c->ssl->dyn_rec.size_lo;
+                c->ssl->dyn_rec_records_sent = 0;
+
+            } else {
+                if (c->ssl->dyn_rec_records_sent >
+                    c->ssl->dyn_rec.threshold * 2)
+                {
+                    buf->end = buf->start + c->ssl->buffer_size;
+
+                } else if (c->ssl->dyn_rec_records_sent >
+                           c->ssl->dyn_rec.threshold)
+                {
+                    buf->end = buf->start + c->ssl->dyn_rec.size_hi;
+
+                } else {
+                    buf->end = buf->start + c->ssl->dyn_rec.size_lo;
+                }
+            }
+        }
+
         while (in && buf->last < buf->end && send < limit) {
             if (in->buf->last_buf || in->buf->flush) {
                 flush = 1;
@@ -2222,6 +2258,9 @@ ngx_ssl_write(ngx_connection_t *c, u_char *data, size_t size)
 
     if (n > 0) {
 
+        c->ssl->dyn_rec_records_sent++;
+        c->ssl->dyn_rec_last_write = ngx_current_msec;
+
         if (c->ssl->saved_read_handler) {
 
             c->read->handler = c->ssl->saved_read_handler;
--- a/src/event/ngx_event_openssl.h
+++ b/src/event/ngx_event_openssl.h
@@ -64,10 +64,19 @@
 #endif
 
 
+typedef struct {
+    ngx_msec_t                  timeout;
+    ngx_uint_t                  threshold;
+    size_t                      size_lo;
+    size_t                      size_hi;
+} ngx_ssl_dyn_rec_t;
+
+
 struct ngx_ssl_s {
     SSL_CTX                    *ctx;
     ngx_log_t                  *log;
     size_t                      buffer_size;
+    ngx_ssl_dyn_rec_t           dyn_rec;
 };
 
 
@@ -95,6 +104,11 @@ struct ngx_ssl_connection_s {
     unsigned                    no_wait_shutdown:1;
     unsigned                    no_send_shutdown:1;
     unsigned                    handshake_buffer_set:1;
+
+    ngx_ssl_dyn_rec_t           dyn_rec;
+    ngx_msec_t                  dyn_rec_last_write;
+    ngx_uint_t                  dyn_rec_records_sent;
+
     unsigned                    try_early_data:1;
     unsigned                    in_early:1;
     unsigned                    early_preread:1;
@@ -107,7 +121,7 @@ struct ngx_ssl_connection_s {
 #define NGX_SSL_DFLT_BUILTIN_SCACHE  -5
 
 
-#define NGX_SSL_MAX_SESSION_SIZE  4096
+#define NGX_SSL_MAX_SESSION_SIZE  16384
 
 typedef struct ngx_ssl_sess_id_s  ngx_ssl_sess_id_t;
 
--- a/src/http/modules/ngx_http_ssl_module.c
+++ b/src/http/modules/ngx_http_ssl_module.c
@@ -246,6 +246,41 @@ static ngx_command_t  ngx_http_ssl_commands[] = {
       offsetof(ngx_http_ssl_srv_conf_t, early_data),
       NULL },
 
+    { ngx_string("ssl_dyn_rec_enable"),
+      NGX_HTTP_MAIN_CONF|NGX_HTTP_SRV_CONF|NGX_CONF_FLAG,
+      ngx_conf_set_flag_slot,
+      NGX_HTTP_SRV_CONF_OFFSET,
+      offsetof(ngx_http_ssl_srv_conf_t, dyn_rec_enable),
+      NULL },
+
+    { ngx_string("ssl_dyn_rec_timeout"),
+      NGX_HTTP_MAIN_CONF|NGX_HTTP_SRV_CONF|NGX_CONF_FLAG,
+      ngx_conf_set_msec_slot,
+      NGX_HTTP_SRV_CONF_OFFSET,
+      offsetof(ngx_http_ssl_srv_conf_t, dyn_rec_timeout),
+      NULL },
+
+    { ngx_string("ssl_dyn_rec_size_lo"),
+      NGX_HTTP_MAIN_CONF|NGX_HTTP_SRV_CONF|NGX_CONF_FLAG,
+      ngx_conf_set_size_slot,
+      NGX_HTTP_SRV_CONF_OFFSET,
+      offsetof(ngx_http_ssl_srv_conf_t, dyn_rec_size_lo),
+      NULL },
+
+    { ngx_string("ssl_dyn_rec_size_hi"),
+      NGX_HTTP_MAIN_CONF|NGX_HTTP_SRV_CONF|NGX_CONF_FLAG,
+      ngx_conf_set_size_slot,
+      NGX_HTTP_SRV_CONF_OFFSET,
+      offsetof(ngx_http_ssl_srv_conf_t, dyn_rec_size_hi),
+      NULL },
+
+    { ngx_string("ssl_dyn_rec_threshold"),
+      NGX_HTTP_MAIN_CONF|NGX_HTTP_SRV_CONF|NGX_CONF_FLAG,
+      ngx_conf_set_num_slot,
+      NGX_HTTP_SRV_CONF_OFFSET,
+      offsetof(ngx_http_ssl_srv_conf_t, dyn_rec_threshold),
+      NULL },
+
       ngx_null_command
 };
 
@@ -576,6 +611,11 @@ ngx_http_ssl_create_srv_conf(ngx_conf_t *cf)
     sscf->session_ticket_keys = NGX_CONF_UNSET_PTR;
     sscf->stapling = NGX_CONF_UNSET;
     sscf->stapling_verify = NGX_CONF_UNSET;
+    sscf->dyn_rec_enable = NGX_CONF_UNSET;
+    sscf->dyn_rec_timeout = NGX_CONF_UNSET_MSEC;
+    sscf->dyn_rec_size_lo = NGX_CONF_UNSET_SIZE;
+    sscf->dyn_rec_size_hi = NGX_CONF_UNSET_SIZE;
+    sscf->dyn_rec_threshold = NGX_CONF_UNSET_UINT;
 
     return sscf;
 }
@@ -643,6 +683,20 @@ ngx_http_ssl_merge_srv_conf(ngx_conf_t *cf, void *parent, void *child)
     ngx_conf_merge_str_value(conf->stapling_responder,
                          prev->stapling_responder, "");
 
+    ngx_conf_merge_value(conf->dyn_rec_enable, prev->dyn_rec_enable, 0);
+    ngx_conf_merge_msec_value(conf->dyn_rec_timeout, prev->dyn_rec_timeout,
+                             1000);
+    /* Default sizes for the dynamic record sizes are defined to fit maximal
+       TLS + IPv6 overhead in a single TCP segment for lo and 3 segments for hi:
+       1369 = 1500 - 40 (IP) - 20 (TCP) - 10 (Time) - 61 (Max TLS overhead) */
+    ngx_conf_merge_size_value(conf->dyn_rec_size_lo, prev->dyn_rec_size_lo,
+                             1369);
+    /* 4229 = (1500 - 40 - 20 - 10) * 3  - 61 */
+    ngx_conf_merge_size_value(conf->dyn_rec_size_hi, prev->dyn_rec_size_hi,
+                             4229);
+    ngx_conf_merge_uint_value(conf->dyn_rec_threshold, prev->dyn_rec_threshold,
+                             40);
+
     conf->ssl.log = cf->log;
 
     if (conf->enable) {
@@ -827,6 +881,28 @@ ngx_http_ssl_merge_srv_conf(ngx_conf_t *cf, void *parent, void *child)
         return NGX_CONF_ERROR;
     }
 
+    if (conf->dyn_rec_enable) {
+        conf->ssl.dyn_rec.timeout = conf->dyn_rec_timeout;
+        conf->ssl.dyn_rec.threshold = conf->dyn_rec_threshold;
+
+        if (conf->buffer_size > conf->dyn_rec_size_lo) {
+            conf->ssl.dyn_rec.size_lo = conf->dyn_rec_size_lo;
+
+        } else {
+            conf->ssl.dyn_rec.size_lo = conf->buffer_size;
+        }
+
+        if (conf->buffer_size > conf->dyn_rec_size_hi) {
+            conf->ssl.dyn_rec.size_hi = conf->dyn_rec_size_hi;
+
+        } else {
+            conf->ssl.dyn_rec.size_hi = conf->buffer_size;
+        }
+
+    } else {
+        conf->ssl.dyn_rec.timeout = 0;
+    }
+
     return NGX_CONF_OK;
 }
 
--- a/src/http/modules/ngx_http_ssl_module.h
+++ b/src/http/modules/ngx_http_ssl_module.h
@@ -58,6 +58,12 @@ typedef struct {
 
     u_char                         *file;
     ngx_uint_t                      line;
+
+    ngx_flag_t                      dyn_rec_enable;
+    ngx_msec_t                      dyn_rec_timeout;
+    size_t                          dyn_rec_size_lo;
+    size_t                          dyn_rec_size_hi;
+    ngx_uint_t                      dyn_rec_threshold;
 } ngx_http_ssl_srv_conf_t;
 
 
EOF

./auto/configure --prefix=/usr/share/nginx --sbin-path=/usr/sbin --conf-path=/etc/nginx/nginx.conf --http-log-path=/var/log/nginx/access.log --error-log-path=/var/log/nginx/error.log --lock-path=/var/lock/nginx.lock --pid-path=/run/nginx.pid --http-client-body-temp-path=/tmp/body --http-fastcgi-temp-path=/tmp/fastcgi --http-proxy-temp-path=/tmp/proxy --with-threads --with-pcre-jit --with-http_ssl_module --with-http_v2_module --with-http_gzip_static_module --without-http_ssi_module --without-http_userid_module --without-http_access_module --without-http_mirror_module --without-http_geo_module --without-http_split_clients_module --without-http_uwsgi_module --without-http_scgi_module --without-http_grpc_module --without-http_memcached_module --without-http_limit_conn_module --without-http_limit_req_module --without-http_empty_gif_module --without-http_browser_module --without-http_upstream_hash_module --without-http_upstream_ip_hash_module --without-http_upstream_least_conn_module --without-http_upstream_keepalive_module --without-http_upstream_zone_module --with-stream --with-stream_ssl_module --without-stream_limit_conn_module --without-stream_access_module --without-stream_geo_module --without-stream_map_module --without-stream_split_clients_module --without-stream_return_module --without-stream_upstream_hash_module --without-stream_upstream_least_conn_module --without-stream_upstream_zone_module --with-cc-opt='-O3 -march=native -mtune=native -fstack-protector-strong -Wformat -Werror=format-security -fPIC -Wdate-time -D_FORTIFY_SOURCE=2' --with-ld-opt='-Wl,-z,relro -Wl,-z,now -fPIC' --add-module=ngx_brotli
make -j $(nproc) install
make distclean
git reset --hard
cd ..
ln -fs /usr/include/qdbm/depot.h /usr/include/depot.h
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
ln -fs /usr/bin/php7.4 /usr/bin/php
cd ..
