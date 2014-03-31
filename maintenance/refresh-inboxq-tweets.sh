#!/bin/bash
#
# Pull the latest tweets from the InboxQ API via these web calls.  This
# script should be run once every 20 minutes with a crontab line similar
# to this:
# */20 * * * * sh /home/reuben/prod/maintenance/refresh-inboxq-tweets.sh
#

. /usr/local/wikihow/wikihow.sh

log="$wikihow/log/tweet-it-forward.log"

if [ "`hostname |grep -c wikidiy.com`" = "0" ]; then
	host="www.wikihow.com"
else
	host="r.doh.wikidiy.com"
fi

out=`curl --silent --user $WH_WEBAUTH_USER:$WH_WEBAUTH_PASSWORD -H 'Cookie: wiki_through=bypass;' -H 'Pragma: nocache' "http://$host/Special:TweetItForward?action=retrieve" 2>&1`

echo "`date` retrieved: $out" >> $log

