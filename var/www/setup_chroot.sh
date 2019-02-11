#!/bin/bash

export PATH="/usr/local/bin:/usr/bin:/bin:/usr/local/sbin:/usr/sbin:/sbin"

test "$1" != "" || (echo "Need path to chroot directory" && exit 1)

### functions
function CHROOT_BINARY() {
    BINARY="$1"
    if [ "$(echo $BINARY | grep -E '.*:.*')" != "" ]; then
        BINARY="$(echo $BINARY | cut -d':' -f2)"
    fi
    LIB_FILES="$(ldd $BINARY | grep -v 'not a dynamic executable' | awk '{ print $3 }' | grep -E '^/(.*)' || echo)"
    LDD_FILES="$(ldd $BINARY | grep -v 'not a dynamic executable' | grep 'ld-linux' | awk '{ print $1; }' || echo)"
    if [ "$LIB_FILES" != "" ]; then
        for LIB_FILE in $LIB_FILES; do
            LIB_DIRECTORY="$(dirname $LIB_FILE)"
            test -d $CHROOT_DIRECTORY$LIB_DIRECTORY || mkdir -pm 0555 $CHROOT_DIRECTORY$LIB_DIRECTORY
            diff $LIB_FILE $CHROOT_DIRECTORY$LIB_FILE > /dev/null 2>&1 || cp $LIB_FILE $CHROOT_DIRECTORY$LIB_FILE
            chmod 0555 $CHROOT_DIRECTORY$LIB_FILE
        done
    fi
    if [ "$LDD_FILES" != "" ]; then
        for LDD_FILE in $LDD_FILES; do
            LDD_DIRECTORY="$(dirname $LDD_FILE)"
            test -d $CHROOT_DIRECTORY$LDD_DIRECTORY || mkdir -pm 0555 $CHROOT_DIRECTORY${LDD_DIRECTORY}
            diff $LDD_FILE $CHROOT_DIRECTORY$LDD_FILE > /dev/null 2>&1 || cp $LDD_FILE $CHROOT_DIRECTORY$LDD_FILE
            chmod 0555 $CHROOT_DIRECTORY$LDD_FILE
        done
    fi
    diff $BINARY $CHROOT_DIRECTORY/$BINARY > /dev/null 2>&1 || cp $BINARY $CHROOT_DIRECTORY/$BINARY
    chmod 0555 $CHROOT_DIRECTORY/$BINARY
}

function CHROOT_FILE() {
    diff $1 $CHROOT_DIRECTORY/$1 > /dev/null 2>&1 || cp $1 $CHROOT_DIRECTORY/$1
}

function CHROOT_DIRECTORY() {
    test -d $CHROOT_DIRECTORY/$1 || mkdir -pm 0555 $CHROOT_DIRECTORY/$1
    diff -r $1 $CHROOT_DIRECTORY/$1 > /dev/null 2>&1 || {
        test ! -d $CHROOT_DIRECTORY/$1 || rm -rf $CHROOT_DIRECTORY/$1/ > /dev/null 2>&1
        cp -Rp $1 $CHROOT_DIRECTORY/$1
    }
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
    '/usr/bin'
    '/usr/lib'
    '/usr/lib/openssh'
    '/usr/sbin'
    '/var'
    '/var/run'
    '/var/run/mysqld'
)
BINARIES_GENERAL=(
    '/usr/lib/openssh/sftp-server'
    '/bin/bash'
    '/bin/sh'
    '/usr/bin/env'
    '/usr/bin/clear'
    '/bin/date'
    '/usr/bin/basename'
    '/bin/ls'
    '/bin/chmod'
    '/bin/touch'
    '/bin/mkdir'
    '/bin/ln'
    '/bin/rm'
    '/bin/rmdir'
    '/bin/cp'
    '/bin/mv'
    '/bin/cat'
    '/bin/grep'
    '/bin/egrep'
    '/bin/fgrep'
    '/bin/sed'
    '/usr/bin/xargs'
    '/usr/bin/head'
    '/usr/bin/tr'
    '/usr/bin/tail'
    '/usr/bin/less'
    '/usr/bin/tput'
    '/usr/bin/sort'
    '/bin/which'
    '/usr/bin/find'
    '/usr/bin/openssl'
    '/bin/tar'
    '/bin/gzip'
    '/bin/gunzip'
    '/usr/bin/zip'
    '/usr/bin/unzip'
    '/usr/bin/curl'
    '/usr/bin/rsync'
    '/usr/bin/scp'
    '/usr/bin/wget'
    '/usr/bin/php7.3'
    '/usr/bin/mysql'
    '/usr/bin/mysqldump'
    '/usr/bin/mysqlcheck'
    '/usr/bin/git'
    '/usr/bin/git-receive-pack'
    '/usr/bin/git-shell'
    '/usr/bin/git-upload-archive'
    '/usr/bin/git-upload-pack'
    '/usr/sbin/nologin'
    '/usr/bin/id'
    '/bin/uname'
    '/bin/nano'
    '/usr/bin/vim'
    '/usr/bin/vi'
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
)
DIRECTORIES_GENERAL=(
    '/usr/lib/git-core'
    '/usr/share/git-core'
    '/usr/lib/locale'
    '/usr/share/i18n'
    '/etc/ssl'
    '/usr/lib/ssl'
    '/usr/share/ca-certificates'
    '/usr/share/bash-completion'
    '/etc/bash_completion.d'
    '/usr/share/zoneinfo'
    '/lib/terminfo'
    '/usr/share/terminfo'
    '/usr/lib/php'
    '/etc/php/7.3/cli'
    '/etc/php/7.3/mods-available'
    '/etc/profile.d'
)
### test variables/parameters
test "$CHROOT_DIRECTORY" != ""

if [ "$2" != "" ]; then
    CHROOT_BINARY $2
    echo "copied extra binary $2";
    exit 0;
fi

### init chroot directory
mkdir -p $CHROOT_DIRECTORY
chown root:www-data $CHROOT_DIRECTORY
chmod 550 $CHROOT_DIRECTORY
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
    CHROOT_DIRECTORY $DIRECTORY
done
# copy general files
for FILE in ${FILES_GENERAL[@]}; do
    CHROOT_FILE $FILE
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
diff $CHROOT_DIRECTORY/usr/bin/php7.3 $CHROOT_DIRECTORY/usr/bin/php > /dev/null 2>&1 || cp -r $CHROOT_DIRECTORY/usr/bin/php7.3 $CHROOT_DIRECTORY/usr/bin/php
