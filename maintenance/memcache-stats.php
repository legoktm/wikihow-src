<?
//
// Looks up and reports on both memcache server stats. Must be run on spare
// host since it's the only one with the Memcache pecl package installed.
//

require_once 'commandLine.inc';

$memcache = new Memcache;
$s1 = split(':', WH_MEMCACHED_SERVER_1);
$memcache->connect($s1[0], $s1[1]);

$s2 = split(':', WH_MEMCACHED_SERVER_2);
$memcache->connect($s2[0], $s2[1]);

print_r( $memcache->getExtendedStats() );

