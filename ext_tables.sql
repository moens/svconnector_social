
CREATE TABLE tx_svconnectorsocial_cache (
	id int(11) NOT NULL auto_increment,
	identifier varchar(128) NOT NULL DEFAULT '',
	crdate int(11) unsigned NOT NULL DEFAULT '0',
	content mediumtext,
	lifetime int(11) unsigned NOT NULL DEFAULT '0',
	PRIMARY KEY (id),
	KEY cache_id (`identifier`)
);

CREATE TABLE tx_svconnectorsocial_cache_tags (
	id int(11) NOT NULL auto_increment,
	identifier varchar(128) NOT NULL DEFAULT '',
	tag varchar(250) NOT NULL DEFAULT '',
# <!-- varchar(250) since a tag cannot be longer than 250 chars: -->
# <!--		t3lib/cache/frontend/interfaces/interface.t3lib_cache_frontend_frontend.php:	const PATTERN_TAG = '/^[a-zA-Z0-9_%\-&]{1,250}$/'; -->
	PRIMARY KEY (id),
	KEY cache_id (`identifier`),
	KEY cache_tag (`tag`)
);
