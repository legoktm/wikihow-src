#!/bin/bash

# range is A-L or M-Z etc
[ "$1" == "" ] && echo "running against all titles"
range=$1
term=error
output=$range.out
echo "starting job, adding 'ERROR' to output for no stopping false positives" >> $output
while tail -5 $output | grep -qi $term; do
	time sudo -u apache php make_new_thumbs.php $range > $output 2>&1
done
