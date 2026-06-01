CREATE TABLE tx_consenti_domain_model_service (
    uid int(11) NOT NULL auto_increment,
    pid int(11) DEFAULT '0' NOT NULL,

    tstamp int(11) unsigned DEFAULT '0' NOT NULL,
    crdate int(11) unsigned DEFAULT '0' NOT NULL,
    cruser_id int(11) unsigned DEFAULT '0' NOT NULL,
    deleted tinyint(4) unsigned DEFAULT '0' NOT NULL,
    hidden tinyint(4) unsigned DEFAULT '0' NOT NULL,
    sorting int(11) DEFAULT '0' NOT NULL,

    title varchar(255) DEFAULT '' NOT NULL,
    category varchar(32) DEFAULT 'marketing' NOT NULL,
    domains text,
    whitelist tinyint(4) unsigned DEFAULT '0' NOT NULL,
    blacklist tinyint(4) unsigned DEFAULT '0' NOT NULL,

    PRIMARY KEY (uid),
    KEY parent (pid)
);

CREATE TABLE tx_consenti_domain_model_discovery (
    uid int(11) NOT NULL auto_increment,
    pid int(11) DEFAULT '0' NOT NULL,

    tstamp int(11) unsigned DEFAULT '0' NOT NULL,
    crdate int(11) unsigned DEFAULT '0' NOT NULL,
    cruser_id int(11) unsigned DEFAULT '0' NOT NULL,
    deleted tinyint(4) unsigned DEFAULT '0' NOT NULL,
    hidden tinyint(4) unsigned DEFAULT '0' NOT NULL,
    sorting int(11) DEFAULT '0' NOT NULL,

    host varchar(255) DEFAULT '' NOT NULL,
    category varchar(32) DEFAULT 'marketing' NOT NULL,
    source_type varchar(16) DEFAULT 'script' NOT NULL,
    last_source_url text,
    first_seen int(11) unsigned DEFAULT '0' NOT NULL,
    last_seen int(11) unsigned DEFAULT '0' NOT NULL,
    hits int(11) unsigned DEFAULT '0' NOT NULL,
    decision varchar(32) DEFAULT 'blocked' NOT NULL,

    PRIMARY KEY (uid),
    KEY parent (pid),
    KEY host_lookup (host(191)),
    KEY source_lookup (source_type, category)
);
