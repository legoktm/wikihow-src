#!/bin/sh

cd /var/www/html/wiki/maintenance/spellcheck

if [ "`ps auxww | grep \"spellchecker.php $1\" |grep -c -v grep`" = "0" ]; then
	/usr/local/bin/php spellchecker.php $1 >> log.txt
fi