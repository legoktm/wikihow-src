#!/bin/bash
WIKILANG='fr'
cd /var/www/html/wiki-$WIKILANG-dev/maintenance/
svn up links
cd links
echo 'select el_from, el_to from externallinks e, page p where el_from = page_id' | mysql wikidb_$WIKILANG > wikidb_$WIKILANG
/usr/local/bin/php addIds.php wikidb_$WIKILANG
/usr/local/bin/php newExternalLinksList.php wikidb_$WIKILANG
zip wikidb_$WIKILANG.zip wikidb_$WIKILANG*
mv wikidb_$WIKILANG.zip ../../x/

