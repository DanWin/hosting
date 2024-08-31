#!/bin/bash
set -e

export PATH="/usr/local/bin:/usr/bin:/bin:/usr/local/sbin:/usr/sbin:/sbin"

test "$1" != "" || { echo "Need path to chroot directory"; exit 1; }

### variables
CHROOT_DIRECTORY=$1
### test variables/parameters
test "$CHROOT_DIRECTORY" != ""

if [ "$2" != "" ]; then
    jk_cp -j "$CHROOT_DIRECTORY" -k "$2"
    echo "copied extra binary $2";
    exit 0;
fi

### init chroot directory
if [[ -d "$CHROOT_DIRECTORY/bin" ]]; then
    chown root:root "$CHROOT_DIRECTORY"
    chmod 555 "$CHROOT_DIRECTORY"
    jk_update -j "$CHROOT_DIRECTORY" -k /bin /lib /usr
else
    mkdir -p "$CHROOT_DIRECTORY"
    chown root:root "$CHROOT_DIRECTORY"
    chmod 555 "$CHROOT_DIRECTORY"
    jk_init -j "$CHROOT_DIRECTORY" -k custom_hosting
    chmod 777 "$CHROOT_DIRECTORY/tmp"
    echo "export HOME=/" > "$CHROOT_DIRECTORY/etc/profile.d/hosting.sh"
    echo "export HISTFILE=/.bash_history" >> "$CHROOT_DIRECTORY/etc/profile.d/hosting.sh"
    echo 'export PATH="$PATH:/.composer/vendor/bin"' >> "$CHROOT_DIRECTORY/etc/profile.d/hosting.sh"
fi
