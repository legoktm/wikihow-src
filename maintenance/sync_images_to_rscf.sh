#!/bin/bash
#
# Copy all new image files to Rackspace cloud
#

. /usr/local/wikihow/wikihow.sh

epoch_file="`dirname $0`/sync_rscloud_images_epoch.txt"

if [ -f "$epoch_file" ]; then
	last_epoch=`cat $epoch_file`
	epoch_param="--epoch=$last_epoch"
fi

if [ "`hostname | grep -c 'wikihow.com$'`" = "0" ]; then
	# on dev server
	bucket_param="--bucket=images_dev"
	src_dir="`dirname $0`"
else
	# production
	bucket_param="--bucket=images"
	src_dir="$wiki/maintenance"
fi

new_epoch=`date +'%s'`
echo $new_epoch > $epoch_file

php $src_dir/sync_images_to_rscf.php $epoch_param $bucket_param

