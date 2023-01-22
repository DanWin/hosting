#!/bin/bash
xgettext --from-code UTF-8 -o var/www/locale/hosting.pot `find var/www/ -iname '*.php'`
for translation in `find var/www/locale -iname '*.po'`; do msgmerge -U "$translation" var/www/locale/hosting.pot; msgfmt -o ${translation:0:-2}mo "$translation"; done
