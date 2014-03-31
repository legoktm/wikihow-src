CREATE TABLE `skey` (
  `skey_id` int(8) unsigned NOT NULL auto_increment,
  `skey_title` varchar(255) character set latin1 collate latin1_bin NOT NULL default '',
  `skey_key` varchar(255) character set latin1 collate latin1_bin NOT NULL default '',
  `skey_namespace` tinyint(2) unsigned NOT NULL default '0',
  `skey_wasfeatured` tinyint(4) NOT NULL default '0',
  UNIQUE KEY `skey_id` (`skey_id`),
  UNIQUE KEY `name_title` (`skey_namespace`,`skey_title`),
  KEY `skey_title` (`skey_title`(20))
);
 CREATE TABLE `rating` (
  `rat_id` int(8) unsigned NOT NULL auto_increment,
  `rat_page` int(8) unsigned NOT NULL default '0',
  `rat_user` int(5) unsigned NOT NULL default '0',
  `rat_user_text` varchar(255) NOT NULL default '',
  `rat_timestamp` timestamp NOT NULL default CURRENT_TIMESTAMP,
  `rat_rating` tinyint(1) unsigned NOT NULL default '0',
  `rat_isdeleted` tinyint(3) unsigned NOT NULL default '0',
  `rat_user_deleted` int(10) unsigned default NULL,
  `rat_deleted_when` timestamp NOT NULL default '0000-00-00 00:00:00',
  PRIMARY KEY  (`rat_page`,`rat_id`),
  UNIQUE KEY `rat_id` (`rat_id`),
  KEY `rat_timestamp` (`rat_timestamp`)
);
CREATE TABLE `user_newkudos` (
  `user_id` int(5) NOT NULL default '0',
  `user_ip` varchar(40) NOT NULL default '',
  KEY `user_id` (`user_id`),
  KEY `user_ip` (`user_ip`)
) ;


alter table page add column page_is_featured tinyint(1) unsigned NOT NULL default '0';
CREATE TABLE `stop_words` (
  `stop_words` text
);

#default sysops
insert into user_groups values (769792, 'sysop'), (769792, 'bureaucrat'), (1236204, 'sysop'), (1236204, 'bureaucrat'), (1315673, 'sysop'), (1315673, 'bureaucrat');

alter table site_stats add column `ss_links_emailed` bigint(20) unsigned default 0;

create table pageswithbrokenlinks (pbl_page int(8) unsigned NOT NULL);
