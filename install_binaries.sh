#!/bin/sh
set -e

export COMPOSER_ALLOW_SUPERUSER=1
export LANG=C.UTF-8
export PATH="/usr/local/bin:/usr/bin:/bin:/usr/local/sbin:/usr/sbin:/sbin"
# install all required packages
DEBIAN_FRONTEND=noninteractive apt-get update
DEBIAN_FRONTEND=noninteractive apt-get --no-install-recommends install -y apt-transport-tor bash-completion bind9 brotli bzip2 ca-certificates clamav-daemon clamav-freshclam curl dovecot-imapd dovecot-lmtpd dovecot-pop3d git hardlink haveged iptables libio-socket-ip-perl libsasl2-modules locales locales-all logrotate lsb-release mariadb-server nano postfix postfix-mysql quota quotatool redis rsync ssh subversion tor unzip util-linux vim wget xz-utils zip zopfli
# build dependencies
DEBIAN_FRONTEND=noninteractive apt-get --no-install-recommends install -y autoconf automake bison cmake g++ gcc ghostscript gnupg libaom-dev `apt-cache search --names-only 'libargon2(-0)?-dev' | awk '{print $1;}' | head -n1` binutils-dev libbrotli-dev libbz2-dev libc-client2007e-dev libcurl4-openssl-dev libdw-dev libdwarf-dev libde265-dev libdjvulibre-dev libedit-dev `apt-cache search --names-only 'libenchant(-2)?-dev' | awk '{print $1;}' | head -n1` libffi-dev `apt-cache search --names-only libfreetype6?-dev | awk '{print $1;}' | head -n1` libfftw3-dev libfribidi-dev libgd-dev libgmp-dev libgpg-error-dev libgpgme-dev libgraphviz-dev libgs-dev libharfbuzz-dev libheif-dev libhyperscan-dev libjbig-dev libjbig2dec0-dev libjxl-dev libkrb5-dev libldap2-dev liblmdb-dev liblqr-1-0-dev libluajit-5.1-dev libmariadb-dev libonig-dev libopenexr-dev libopenjp2-7-dev libpango1.0-dev libpcre3-dev libpng-dev libpspell-dev libqdbm-dev libraqm-dev libraw-dev libreadline-dev librsvg2-dev libsasl2-dev libsodium-dev libssh2-1-dev libssl-dev libsqlite3-dev libsystemd-dev libtidy-dev libtool libwebp-dev libwmf-dev libx265-dev libxml2-dev libxpm-dev libxslt1-dev libzip-dev libzstd-dev make poppler-utils ragel re2c yasm zlib1g-dev

# install nvm
curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.39.7/install.sh | bash
export NVM_DIR="$HOME/.nvm"
[ -s "$NVM_DIR/nvm.sh" ] && \. "$NVM_DIR/nvm.sh"  # This loads nvm

# install nodejs
nvm install node --latest-npm --default
for old_version in `nvm ls --no-alias --no-colors | grep -v '\->' | awk '{print $1;}'`; do nvm uninstall $old_version; done
nvm cache clear

#install yarn
npm i -g yarn

# initial repository clones
if [ ! -e ImageMagick ]; then
	git clone https://github.com/ImageMagick/ImageMagick
fi
if [ ! -e rspamd ]; then
	git clone --recurse-submodules https://github.com/rspamd/rspamd.git
fi
if [ ! -e nginx ]; then
	git clone https://github.com/nginx/nginx
fi
cd nginx
if [ ! -e ngx_brotli ]; then
	git clone https://github.com/google/ngx_brotli
fi
cd ..
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

export PROC_LIMIT=`free -g | grep Mem | awk -v nproc=$(nproc) '{print (($2 + 1) < nproc) ? ($2 + 1) : nproc;}'`
#start build
cd ImageMagick
git fetch --all
git checkout 7.1.1-34
CXXFLAGS='-O3 -mtune=native -march=native' CFLAGS='-O3 -mtune=native -march=native' ./configure --without-perl --without-magick-plus-plus --disable-openmp --with-fftw --with-gslib --with-gvc --with-rsvg --with-wmf
make -j $PROC_LIMIT install
make distclean
ldconfig
cd ../rspamd
git fetch --all --recurse-submodules
git checkout 3.8.4 --recurse-submodules
cd ..
mkdir -p rspamd_build
cd rspamd_build
cmake ../rspamd -DENABLE_HYPERSCAN=ON -DENABLE_LUAJIT=ON -DCMAKE_BUILD_TYPE=Release
make -j $PROC_LIMIT
make install
cd ..
rm -rf rspamd_build
cd nginx
git fetch --all
git checkout release-1.27.0
cd ngx_brotli && git fetch --all && git checkout 6e975bcb015f62e1f303054897783355e2a877dc && cd ..
# apply dynamic TLS record and HTTP2 HPACK patch by CloudFlare
cat <<EOF | git apply -
diff --git a/auto/modules b/auto/modules
index f1c63f3d..edb7863f 100644
--- a/auto/modules
+++ b/auto/modules
@@ -423,6 +423,10 @@ if [ \$HTTP = YES ]; then
         . auto/module
     fi
 
+    if [ \$HTTP_V2_HPACK_ENC = YES ]; then
+        have=NGX_HTTP_V2_HPACK_ENC . auto/have
+    fi
+
     if :; then
         ngx_module_name=ngx_http_static_module
         ngx_module_incs=
diff --git a/auto/options b/auto/options
index 0b21def2..69ea76cb 100644
--- a/auto/options
+++ b/auto/options
@@ -59,6 +59,7 @@ HTTP_CHARSET=YES
 HTTP_GZIP=YES
 HTTP_SSL=NO
 HTTP_V2=NO
+HTTP_V2_HPACK_ENC=NO
 HTTP_V3=NO
 HTTP_SSI=YES
 HTTP_REALIP=NO
@@ -235,6 +236,7 @@ \$0: warning: the \"--with-ipv6\" option is deprecated"
 
         --with-http_ssl_module)          HTTP_SSL=YES               ;;
         --with-http_v2_module)           HTTP_V2=YES                ;;
+        --with-http_v2_hpack_enc)        HTTP_V2_HPACK_ENC=YES      ;;
         --with-http_v3_module)           HTTP_V3=YES                ;;
         --with-http_realip_module)       HTTP_REALIP=YES            ;;
         --with-http_addition_module)     HTTP_ADDITION=YES          ;;
@@ -456,6 +458,7 @@ cat << END
 
   --with-http_ssl_module             enable ngx_http_ssl_module
   --with-http_v2_module              enable ngx_http_v2_module
+  --with-http_v2_hpack_enc           enable ngx_http_v2_hpack_enc
   --with-http_v3_module              enable ngx_http_v3_module
   --with-http_realip_module          enable ngx_http_realip_module
   --with-http_addition_module        enable ngx_http_addition_module
diff --git a/src/core/ngx_murmurhash.c b/src/core/ngx_murmurhash.c
index 5ade658d..4932f20d 100644
--- a/src/core/ngx_murmurhash.c
+++ b/src/core/ngx_murmurhash.c
@@ -50,3 +50,63 @@ ngx_murmur_hash2(u_char *data, size_t len)
 
     return h;
 }
+
+
+uint64_t
+ngx_murmur_hash2_64(u_char *data, size_t len, uint64_t seed)
+{
+    uint64_t  h, k;
+
+    h = seed ^ len;
+
+    while (len >= 8) {
+        k  = data[0];
+        k |= data[1] << 8;
+        k |= data[2] << 16;
+        k |= data[3] << 24;
+        k |= (uint64_t)data[4] << 32;
+        k |= (uint64_t)data[5] << 40;
+        k |= (uint64_t)data[6] << 48;
+        k |= (uint64_t)data[7] << 56;
+
+        k *= 0xc6a4a7935bd1e995ull;
+        k ^= k >> 47;
+        k *= 0xc6a4a7935bd1e995ull;
+
+        h ^= k;
+        h *= 0xc6a4a7935bd1e995ull;
+
+        data += 8;
+        len -= 8;
+    }
+
+    switch (len) {
+    case 7:
+        h ^= (uint64_t)data[6] << 48;
+        /* fall through */
+    case 6:
+        h ^= (uint64_t)data[5] << 40;
+        /* fall through */
+    case 5:
+        h ^= (uint64_t)data[4] << 32;
+        /* fall through */
+    case 4:
+        h ^= data[3] << 24;
+        /* fall through */
+    case 3:
+        h ^= data[2] << 16;
+        /* fall through */
+    case 2:
+        h ^= data[1] << 8;
+        /* fall through */
+    case 1:
+        h ^= data[0];
+        h *= 0xc6a4a7935bd1e995ull;
+    }
+
+    h ^= h >> 47;
+    h *= 0xc6a4a7935bd1e995ull;
+    h ^= h >> 47;
+
+    return h;
+}
diff --git a/src/core/ngx_murmurhash.h b/src/core/ngx_murmurhash.h
index 54e867d3..322b3df9 100644
--- a/src/core/ngx_murmurhash.h
+++ b/src/core/ngx_murmurhash.h
@@ -15,5 +15,7 @@
 
 uint32_t ngx_murmur_hash2(u_char *data, size_t len);
 
+uint64_t ngx_murmur_hash2_64(u_char *data, size_t len, uint64_t seed);
+
 
 #endif /* _NGX_MURMURHASH_H_INCLUDED_ */
diff --git a/src/event/ngx_event_openssl.c b/src/event/ngx_event_openssl.c
index fd2b92ff..397a4114 100644
--- a/src/event/ngx_event_openssl.c
+++ b/src/event/ngx_event_openssl.c
@@ -1590,6 +1590,7 @@ ngx_ssl_create_connection(ngx_ssl_t *ssl, ngx_connection_t *c, ngx_uint_t flags)
 
     sc->buffer = ((flags & NGX_SSL_BUFFER) != 0);
     sc->buffer_size = ssl->buffer_size;
+    sc->dyn_rec = ssl->dyn_rec;
 
     sc->session_ctx = ssl->ctx;
 
@@ -2525,6 +2526,41 @@ ngx_ssl_send_chain(ngx_connection_t *c, ngx_chain_t *in, off_t limit)
 
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
@@ -2632,6 +2668,9 @@ ngx_ssl_write(ngx_connection_t *c, u_char *data, size_t size)
 
     if (n > 0) {
 
+        c->ssl->dyn_rec_records_sent++;
+        c->ssl->dyn_rec_last_write = ngx_current_msec;
+
         if (c->ssl->saved_read_handler) {
 
             c->read->handler = c->ssl->saved_read_handler;
diff --git a/src/event/ngx_event_openssl.h b/src/event/ngx_event_openssl.h
index 329760d0..2c72f932 100644
--- a/src/event/ngx_event_openssl.h
+++ b/src/event/ngx_event_openssl.h
@@ -66,11 +66,19 @@
 
 typedef struct ngx_ssl_ocsp_s  ngx_ssl_ocsp_t;
 
+typedef struct {
+    ngx_msec_t                  timeout;
+    ngx_uint_t                  threshold;
+    size_t                      size_lo;
+    size_t                      size_hi;
+} ngx_ssl_dyn_rec_t;
+
 
 struct ngx_ssl_s {
     SSL_CTX                    *ctx;
     ngx_log_t                  *log;
     size_t                      buffer_size;
+    ngx_ssl_dyn_rec_t           dyn_rec;
 };
 
 
@@ -115,6 +123,11 @@ struct ngx_ssl_connection_s {
     unsigned                    shutdown_without_free:1;
     unsigned                    handshake_buffer_set:1;
     unsigned                    session_timeout_set:1;
+
+    ngx_ssl_dyn_rec_t           dyn_rec;
+    ngx_msec_t                  dyn_rec_last_write;
+    ngx_uint_t                  dyn_rec_records_sent;
+
     unsigned                    try_early_data:1;
     unsigned                    in_early:1;
     unsigned                    in_ocsp:1;
@@ -115,7 +128,7 @@ struct ngx_ssl_connection_s {
 #define NGX_SSL_DFLT_BUILTIN_SCACHE  -5
 
 
-#define NGX_SSL_MAX_SESSION_SIZE  4096
+#define NGX_SSL_MAX_SESSION_SIZE  16384
 
 typedef struct ngx_ssl_sess_id_s  ngx_ssl_sess_id_t;
 
diff --git a/src/http/modules/ngx_http_ssl_module.c b/src/http/modules/ngx_http_ssl_module.c
index e062b03a..2dae17d4 100644
--- a/src/http/modules/ngx_http_ssl_module.c
+++ b/src/http/modules/ngx_http_ssl_module.c
@@ -301,6 +301,41 @@ static ngx_command_t  ngx_http_ssl_commands[] = {
       offsetof(ngx_http_ssl_srv_conf_t, reject_handshake),
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
 
@@ -637,6 +672,11 @@ ngx_http_ssl_create_srv_conf(ngx_conf_t *cf)
     sscf->ocsp_cache_zone = NGX_CONF_UNSET_PTR;
     sscf->stapling = NGX_CONF_UNSET;
     sscf->stapling_verify = NGX_CONF_UNSET;
+    sscf->dyn_rec_enable = NGX_CONF_UNSET;
+    sscf->dyn_rec_timeout = NGX_CONF_UNSET_MSEC;
+    sscf->dyn_rec_size_lo = NGX_CONF_UNSET_SIZE;
+    sscf->dyn_rec_size_hi = NGX_CONF_UNSET_SIZE;
+    sscf->dyn_rec_threshold = NGX_CONF_UNSET_UINT;
 
     return sscf;
 }
@@ -694,6 +734,20 @@ ngx_http_ssl_merge_srv_conf(ngx_conf_t *cf, void *parent, void *child)
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
 
     if (conf->certificates) {
@@ -943,6 +997,28 @@ ngx_http_ssl_merge_srv_conf(ngx_conf_t *cf, void *parent, void *child)
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
 
diff --git a/src/http/modules/ngx_http_ssl_module.h b/src/http/modules/ngx_http_ssl_module.h
index 7ab0f7ea..4485a8b8 100644
--- a/src/http/modules/ngx_http_ssl_module.h
+++ b/src/http/modules/ngx_http_ssl_module.h
@@ -63,5 +63,11 @@ typedef struct {
     ngx_str_t                       stapling_file;
     ngx_str_t                       stapling_responder;
+
+    ngx_flag_t                      dyn_rec_enable;
+    ngx_msec_t                      dyn_rec_timeout;
+    size_t                          dyn_rec_size_lo;
+    size_t                          dyn_rec_size_hi;
+    ngx_uint_t                      dyn_rec_threshold;
 } ngx_http_ssl_srv_conf_t;
 
 
diff --git a/src/http/v2/ngx_http_v2.c b/src/http/v2/ngx_http_v2.c
index 58916a18..4297a0b6 100644
--- a/src/http/v2/ngx_http_v2.c
+++ b/src/http/v2/ngx_http_v2.c
@@ -245,6 +245,8 @@ ngx_http_v2_init(ngx_event_t *rev)
 
     h2c->frame_size = NGX_HTTP_V2_DEFAULT_FRAME_SIZE;
 
+    h2c->max_hpack_table_size = NGX_HTTP_V2_DEFAULT_HPACK_TABLE_SIZE;
+
     h2scf = ngx_http_get_module_srv_conf(hc->conf_ctx, ngx_http_v2_module);
 
     h2c->priority_limit = ngx_max(h2scf->concurrent_streams, 100);
@@ -2254,6 +2256,13 @@ ngx_http_v2_state_settings_params(ngx_http_v2_connection_t *h2c, u_char *pos,
 
         case NGX_HTTP_V2_HEADER_TABLE_SIZE_SETTING:
 
+            if (value > NGX_HTTP_V2_MAX_HPACK_TABLE_SIZE) {
+                h2c->max_hpack_table_size = NGX_HTTP_V2_MAX_HPACK_TABLE_SIZE;
+            } else {
+                h2c->max_hpack_table_size = value;
+            }
+
+            h2c->indicate_resize = 1;
             h2c->table_update = 1;
             break;
 
diff --git a/src/http/v2/ngx_http_v2.h b/src/http/v2/ngx_http_v2.h
index 34922971..78bf9fc6 100644
--- a/src/http/v2/ngx_http_v2.h
+++ b/src/http/v2/ngx_http_v2.h
@@ -54,6 +54,13 @@
 
 #define NGX_HTTP_V2_DEFAULT_WEIGHT       16
 
+#define HPACK_ENC_HTABLE_SZ              128 /* better to keep a PoT < 64k */
+#define HPACK_ENC_HTABLE_ENTRIES         ((HPACK_ENC_HTABLE_SZ * 100) / 128)
+#define HPACK_ENC_DYNAMIC_KEY_TBL_SZ     10  /* 10 is sufficient for most */
+#define HPACK_ENC_MAX_ENTRY              512 /* longest header size to match */
+
+#define NGX_HTTP_V2_DEFAULT_HPACK_TABLE_SIZE     4096
+#define NGX_HTTP_V2_MAX_HPACK_TABLE_SIZE         16384 /* < 64k */
 
 typedef struct ngx_http_v2_connection_s   ngx_http_v2_connection_t;
 typedef struct ngx_http_v2_node_s         ngx_http_v2_node_t;
@@ -115,6 +122,46 @@ typedef struct {
 } ngx_http_v2_hpack_t;
 
 
+#if (NGX_HTTP_V2_HPACK_ENC)
+typedef struct {
+    uint64_t                         hash_val;
+    uint32_t                         index;
+    uint16_t                         pos;
+    uint16_t                         klen, vlen;
+    uint16_t                         size;
+    uint16_t                         next;
+} ngx_http_v2_hpack_enc_entry_t;
+
+
+typedef struct {
+    uint64_t                         hash_val;
+    uint32_t                         index;
+    uint16_t                         pos;
+    uint16_t                         klen;
+} ngx_http_v2_hpack_name_entry_t;
+
+
+typedef struct {
+    size_t                           size;    /* size as defined in RFC 7541 */
+    uint32_t                         top;     /* the last entry */
+    uint32_t                         pos;
+    uint16_t                         n_elems; /* number of elements */
+    uint16_t                         base;    /* index of the oldest entry */
+    uint16_t                         last;    /* index of the newest entry */
+
+    /* hash table for dynamic entries, instead using a generic hash table,
+       which would be too slow to process a significant amount of headers,
+       this table is not determenistic, and might ocasionally fail to insert
+       a value, at the cost of slightly worse compression, but significantly
+       faster performance */
+    ngx_http_v2_hpack_enc_entry_t    htable[HPACK_ENC_HTABLE_SZ];
+    ngx_http_v2_hpack_name_entry_t   heads[HPACK_ENC_DYNAMIC_KEY_TBL_SZ];
+    u_char                           storage[NGX_HTTP_V2_MAX_HPACK_TABLE_SIZE +
+                                             HPACK_ENC_MAX_ENTRY];
+} ngx_http_v2_hpack_enc_t;
+#endif
+
+
 struct ngx_http_v2_connection_s {
     ngx_connection_t                *connection;
     ngx_http_connection_t           *http_connection;
@@ -136,6 +183,8 @@ struct ngx_http_v2_connection_s {
 
     size_t                           frame_size;
 
+    size_t                           max_hpack_table_size;
+
     ngx_queue_t                      waiting;
 
     ngx_http_v2_state_t              state;
@@ -165,6 +214,11 @@ struct ngx_http_v2_connection_s {
     unsigned                         table_update:1;
     unsigned                         blocked:1;
     unsigned                         goaway:1;
+    unsigned                         indicate_resize:1;
+
+#if (NGX_HTTP_V2_HPACK_ENC)
+    ngx_http_v2_hpack_enc_t          hpack_enc;
+#endif
 };
 
 
@@ -413,4 +467,31 @@ u_char *ngx_http_v2_string_encode(u_char *dst, u_char *src, size_t len,
     u_char *tmp, ngx_uint_t lower);
 
 
+u_char *ngx_http_v2_string_encode(u_char *dst, u_char *src, size_t len,
+    u_char *tmp, ngx_uint_t lower);
+
+u_char *
+ngx_http_v2_write_int(u_char *pos, ngx_uint_t prefix, ngx_uint_t value);
+
+#define ngx_http_v2_write_name(dst, src, len, tmp)                            \\
+    ngx_http_v2_string_encode(dst, src, len, tmp, 1)
+#define ngx_http_v2_write_value(dst, src, len, tmp)                           \\
+    ngx_http_v2_string_encode(dst, src, len, tmp, 0)
+
+u_char *
+ngx_http_v2_write_header(ngx_http_v2_connection_t *h2c, u_char *pos,
+    u_char *key, size_t key_len, u_char *value, size_t value_len,
+    u_char *tmp);
+
+void
+ngx_http_v2_table_resize(ngx_http_v2_connection_t *h2c);
+
+#define ngx_http_v2_write_header_str(key, value)                        \\
+    ngx_http_v2_write_header(h2c, pos, (u_char *) key, sizeof(key) - 1, \\
+    (u_char *) value, sizeof(value) - 1, tmp);
+
+#define ngx_http_v2_write_header_tbl(key, val)                          \\
+    ngx_http_v2_write_header(h2c, pos, (u_char *) key, sizeof(key) - 1, \\
+    val.data, val.len, tmp);
+
 extern ngx_module_t  ngx_http_v2_module;
diff --git a/src/http/v2/ngx_http_v2_encode.c b/src/http/v2/ngx_http_v2_encode.c
index ac792084..d1fb7217 100644
--- a/src/http/v2/ngx_http_v2_encode.c
+++ b/src/http/v2/ngx_http_v2_encode.c
@@ -10,7 +10,7 @@
 #include <ngx_http.h>
 
 
-static u_char *ngx_http_v2_write_int(u_char *pos, ngx_uint_t prefix,
+u_char *ngx_http_v2_write_int(u_char *pos, ngx_uint_t prefix,
     ngx_uint_t value);
 
 
@@ -40,7 +40,7 @@ ngx_http_v2_string_encode(u_char *dst, u_char *src, size_t len, u_char *tmp,
 }
 
 
-static u_char *
+u_char *
 ngx_http_v2_write_int(u_char *pos, ngx_uint_t prefix, ngx_uint_t value)
 {
     if (value < prefix) {
diff --git a/src/http/v2/ngx_http_v2_filter_module.c b/src/http/v2/ngx_http_v2_filter_module.c
index a6e5e7d4..f4ebe53e 100644
--- a/src/http/v2/ngx_http_v2_filter_module.c
+++ b/src/http/v2/ngx_http_v2_filter_module.c
@@ -155,11 +155,9 @@ ngx_http_v2_header_filter(ngx_http_request_t *r)
 #endif
 
     static size_t nginx_ver_len = ngx_http_v2_literal_size(NGINX_VER);
-    static u_char nginx_ver[ngx_http_v2_literal_size(NGINX_VER)];
 
     static size_t nginx_ver_build_len =
                                   ngx_http_v2_literal_size(NGINX_VER_BUILD);
-    static u_char nginx_ver_build[ngx_http_v2_literal_size(NGINX_VER_BUILD)];
 
     stream = r->stream;
 
@@ -435,7 +433,7 @@ ngx_http_v2_header_filter(ngx_http_request_t *r)
     }
 
     tmp = ngx_palloc(r->pool, tmp_len);
-    pos = ngx_pnalloc(r->pool, len);
+    pos = ngx_pnalloc(r->pool, len + 15 + 1);
 
     if (pos == NULL || tmp == NULL) {
         return NGX_ERROR;
@@ -450,6 +448,18 @@ ngx_http_v2_header_filter(ngx_http_request_t *r)
         h2c->table_update = 0;
     }
 
+    h2c = r->stream->connection;
+
+    if (h2c->indicate_resize) {
+        *pos = 32;
+        pos = ngx_http_v2_write_int(pos, ngx_http_v2_prefix(5),
+                                    h2c->max_hpack_table_size);
+        h2c->indicate_resize = 0;
+#if (NGX_HTTP_V2_HPACK_ENC)
+        ngx_http_v2_table_resize(h2c);
+#endif
+    }
+
     ngx_log_debug1(NGX_LOG_DEBUG_HTTP, fc->log, 0,
                    "http2 output header: \":status: %03ui\"",
                    r->headers_out.status);
@@ -458,67 +468,28 @@ ngx_http_v2_header_filter(ngx_http_request_t *r)
         *pos++ = status;
 
     } else {
-        *pos++ = ngx_http_v2_inc_indexed(NGX_HTTP_V2_STATUS_INDEX);
-        *pos++ = NGX_HTTP_V2_ENCODE_RAW | 3;
-        pos = ngx_sprintf(pos, "%03ui", r->headers_out.status);
+        ngx_sprintf(pos + 8, "%O3ui", r->headers_out.status);
+        pos = ngx_http_v2_write_header(h2c, pos, (u_char *)":status",
+                                       sizeof(":status") - 1, pos + 8, 3, tmp);
     }
 
     if (r->headers_out.server == NULL) {
-
         if (clcf->server_tokens == NGX_HTTP_SERVER_TOKENS_ON) {
-            ngx_log_debug1(NGX_LOG_DEBUG_HTTP, fc->log, 0,
-                           "http2 output header: \"server: %s\"",
-                           NGINX_VER);
+            pos = ngx_http_v2_write_header_str("server", NGINX_VER);
 
         } else if (clcf->server_tokens == NGX_HTTP_SERVER_TOKENS_BUILD) {
-            ngx_log_debug1(NGX_LOG_DEBUG_HTTP, fc->log, 0,
-                           "http2 output header: \"server: %s\"",
-                           NGINX_VER_BUILD);
+            pos = ngx_http_v2_write_header_str("server", NGINX_VER_BUILD);
 
         } else {
-            ngx_log_debug0(NGX_LOG_DEBUG_HTTP, fc->log, 0,
-                           "http2 output header: \"server: nginx\"");
-        }
-
-        *pos++ = ngx_http_v2_inc_indexed(NGX_HTTP_V2_SERVER_INDEX);
-
-        if (clcf->server_tokens == NGX_HTTP_SERVER_TOKENS_ON) {
-            if (nginx_ver[0] == '\0') {
-                p = ngx_http_v2_write_value(nginx_ver, (u_char *) NGINX_VER,
-                                            sizeof(NGINX_VER) - 1, tmp);
-                nginx_ver_len = p - nginx_ver;
-            }
-
-            pos = ngx_cpymem(pos, nginx_ver, nginx_ver_len);
-
-        } else if (clcf->server_tokens == NGX_HTTP_SERVER_TOKENS_BUILD) {
-            if (nginx_ver_build[0] == '\0') {
-                p = ngx_http_v2_write_value(nginx_ver_build,
-                                            (u_char *) NGINX_VER_BUILD,
-                                            sizeof(NGINX_VER_BUILD) - 1, tmp);
-                nginx_ver_build_len = p - nginx_ver_build;
-            }
-
-            pos = ngx_cpymem(pos, nginx_ver_build, nginx_ver_build_len);
-
-        } else {
-            pos = ngx_cpymem(pos, nginx, sizeof(nginx));
+            pos = ngx_http_v2_write_header_str("server", "nginx");
         }
     }
 
     if (r->headers_out.date == NULL) {
-        ngx_log_debug1(NGX_LOG_DEBUG_HTTP, fc->log, 0,
-                       "http2 output header: \"date: %V\"",
-                       &ngx_cached_http_time);
-
-        *pos++ = ngx_http_v2_inc_indexed(NGX_HTTP_V2_DATE_INDEX);
-        pos = ngx_http_v2_write_value(pos, ngx_cached_http_time.data,
-                                      ngx_cached_http_time.len, tmp);
+        pos = ngx_http_v2_write_header_tbl("date", ngx_cached_http_time);
     }
 
     if (r->headers_out.content_type.len) {
-        *pos++ = ngx_http_v2_inc_indexed(NGX_HTTP_V2_CONTENT_TYPE_INDEX);
-
         if (r->headers_out.content_type_len == r->headers_out.content_type.len
             && r->headers_out.charset.len)
         {
@@ -544,64 +515,36 @@ ngx_http_v2_header_filter(ngx_http_request_t *r)
             r->headers_out.content_type.data = p - len;
         }
 
-        ngx_log_debug1(NGX_LOG_DEBUG_HTTP, fc->log, 0,
-                       "http2 output header: \"content-type: %V\"",
-                       &r->headers_out.content_type);
-
-        pos = ngx_http_v2_write_value(pos, r->headers_out.content_type.data,
-                                      r->headers_out.content_type.len, tmp);
+        pos = ngx_http_v2_write_header_tbl("content-type",
+                                           r->headers_out.content_type);
     }
 
     if (r->headers_out.content_length == NULL
         && r->headers_out.content_length_n >= 0)
     {
-        ngx_log_debug1(NGX_LOG_DEBUG_HTTP, fc->log, 0,
-                       "http2 output header: \"content-length: %O\"",
-                       r->headers_out.content_length_n);
-
-        *pos++ = ngx_http_v2_inc_indexed(NGX_HTTP_V2_CONTENT_LENGTH_INDEX);
-
-        p = pos;
-        pos = ngx_sprintf(pos + 1, "%O", r->headers_out.content_length_n);
-        *p = NGX_HTTP_V2_ENCODE_RAW | (u_char) (pos - p - 1);
+        p = ngx_sprintf(pos + 15, "%O", r->headers_out.content_length_n);
+        pos = ngx_http_v2_write_header(h2c, pos, (u_char *)"content-length",
+                                       sizeof("content-length") - 1, pos + 15,
+                                       p - (pos + 15), tmp);
     }
 
     if (r->headers_out.last_modified == NULL
         && r->headers_out.last_modified_time != -1)
     {
-        *pos++ = ngx_http_v2_inc_indexed(NGX_HTTP_V2_LAST_MODIFIED_INDEX);
-
-        ngx_http_time(pos, r->headers_out.last_modified_time);
+        ngx_http_time(pos + 14, r->headers_out.last_modified_time);
         len = sizeof("Wed, 31 Dec 1986 18:00:00 GMT") - 1;
-
-        ngx_log_debug2(NGX_LOG_DEBUG_HTTP, fc->log, 0,
-                       "http2 output header: \"last-modified: %*s\"",
-                       len, pos);
-
-        /*
-         * Date will always be encoded using huffman in the temporary buffer,
-         * so it's safe here to use src and dst pointing to the same address.
-         */
-        pos = ngx_http_v2_write_value(pos, pos, len, tmp);
+        pos = ngx_http_v2_write_header(h2c, pos, (u_char *)"last-modified",
+                                       sizeof("last-modified") - 1, pos + 14,
+                                       len, tmp);
     }
 
     if (r->headers_out.location && r->headers_out.location->value.len) {
-        ngx_log_debug1(NGX_LOG_DEBUG_HTTP, fc->log, 0,
-                       "http2 output header: \"location: %V\"",
-                       &r->headers_out.location->value);
-
-        *pos++ = ngx_http_v2_inc_indexed(NGX_HTTP_V2_LOCATION_INDEX);
-        pos = ngx_http_v2_write_value(pos, r->headers_out.location->value.data,
-                                      r->headers_out.location->value.len, tmp);
+        pos = ngx_http_v2_write_header_tbl("location", r->headers_out.location->value);
     }
 
 #if (NGX_HTTP_GZIP)
     if (r->gzip_vary) {
-        ngx_log_debug0(NGX_LOG_DEBUG_HTTP, fc->log, 0,
-                       "http2 output header: \"vary: Accept-Encoding\"");
-
-        *pos++ = ngx_http_v2_inc_indexed(NGX_HTTP_V2_VARY_INDEX);
-        pos = ngx_cpymem(pos, accept_encoding, sizeof(accept_encoding));
+        pos = ngx_http_v2_write_header_str("vary", "Accept-Encoding");
     }
 #endif
 
@@ -624,23 +567,9 @@ ngx_http_v2_header_filter(ngx_http_request_t *r)
             continue;
         }
 
-#if (NGX_DEBUG)
-        if (fc->log->log_level & NGX_LOG_DEBUG_HTTP) {
-            ngx_strlow(tmp, header[i].key.data, header[i].key.len);
-
-            ngx_log_debug3(NGX_LOG_DEBUG_HTTP, fc->log, 0,
-                           "http2 output header: \"%*s: %V\"",
-                           header[i].key.len, tmp, &header[i].value);
-        }
-#endif
-
-        *pos++ = 0;
-
-        pos = ngx_http_v2_write_name(pos, header[i].key.data,
-                                     header[i].key.len, tmp);
-
-        pos = ngx_http_v2_write_value(pos, header[i].value.data,
-                                      header[i].value.len, tmp);
+        pos = ngx_http_v2_write_header(h2c, pos, header[i].key.data,
+                                       header[i].key.len, header[i].value.data,
+                                       header[i].value.len, tmp);
     }
 
     fin = r->header_only
@@ -1308,6 +1237,7 @@ ngx_http_v2_create_trailers_frame(ngx_http_request_t *r)
     ngx_list_part_t   *part;
     ngx_table_elt_t   *header;
     ngx_connection_t  *fc;
+    ngx_http_v2_connection_t  *h2c;
 
     fc = r->connection;
     len = 0;
@@ -1316,6 +1246,8 @@ ngx_http_v2_create_trailers_frame(ngx_http_request_t *r)
     part = &r->headers_out.trailers.part;
     header = part->elts;
 
+    h2c = r->stream->connection;
+
     for (i = 0; /* void */; i++) {
 
         if (i >= part->nelts) {
@@ -1400,13 +1332,9 @@ ngx_http_v2_create_trailers_frame(ngx_http_request_t *r)
         }
 #endif
 
-        *pos++ = 0;
-
-        pos = ngx_http_v2_write_name(pos, header[i].key.data,
-                                     header[i].key.len, tmp);
-
-        pos = ngx_http_v2_write_value(pos, header[i].value.data,
-                                      header[i].value.len, tmp);
+        pos = ngx_http_v2_write_header(h2c, pos, header[i].key.data,
+                                       header[i].key.len, header[i].value.data,
+                                       header[i].value.len, tmp);
     }
 
     return ngx_http_v2_create_headers_frame(r, start, pos, 1);
diff --git a/src/http/v2/ngx_http_v2_table.c b/src/http/v2/ngx_http_v2_table.c
index 7d49803f..b9ee2048 100644
--- a/src/http/v2/ngx_http_v2_table.c
+++ b/src/http/v2/ngx_http_v2_table.c
@@ -361,3 +361,434 @@ ngx_http_v2_table_size(ngx_http_v2_connection_t *h2c, size_t size)
 
     return NGX_OK;
 }
+
+
+#if (NGX_HTTP_V2_HPACK_ENC)
+
+static ngx_int_t
+hpack_get_static_index(ngx_http_v2_connection_t *h2c, u_char *val, size_t len);
+
+static ngx_int_t
+hpack_get_dynamic_index(ngx_http_v2_connection_t *h2c, uint64_t key_hash,
+                        uint8_t *key, size_t key_len);
+
+
+void
+ngx_http_v2_table_resize(ngx_http_v2_connection_t *h2c)
+{
+    ngx_http_v2_hpack_enc_entry_t  *table;
+    uint64_t                        idx;
+
+    table = h2c->hpack_enc.htable;
+
+    while (h2c->hpack_enc.size > h2c->max_hpack_table_size) {
+        idx = h2c->hpack_enc.base;
+        h2c->hpack_enc.base = table[idx].next;
+        h2c->hpack_enc.size -= table[idx].size;
+        table[idx].hash_val = 0;
+        h2c->hpack_enc.n_elems--;
+    }
+}
+
+
+/* checks if a header is in the hpack table - if so returns the table entry,
+   otherwise encodes and inserts into the table and returns 0,
+   if failed to insert into table, returns -1 */
+static ngx_int_t
+ngx_http_v2_table_encode_strings(ngx_http_v2_connection_t *h2c,
+    size_t key_len, size_t val_len, uint8_t *key, uint8_t *val,
+    ngx_int_t *header_idx)
+{
+    uint64_t  hash_val, key_hash, idx, lru;
+    int       i;
+    size_t    size = key_len + val_len + 32;
+    uint8_t  *storage = h2c->hpack_enc.storage;
+
+    ngx_http_v2_hpack_enc_entry_t   *table;
+    ngx_http_v2_hpack_name_entry_t  *name;
+
+    *header_idx = NGX_ERROR;
+    /* step 1: compute the hash value of header */
+    if (size > HPACK_ENC_MAX_ENTRY || size > h2c->max_hpack_table_size) {
+        return NGX_ERROR;
+    }
+
+    key_hash = ngx_murmur_hash2_64(key, key_len, 0x01234);
+    hash_val = ngx_murmur_hash2_64(val, val_len, key_hash);
+
+    if (hash_val == 0) {
+        return NGX_ERROR;
+    }
+
+    /* step 2: check if full header in the table */
+    idx = hash_val;
+    i = -1;
+    while (idx) {
+         /* at most 8 locations are checked, but most will be done in 1 or 2 */
+        table = &h2c->hpack_enc.htable[idx % HPACK_ENC_HTABLE_SZ];
+        if (table->hash_val == hash_val
+            && table->klen == key_len
+            && table->vlen == val_len
+            && ngx_memcmp(key, storage + table->pos, key_len) == 0
+            && ngx_memcmp(val, storage + table->pos + key_len, val_len) == 0)
+        {
+            return (h2c->hpack_enc.top - table->index) + 61;
+        }
+
+        if (table->hash_val == 0 && i == -1) {
+            i = idx % HPACK_ENC_HTABLE_SZ;
+            break;
+        }
+
+        idx >>= 8;
+    }
+
+    /* step 3: check if key is in one of the tables */
+    *header_idx = hpack_get_static_index(h2c, key, key_len);
+
+    if (i == -1) {
+        return NGX_ERROR;
+    }
+
+    if (*header_idx == NGX_ERROR) {
+        *header_idx = hpack_get_dynamic_index(h2c, key_hash, key, key_len);
+    }
+
+    /* step 4: store the new entry */
+    table =  h2c->hpack_enc.htable;
+
+    if (h2c->hpack_enc.top == 0xffffffff) {
+        /* just to be on the safe side, avoid overflow */
+        ngx_memset(&h2c->hpack_enc, 0, sizeof(ngx_http_v2_hpack_enc_t));
+    }
+
+    while ((h2c->hpack_enc.size + size > h2c->max_hpack_table_size)
+           || h2c->hpack_enc.n_elems == HPACK_ENC_HTABLE_ENTRIES) {
+        /* make space for the new entry first */
+        idx = h2c->hpack_enc.base;
+        h2c->hpack_enc.base = table[idx].next;
+        h2c->hpack_enc.size -= table[idx].size;
+        table[idx].hash_val = 0;
+        h2c->hpack_enc.n_elems--;
+    }
+
+    table[i] = (ngx_http_v2_hpack_enc_entry_t){.hash_val = hash_val,
+                                               .index = h2c->hpack_enc.top,
+                                               .pos = h2c->hpack_enc.pos,
+                                               .klen = key_len,
+                                               .vlen = val_len,
+                                               .size = size,
+                                               .next = 0};
+
+    table[h2c->hpack_enc.last].next = i;
+    if (h2c->hpack_enc.n_elems == 0) {
+        h2c->hpack_enc.base = i;
+    }
+
+    h2c->hpack_enc.last = i;
+    h2c->hpack_enc.top++;
+    h2c->hpack_enc.size += size;
+    h2c->hpack_enc.n_elems++;
+
+    /* update header name lookup */
+    if (*header_idx == NGX_ERROR ) {
+        lru = h2c->hpack_enc.top;
+
+        for (i=0; i<HPACK_ENC_DYNAMIC_KEY_TBL_SZ; i++) {
+
+            name = &h2c->hpack_enc.heads[i];
+
+            if ( name->hash_val == 0 || (name->hash_val == key_hash
+                && ngx_memcmp(storage + name->pos, key, key_len) == 0) )
+            {
+                name->hash_val = key_hash;
+                name->pos = h2c->hpack_enc.pos;
+                name->index = h2c->hpack_enc.top - 1;
+                break;
+            }
+
+            if (lru > name->index) {
+                lru = name->index;
+                idx = i;
+            }
+        }
+
+        if (i == HPACK_ENC_DYNAMIC_KEY_TBL_SZ) {
+            name = &h2c->hpack_enc.heads[idx];
+            name->hash_val = hash_val;
+            name->pos = h2c->hpack_enc.pos;
+            name->index = h2c->hpack_enc.top - 1;
+        }
+    }
+
+    ngx_memcpy(storage + h2c->hpack_enc.pos, key, key_len);
+    ngx_memcpy(storage + h2c->hpack_enc.pos + key_len, val, val_len);
+
+    h2c->hpack_enc.pos += size;
+    if (h2c->hpack_enc.pos > NGX_HTTP_V2_MAX_HPACK_TABLE_SIZE) {
+        h2c->hpack_enc.pos = 0;
+    }
+
+    return NGX_OK;
+}
+
+
+u_char *
+ngx_http_v2_write_header(ngx_http_v2_connection_t *h2c, u_char *pos,
+                         u_char *key, size_t key_len,
+                         u_char *value, size_t value_len,
+                         u_char *tmp)
+{
+    ngx_int_t idx, header_idx;
+
+    ngx_log_debug4(NGX_LOG_DEBUG_HTTP, h2c->connection->log, 0,
+                   "http2 output header: %*s: %*s", key_len, key, value_len,
+                   value);
+
+    /* attempt to find the value in the dynamic table */
+    idx = ngx_http_v2_table_encode_strings(h2c, key_len, value_len, key, value,
+                                           &header_idx);
+
+    if (idx > 0) {
+        /* positive index indicates success */
+        ngx_log_debug1(NGX_LOG_DEBUG_HTTP, h2c->connection->log, 0,
+                       "http2 hpack encode: Indexed Header Field: %ud", idx);
+
+        *pos = 128;
+        pos = ngx_http_v2_write_int(pos, ngx_http_v2_prefix(7), idx);
+
+    } else {
+
+        if (header_idx == NGX_ERROR) { /* if key is not present */
+
+            if (idx == NGX_ERROR) {    /* if header was not added */
+                *pos++ = 0;
+
+                ngx_log_debug0(NGX_LOG_DEBUG_HTTP, h2c->connection->log, 0,
+                              "http2 hpack encode: Literal Header Field without"
+                              " Indexing — New Name");
+            } else {                   /* if header was added */
+                *pos++ = 64;
+
+                ngx_log_debug0(NGX_LOG_DEBUG_HTTP, h2c->connection->log, 0,
+                              "http2 hpack encode: Literal Header Field with "
+                              "Incremental Indexing — New Name");
+            }
+
+            pos = ngx_http_v2_write_name(pos, key, key_len, tmp);
+
+        } else {                       /* if key is present */
+
+            if (idx == NGX_ERROR) {
+                *pos = 0;
+                pos = ngx_http_v2_write_int(pos, ngx_http_v2_prefix(4), header_idx);
+
+                ngx_log_debug1(NGX_LOG_DEBUG_HTTP, h2c->connection->log, 0,
+                              "http2 hpack encode: Literal Header Field without"
+                              " Indexing — Indexed Name: %ud", header_idx);
+            } else {
+                *pos = 64;
+                pos = ngx_http_v2_write_int(pos, ngx_http_v2_prefix(6), header_idx);
+
+                ngx_log_debug1(NGX_LOG_DEBUG_HTTP, h2c->connection->log, 0,
+                              "http2 hpack encode: Literal Header Field with "
+                              "Incremental Indexing — Indexed Name: %ud", header_idx);
+            }
+        }
+
+        pos = ngx_http_v2_write_value(pos, value, value_len, tmp);
+    }
+
+    return pos;
+}
+
+
+static ngx_int_t
+hpack_get_dynamic_index(ngx_http_v2_connection_t *h2c, uint64_t key_hash,
+                        uint8_t *key, size_t key_len)
+{
+    ngx_http_v2_hpack_name_entry_t  *name;
+    int                              i;
+
+    for (i=0; i<HPACK_ENC_DYNAMIC_KEY_TBL_SZ; i++) {
+        name = &h2c->hpack_enc.heads[i];
+
+        if (name->hash_val == key_hash
+            && ngx_memcmp(h2c->hpack_enc.storage + name->pos, key, key_len) == 0)
+        {
+            if (name->index >= h2c->hpack_enc.top - h2c->hpack_enc.n_elems) {
+                return (h2c->hpack_enc.top - name->index) + 61;
+            }
+            break;
+        }
+    }
+
+    return NGX_ERROR;
+}
+
+
+/* decide if a given header is present in the static dictionary, this could be
+   done in several ways, but it seems the fastest one is "exhaustive" search */
+static ngx_int_t
+hpack_get_static_index(ngx_http_v2_connection_t *h2c, u_char *val, size_t len)
+{
+    /* the static dictionary of response only headers,
+       although response headers can be put by origin,
+       that would be rare */
+    static const struct {
+        u_char         len;
+        const u_char   val[28];
+        u_char         idx;
+    } server_headers[] = {
+        { 3, "age",                         21},//0
+        { 3, "via",                         60},
+        { 4, "date",                        33},//2
+        { 4, "etag",                        34},
+        { 4, "link",                        45},
+        { 4, "vary",                        59},
+        { 5, "allow",                       22},//6
+        { 6, "server",                      54},//7
+        { 7, "expires",                     36},//8
+        { 7, "refresh",                     52},
+        { 8, "location",                    46},//10
+        {10, "set-cookie",                  55},//11
+        {11, "retry-after",                 53},//12
+        {12, "content-type",                31},//13
+        {13, "content-range",               30},//14
+        {13, "accept-ranges",               18},
+        {13, "cache-control",               24},
+        {13, "last-modified",               44},
+        {14, "content-length",              28},//18
+        {16, "content-encoding",            26},//19
+        {16, "content-language",            27},
+        {16, "content-location",            29},
+        {16, "www-authenticate",            61},
+        {17, "transfer-encoding",           57},//23
+        {18, "proxy-authenticate",          48},//24
+        {19, "content-disposition",         25},//25
+        {25, "strict-transport-security",   56},//26
+        {27, "access-control-allow-origin", 20},//27
+        {99, "",                            99},
+    }, *header;
+
+    /* for a given length, where to start the search
+       since minimal length is 3, the table has a -3
+       offset */
+    static const int8_t start_at[] = {
+        [3-3]  = 0,
+        [4-3]  = 2,
+        [5-3]  = 6,
+        [6-3]  = 7,
+        [7-3]  = 8,
+        [8-3]  = 10,
+        [9-3]  = -1,
+        [10-3] = 11,
+        [11-3] = 12,
+        [12-3] = 13,
+        [13-3] = 14,
+        [14-3] = 18,
+        [15-3] = -1,
+        [16-3] = 19,
+        [17-3] = 23,
+        [18-3] = 24,
+        [19-3] = 25,
+        [20-3] = -1,
+        [21-3] = -1,
+        [22-3] = -1,
+        [23-3] = -1,
+        [24-3] = -1,
+        [25-3] = 26,
+        [26-3] = -1,
+        [27-3] = 27,
+    };
+
+    uint64_t pref;
+    size_t   save_len = len, i;
+    int8_t   start;
+
+    /* early exit for out of bounds lengths */
+    if (len < 3 || len > 27) {
+        return NGX_ERROR;
+    }
+
+    start = start_at[len - 3];
+    if (start == -1) {
+        /* exit for non existent lengths */
+        return NGX_ERROR;
+    }
+
+    header = &server_headers[start_at[len - 3]];
+
+    /* load first 8 bytes of key, for fast comparison */
+    if (len < 8) {
+        pref = 0;
+        if (len >= 4) {
+            pref = *(uint32_t *)(val + len - 4) | 0x20202020;
+            len -= 4;
+        }
+        while (len > 0) { /* 3 iterations at most */
+            pref = (pref << 8) ^ (val[len - 1] | 0x20);
+            len--;
+        }
+    } else {
+        pref = *(uint64_t *)val | 0x2020202020202020;
+        len -= 8;
+    }
+
+    /* iterate over headers with the right length */
+    while (header->len == save_len) {
+        /* quickly compare the first 8 bytes, most tests will end here */
+        if (pref != *(uint64_t *) header->val) {
+            header++;
+            continue;
+        }
+
+        if (len == 0) {
+            /* len == 0, indicates prefix held the entire key */
+            return header->idx;
+        }
+        /* for longer keys compare the rest */
+        i = 1 + (save_len + 7) % 8; /* align so we can compare in quadwords */
+
+        while (i + 8 <= save_len) { /* 3 iterations at most */
+            if ( *(uint64_t *)&header->val[i]
+                 != (*(uint64_t *) &val[i]| 0x2020202020202020) )
+            {
+                header++;
+                i = 0;
+                break;
+            }
+            i += 8;
+        }
+
+        if (i == 0) {
+            continue;
+        }
+
+        /* found the corresponding entry in the static dictionary */
+        return header->idx;
+    }
+
+    return NGX_ERROR;
+}
+
+#else
+
+u_char *
+ngx_http_v2_write_header(ngx_http_v2_connection_t *h2c, u_char *pos,
+                         u_char *key, size_t key_len,
+                         u_char *value, size_t value_len,
+                         u_char *tmp)
+{
+    ngx_log_debug4(NGX_LOG_DEBUG_HTTP, h2c->connection->log, 0,
+                   "http2 output header: %*s: %*s", key_len, key, value_len,
+                   value);
+
+    *pos++ = 64;
+    pos = ngx_http_v2_write_name(pos, key, key_len, tmp);
+    pos = ngx_http_v2_write_value(pos, value, value_len, tmp);
+
+    return pos;
+}
+
+#endif
EOF

./auto/configure --prefix=/usr/share/nginx --sbin-path=/usr/sbin/nginx --conf-path=/etc/nginx/nginx.conf --http-log-path=/var/log/nginx/access.log --error-log-path=/var/log/nginx/error.log --lock-path=/var/lock/nginx.lock --pid-path=/run/nginx.pid --http-client-body-temp-path=/tmp/body --http-fastcgi-temp-path=/tmp/fastcgi --http-proxy-temp-path=/tmp/proxy --with-threads --with-file-aio --with-pcre-jit --with-http_ssl_module --with-http_v2_module --with-http_v2_hpack_enc --with-http_v3_module --with-http_gzip_static_module --without-http_ssi_module --without-http_userid_module --without-http_mirror_module --without-http_geo_module --without-http_split_clients_module --without-http_uwsgi_module --without-http_scgi_module --without-http_grpc_module --without-http_memcached_module --without-http_empty_gif_module --without-http_browser_module --without-http_upstream_hash_module --without-http_upstream_ip_hash_module --without-http_upstream_least_conn_module --without-http_upstream_keepalive_module --without-http_upstream_zone_module --with-stream --with-stream_ssl_module --without-stream_geo_module --without-stream_map_module --without-stream_split_clients_module --without-stream_return_module --without-stream_upstream_hash_module --without-stream_upstream_least_conn_module --without-stream_upstream_zone_module --with-cc-opt='-O3 -march=native -mtune=native -fstack-protector-strong -Wformat -Werror=format-security -fPIC -Wdate-time -D_FORTIFY_SOURCE=2' --with-ld-opt='-Wl,-z,relro -Wl,-z,now -fPIC' --add-module=ngx_brotli --without-pcre2
make -j $PROC_LIMIT install
make clean
git reset --hard
cd ..
ln -fs /usr/include/qdbm/depot.h /usr/include/depot.h
cd php-src
cd ext
cd apcu && git fetch --all && git checkout v5.1.23 && cd ..
cd php-ext-brotli && git fetch --all && git checkout 0.13.1 && cd ..
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
cd igbinary && git fetch --all && git checkout 3.2.15 && cd ..
cd msgpack-php && git fetch --all && git checkout msgpack-2.2.0 && cd ..
rm -rf ssh2-*
curl -sSf https://pecl.php.net/get/ssh2 | tar xzvf - --exclude package.xml
cd ..
git fetch --all
git fetch --all --tags
git checkout php-8.3.9
./buildconf -f
LIBS='-lgpg-error' CXXFLAGS='-O3 -mtune=native -march=native' CFLAGS='-O3 -mtune=native -march=native' ./configure -C --enable-re2c-cgoto --prefix=/usr --with-config-file-scan-dir=/etc/php/8.3/fpm/conf.d --libdir=/usr/lib/php --libexecdir=/usr/lib/php --datadir=/usr/share/php/8.3 --program-suffix=8.3 --sysconfdir=/etc --localstatedir=/var --mandir=/usr/share/man --enable-fpm --enable-cli --disable-cgi --disable-phpdbg --with-fpm-systemd --with-fpm-user=www-data --with-fpm-group=www-data --with-layout=GNU --disable-dtrace --disable-short-tags --without-valgrind --disable-shared --disable-debug --disable-rpath --without-pear --with-openssl --enable-bcmath --with-bz2 --enable-calendar --with-curl --enable-dba --with-qdbm --with-lmdb --enable-exif --enable-ftp --enable-gd --with-external-gd --with-jpeg --with-webp --with-xpm --with-freetype --enable-gd-jis-conv --with-gettext --with-gmp --with-mhash --with-imap --with-imap-ssl --with-kerberos --enable-intl --with-ldap --with-ldap-sasl --enable-mbstring --with-mysqli --with-pdo-mysql --enable-mysqlnd --with-mysql-sock=/var/run/mysqld/mysqld.sock --with-zlib --with-libedit --with-readline --enable-shmop --enable-soap --enable-sockets --with-sodium --with-password-argon2 --with-tidy --with-xsl --with-enchant --with-pspell --with-zip --with-ffi --enable-apcu --enable-brotli --with-libbrotli --with-imagick --with-ssh2 --with-gnupg --enable-rar --enable-igbinary --with-msgpack --enable-sysvsem --enable-sysvmsg --enable-sysvshm
make -j $PROC_LIMIT install
make distclean
git reset --hard
git checkout php-8.2.21
./buildconf -f
LIBS='-lgpg-error' CXXFLAGS='-O3 -mtune=native -march=native' CFLAGS='-O3 -mtune=native -march=native' ./configure -C --enable-re2c-cgoto --prefix=/usr --with-config-file-scan-dir=/etc/php/8.2/fpm/conf.d --libdir=/usr/lib/php --libexecdir=/usr/lib/php --datadir=/usr/share/php/8.2 --program-suffix=8.2 --sysconfdir=/etc --localstatedir=/var --mandir=/usr/share/man --enable-fpm --enable-cli --disable-cgi --disable-phpdbg --with-fpm-systemd --with-fpm-user=www-data --with-fpm-group=www-data --with-layout=GNU --disable-dtrace --disable-short-tags --without-valgrind --disable-shared --disable-debug --disable-rpath --without-pear --with-openssl --enable-bcmath --with-bz2 --enable-calendar --with-curl --enable-dba --with-qdbm --with-lmdb --enable-exif --enable-ftp --enable-gd --with-external-gd --with-jpeg --with-webp --with-xpm --with-freetype --enable-gd-jis-conv --with-gettext --with-gmp --with-mhash --with-imap --with-imap-ssl --with-kerberos --enable-intl --with-ldap --with-ldap-sasl --enable-mbstring --with-mysqli --with-pdo-mysql --enable-mysqlnd --with-mysql-sock=/var/run/mysqld/mysqld.sock --with-zlib --with-libedit --with-readline --enable-shmop --enable-soap --enable-sockets --with-sodium --with-password-argon2 --with-tidy --with-xsl --with-enchant --with-pspell --with-zip --with-ffi --enable-apcu --enable-brotli --with-libbrotli --with-imagick --with-ssh2 --with-gnupg --enable-rar --enable-igbinary --with-msgpack --enable-sysvsem --enable-sysvmsg --enable-sysvshm
make -j $PROC_LIMIT install
make distclean
git reset --hard
git checkout php-8.1.29
./buildconf -f
LIBS='-lgpg-error' CXXFLAGS='-O3 -mtune=native -march=native' CFLAGS='-O3 -mtune=native -march=native' ./configure -C --enable-re2c-cgoto --prefix=/usr --with-config-file-scan-dir=/etc/php/8.1/fpm/conf.d --libdir=/usr/lib/php --libexecdir=/usr/lib/php --datadir=/usr/share/php/8.1 --program-suffix=8.1 --sysconfdir=/etc --localstatedir=/var --mandir=/usr/share/man --enable-fpm --enable-cli --disable-cgi --disable-phpdbg --with-fpm-systemd --with-fpm-user=www-data --with-fpm-group=www-data --with-layout=GNU --disable-dtrace --disable-short-tags --without-valgrind --disable-shared --disable-debug --disable-rpath --without-pear --with-openssl --enable-bcmath --with-bz2 --enable-calendar --with-curl --enable-dba --with-qdbm --with-lmdb --enable-exif --enable-ftp --enable-gd --with-external-gd --with-jpeg --with-webp --with-xpm --with-freetype --enable-gd-jis-conv --with-gettext --with-gmp --with-mhash --with-imap --with-imap-ssl --with-kerberos --enable-intl --with-ldap --with-ldap-sasl --enable-mbstring --with-mysqli --with-pdo-mysql --enable-mysqlnd --with-mysql-sock=/var/run/mysqld/mysqld.sock --with-zlib --with-libedit --with-readline --enable-shmop --enable-soap --enable-sockets --with-sodium --with-password-argon2 --with-tidy --with-xsl --with-enchant --with-pspell --with-zip --with-ffi --enable-apcu --enable-brotli --with-libbrotli --with-imagick --with-ssh2 --with-gnupg --enable-rar --enable-igbinary --with-msgpack --enable-sysvsem --enable-sysvmsg --enable-sysvshm
make -j $PROC_LIMIT install
make distclean
git reset --hard
ln -fs /usr/bin/php8.3 /usr/bin/php
cd ..
ldconfig

# install composer
curl -sSL https://github.com/composer/composer/releases/download/2.7.7/composer.phar > /usr/bin/composer
chmod +x /usr/bin/composer
composer self-update

#rspamd user
id -u _rspamd >/dev/null 2>&1 ||useradd -M -r -s /bin/false -d /var/lib/rspamd _rspamd
mkdir -p /var/lib/rspamd
chown _rspamd: /var/lib/rspamd

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
