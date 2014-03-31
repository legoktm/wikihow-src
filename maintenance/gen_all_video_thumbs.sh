#! /bin/sh

cd /var/www/html/wiki
log=vids.log
log2=vid_help.log

old=`find /var/www/images_en/yt -name "*.jpg"  | sed 's@.*/@@' | sed 's@-.*@@' | sort -u | wc -l`
echo "`date` starting ... had $old videos" >> $log2

/usr/local/bin/php maintenance/get_videos_to_generate_thumbs.php  25 > newvids.txt
for id in `awk '{print $NF}' newvids.txt`
do
	echo "`date` Doing $id"
	/usr/local/wikihow/grabTYthumbnails.sh $id 2>> $log >> $log
done

count=`find /var/www/images_en/yt -name "*.jpg"  | sed 's@.*/@@' | sed 's@-.*@@' | sort -u | wc -l`
echo "`date` done ... now have $count videos" >> $log2
