#!/bin/bash

export PATH="/usr/local/bin:/usr/bin:/bin:/usr/local/sbin:/usr/sbin:/sbin"

test "$1" != "" || (echo "Need path to chroot directory" && exit 1)

ALL_LIB_DIRECTORIES=()
ALL_LIB_FILES=()

### functions
function CHROOT_BINARY() {
    BINARY="$1"
    if [ "$(echo $BINARY | grep -E '.*:.*')" != "" ]; then
        BINARY="$(echo $BINARY | cut -d':' -f2)"
    fi
    LIB_FILES="$(ldd $BINARY 2>&1 | grep -v 'not a dynamic executable' | awk '{ print $3 }' | grep -E '^/(.*)' || echo)"
    LDD_FILES="$(ldd $BINARY 2>&1 | grep -v 'not a dynamic executable' | grep 'ld-linux' | awk '{ print $1; }' || echo)"
    if [ "$LIB_FILES" != "" ]; then
        for LIB_FILE in $LIB_FILES; do
            LIB_DIRECTORY="$(dirname $LIB_FILE)"
            if [[ ! "${ALL_LIB_DIRECTORIES[@]}" =~ "$LIB_DIRECTORY" ]]; then
                ALL_LIB_DIRECTORIES=(${ALL_LIB_DIRECTORIES[@]} "$LIB_DIRECTORY")
            fi
            if [[ ! "${ALL_LIB_FILES[@]}" =~ "$LIB_FILE" ]]; then
                ALL_LIB_FILES=(${ALL_LIB_FILES[@]} "$LIB_FILE")
            fi
        done
    fi
    if [ "$LDD_FILES" != "" ]; then
        for LDD_FILE in $LDD_FILES; do
            LDD_DIRECTORY="$(dirname $LDD_FILE)"
            if [[ ! "${ALL_LIB_DIRECTORIES[@]}" =~ "$LDD_DIRECTORY" ]]; then
                ALL_LIB_DIRECTORIES=(${ALL_LIB_DIRECTORIES[@]} "$LDD_DIRECTORY")
            fi
            if [[ ! "${ALL_LIB_FILES[@]}" =~ "$LDD_DIRECTORY" ]]; then
                ALL_LIB_FILES=(${ALL_LIB_FILES[@]} "$LDD_FILE")
            fi
        done
    fi
    BINARY_DIRECTORY="$(dirname $BINARY)"
    mkdir -pm 0555 $CHROOT_DIRECTORY$BINARY_DIRECTORY
    cp $BINARY $CHROOT_DIRECTORY$BINARY
    chmod 0555 $CHROOT_DIRECTORY$BINARY
}

function CHROOT_LIBRARIES() {
    for DIRECTORY in ${ALL_LIB_DIRECTORIES[@]}; do
        mkdir -pm 0555 $CHROOT_DIRECTORY$DIRECTORY
    done
    for FILE in ${ALL_LIB_FILES[@]}; do
        cp $FILE $CHROOT_DIRECTORY$FILE
        chmod 0555 $CHROOT_DIRECTORY$FILE
    done
}

### variables
CHROOT_DIRECTORY=$1
CHROOT_DIRECTORY_STRUCTURE=(
    '/bin'
    '/etc'
    '/etc/default'
    '/dev'
    '/lib'
    '/tmp'
    '/usr'
    '/usr/share'
    '/usr/share/bash-completion'
    '/usr/share/bash-completion/completions'
    '/usr/bin'
    '/usr/lib'
    '/usr/lib/openssh'
    '/usr/sbin'
    '/var'
    '/var/run'
    '/var/run/mysqld'
)
CHROOT_DIRECTORY_TO_CLEAN=(
    '/bin'
    '/lib'
    '/usr/bin'
    '/usr/lib'
    '/usr/sbin'
)
BINARIES_GENERAL=(
    '/bin/bash'
    '/bin/bzip2'
    '/bin/cat'
    '/bin/chmod'
    '/bin/cp'
    '/bin/date'
    '/bin/echo'
    '/bin/egrep'
    '/bin/false'
    '/bin/fgrep'
    '/bin/grep'
    '/bin/gunzip'
    '/bin/gzip'
    '/bin/ln'
    '/bin/ls'
    '/bin/mkdir'
    '/bin/mktemp'
    '/bin/mv'
    '/bin/nano'
    '/bin/pwd'
    '/bin/readlink'
    '/bin/rm'
    '/bin/rmdir'
    '/bin/sed'
    '/bin/sh'
    '/bin/sleep'
    '/bin/tar'
    '/bin/touch'
    '/bin/true'
    '/bin/uname'
    '/bin/vdir'
    '/bin/which'
    '/usr/bin/['
    '/usr/bin/awk'
    '/usr/bin/base32'
    '/usr/bin/base64'
    '/usr/bin/basename'
    '/usr/bin/basenc'
    '/usr/bin/brotli'
    '/usr/bin/clear'
    '/usr/bin/composer'
    '/usr/bin/cksum'
    '/usr/bin/comm'
    '/usr/bin/csplit'
    '/usr/bin/curl'
    '/usr/bin/cut'
    '/usr/bin/dirname'
    '/usr/bin/dd'
    '/usr/bin/dir'
    '/usr/bin/du'
    '/usr/bin/env'
    '/usr/bin/expand'
    '/usr/bin/expr'
    '/usr/bin/factor'
    '/usr/bin/find'
    '/usr/bin/fmt'
    '/usr/bin/fold'
    '/usr/bin/git'
    '/usr/bin/git-receive-pack'
    '/usr/bin/git-shell'
    '/usr/bin/git-upload-archive'
    '/usr/bin/git-upload-pack'
    '/usr/bin/gpg'
    '/usr/bin/head'
    '/usr/bin/id'
    '/usr/bin/install'
    '/usr/bin/join'
    '/usr/bin/less'
    '/usr/bin/link'
    '/usr/bin/md5sum'
    '/usr/bin/mysql'
    '/usr/bin/mysqldump'
    '/usr/bin/mysqlcheck'
    '/usr/bin/nl'
    '/usr/bin/nohup'
    '/usr/bin/numfmt'
    '/usr/bin/od'
    '/usr/bin/openssl'
    '/usr/bin/paste'
    '/usr/bin/php7.4'
    '/usr/bin/php8.0'
    '/usr/bin/pr'
    '/usr/bin/printenv'
    '/usr/bin/printf'
    '/usr/bin/ptx'
    '/usr/bin/realpath'
    '/usr/bin/rsync'
    '/usr/bin/scp'
    '/usr/bin/seq'
    '/usr/bin/sftp'
    '/usr/bin/sha1sum'
    '/usr/bin/sha224sum'
    '/usr/bin/sha256sum'
    '/usr/bin/sha384sum'
    '/usr/bin/sha512sum'
    '/usr/bin/shred'
    '/usr/bin/shuf'
    '/usr/bin/sort'
    '/usr/bin/split'
    '/usr/bin/ssh'
    '/usr/bin/stat'
    '/usr/bin/stdbuf'
    '/usr/bin/sum'
    '/usr/bin/tac'
    '/usr/bin/tail'
    '/usr/bin/test'
    '/usr/bin/tee'
    '/usr/bin/timeout'
    '/usr/bin/tput'
    '/usr/bin/tr'
    '/usr/bin/truncate'
    '/usr/bin/tsort'
    '/usr/bin/unexpand'
    '/usr/bin/uniq'
    '/usr/bin/unlink'
    '/usr/bin/unxz'
    '/usr/bin/unzip'
    '/usr/bin/vi'
    '/usr/bin/vim'
    '/usr/bin/wc'
    '/usr/bin/wget'
    '/usr/bin/xargs'
    '/usr/bin/xz'
    '/usr/bin/zip'
    '/usr/bin/zopfli'
    '/usr/sbin/nologin'
)
FILES_GENERAL=(
    '/etc/hosts'
    '/etc/hostname'
    '/etc/resolv.conf'
    '/etc/nsswitch.conf'
    '/etc/services'
    '/etc/protocols'
    '/etc/locale.alias'
    '/etc/default/locale'
    '/etc/localtime'
    '/etc/profile'
    '/etc/bash_completion'
    '/etc/bash.bashrc'
    '/usr/share/bash-completion/bash_completion'
    '/usr/share/bash-completion/completions/alias'
    '/usr/share/bash-completion/completions/bind'
    '/usr/share/bash-completion/completions/bzip2'
    '/usr/share/bash-completion/completions/compgen'
    '/usr/share/bash-completion/completions/complete'
    '/usr/share/bash-completion/completions/curl'
    '/usr/share/bash-completion/completions/declare'
    '/usr/share/bash-completion/completions/export'
    '/usr/share/bash-completion/completions/find'
    '/usr/share/bash-completion/completions/function'
    '/usr/share/bash-completion/completions/git'
    '/usr/share/bash-completion/completions/gzip'
    '/usr/share/bash-completion/completions/id'
    '/usr/share/bash-completion/completions/kill'
    '/usr/share/bash-completion/completions/mysql'
    '/usr/share/bash-completion/completions/openssl'
    '/usr/share/bash-completion/completions/pwd'
    '/usr/share/bash-completion/completions/rsync'
    '/usr/share/bash-completion/completions/scp'
    '/usr/share/bash-completion/completions/sh'
    '/usr/share/bash-completion/completions/sftp'
    '/usr/share/bash-completion/completions/tar'
    '/usr/share/bash-completion/completions/typeset'
    '/usr/share/bash-completion/completions/wget'
)
DIRECTORIES_GENERAL=(
    '/usr/lib/git-core'
    '/usr/share/git-core'
    '/usr/lib/locale'
    '/usr/share/i18n'
    '/etc/ssl'
    '/usr/lib/ssl'
    '/usr/share/ca-certificates'
    '/etc/bash_completion.d'
    '/usr/share/zoneinfo'
    '/lib/terminfo'
    '/usr/share/terminfo'
    '/usr/lib/php'
    '/etc/profile.d'
)
### test variables/parameters
test "$CHROOT_DIRECTORY" != ""

if [ "$2" != "" ]; then
    CHROOT_BINARY $2
    CHROOT_LIBRARIES
    echo "copied extra binary $2";
    exit 0;
fi

### init chroot directory
mkdir -p $CHROOT_DIRECTORY
chown root:www-data $CHROOT_DIRECTORY
chmod 550 $CHROOT_DIRECTORY
for DIRECTORY in ${CHROOT_DIRECTORY_TO_CLEAN[@]}; do
    rm -rf $CHROOT_DIRECTORY$DIRECTORY
done
for DIRECTORY in ${CHROOT_DIRECTORY_STRUCTURE[@]}; do
    mkdir -pm 0555 $CHROOT_DIRECTORY$DIRECTORY
done
chmod 777 $CHROOT_DIRECTORY/tmp
# users and groups
echo "root:x:0:0:root:/root:/bin/bash" > $CHROOT_DIRECTORY/etc/passwd
echo "www-data:x:33:33::/var/www:/bin/bash" >> $CHROOT_DIRECTORY/etc/passwd
echo "root:x:0:" > $CHROOT_DIRECTORY/etc/group
echo "www-data:x:33:www-data" >> $CHROOT_DIRECTORY/etc/group

# /dev devices
test -e $CHROOT_DIRECTORY/dev/null      || mknod -m 666 $CHROOT_DIRECTORY/dev/null c 1 3
test -e $CHROOT_DIRECTORY/dev/zero      || mknod -m 666 $CHROOT_DIRECTORY/dev/zero c 1 5
test -e $CHROOT_DIRECTORY/dev/tty       || mknod -m 666 $CHROOT_DIRECTORY/dev/tty c 5 0
test -e $CHROOT_DIRECTORY/dev/random    || mknod -m 644 $CHROOT_DIRECTORY/dev/random c 1 8
test -e $CHROOT_DIRECTORY/dev/urandom	|| mknod -m 644 $CHROOT_DIRECTORY/dev/urandom c 1 9
# copy general directories
for DIRECTORY in ${DIRECTORIES_GENERAL[@]}; do
    rm -rf $CHROOT_DIRECTORY$DIRECTORY
    cp -Rp $DIRECTORY $CHROOT_DIRECTORY$DIRECTORY
done
echo "export HOME=/" > $CHROOT_DIRECTORY/etc/profile.d/hosting.sh
echo "export HISTFILE=/.bash_history" >> $CHROOT_DIRECTORY/etc/profile.d/hosting.sh
echo 'export PATH="$PATH:/.composer/vendor/bin"' >> $CHROOT_DIRECTORY/etc/profile.d/hosting.sh
# copy general files
for FILE in ${FILES_GENERAL[@]}; do
    cp $FILE $CHROOT_DIRECTORY$FILE
done
### copy shared libraries and binaries
# general
for BINARY in ${BINARIES_GENERAL[@]}; do
    CHROOT_BINARY $BINARY
done
# git
for BINARY in `find /usr/lib/git-core -type f`; do
    CHROOT_BINARY $BINARY
done
# networking
for BINARY in /lib/*/libnss_*; do
    CHROOT_BINARY $BINARY
done
# php
for BINARY in /usr/lib/php/*/*.so; do
    CHROOT_BINARY $BINARY
done
CHROOT_LIBRARIES
ln -f $CHROOT_DIRECTORY/usr/bin/php8.0 $CHROOT_DIRECTORY/usr/bin/php
