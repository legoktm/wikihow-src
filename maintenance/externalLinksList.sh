#!/bin/bash
#
# Runs a script that lists out all external links

DIR="/var/www/html/wiki"

cd $DIR
/usr/local/bin/php maintenance/externalLinksList.php
