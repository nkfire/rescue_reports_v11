-- Domain-Model-Tabellen
CREATE TABLE tx_rescuereports_domain_model_event (
  uid int(11) NOT NULL auto_increment,
  pid int(11) DEFAULT '0' NOT NULL,
  title varchar(255) DEFAULT '' NOT NULL,
  description text,
  internal_notes text,
  start datetime DEFAULT NULL,
  end datetime DEFAULT NULL,
  location varchar(255) DEFAULT '' NOT NULL,
  latitude decimal(10,8) DEFAULT NULL,
  longitude decimal(11,8) DEFAULT NULL,
  disable_detail int(1) DEFAULT 0 NOT NULL,
  cars varchar(255) DEFAULT '' NOT NULL,
  vehicles TEXT NOT NULL,
  slug varchar(2048) DEFAULT '' NOT NULL,
  slug_source varchar(255) DEFAULT '' NOT NULL,
  types int(11) DEFAULT '0' NOT NULL,
  keyword_escalation int(11) DEFAULT '0' NOT NULL,
  enable_keyword_escalation tinyint(1) DEFAULT 0 NOT NULL,
  images INT(11) DEFAULT 0 NOT NULL,
  brigade INT(11) DEFAULT 0 NOT NULL,
  stations int(11) DEFAULT '0' NOT NULL,
  number varchar(255) DEFAULT '0' NOT NULL,
  hidden tinyint(4) DEFAULT '0' NOT NULL,
  deleted tinyint(4) DEFAULT '0' NOT NULL,
  tstamp int(11) DEFAULT '0' NOT NULL,
  crdate int(11) DEFAULT '0' NOT NULL,
  cruser_id int(11) DEFAULT '0' NOT NULL,
  sys_language_uid int(11) DEFAULT '0' NOT NULL,
  l18n_parent int(11) DEFAULT '0' NOT NULL,
  l18n_diffsource mediumblob,
  starttime int(11) DEFAULT '0' NOT NULL,
  endtime int(11) DEFAULT '0' NOT NULL,
  t3ver_oid int(11) DEFAULT '0' NOT NULL,
  t3ver_id int(11) DEFAULT '0' NOT NULL,
  t3ver_wsid int(11) DEFAULT '0' NOT NULL,
  t3ver_label varchar(255) DEFAULT '' NOT NULL,
  t3ver_state smallint(6) DEFAULT '0' NOT NULL,
  t3ver_stage int(11) DEFAULT '0' NOT NULL,
  t3ver_count int(11) DEFAULT '0' NOT NULL,
  t3ver_tstamp int(11) DEFAULT '0' NOT NULL,
  t3_origuid int(11) DEFAULT '0' NOT NULL,
  PRIMARY KEY (uid)
);

CREATE TABLE tx_rescuereports_domain_model_car (
uid int(11) NOT NULL auto_increment,
pid int(11) DEFAULT '0' NOT NULL,
name varchar(255) DEFAULT '' NOT NULL,
organization INT(11) DEFAULT '0' NOT NULL,
hidden tinyint(4) DEFAULT '0' NOT NULL,
deleted tinyint(4) DEFAULT '0' NOT NULL,
tstamp int(11) DEFAULT '0' NOT NULL,
crdate int(11) DEFAULT '0' NOT NULL,
cruser_id int(11) DEFAULT '0' NOT NULL,
sys_language_uid int(11) DEFAULT '0' NOT NULL,
l18n_parent int(11) DEFAULT '0' NOT NULL,
l18n_diffsource mediumblob,
starttime int(11) DEFAULT '0' NOT NULL,
endtime int(11) DEFAULT '0' NOT NULL,
t3ver_oid int(11) DEFAULT '0' NOT NULL,
t3ver_id int(11) DEFAULT '0' NOT NULL,
t3ver_wsid int(11) DEFAULT '0' NOT NULL,
t3ver_label varchar(255) DEFAULT '' NOT NULL,
t3ver_state smallint(6) DEFAULT '0' NOT NULL,
t3ver_stage int(11) DEFAULT '0' NOT NULL,
t3ver_count int(11) DEFAULT '0' NOT NULL,
t3ver_tstamp int(11) DEFAULT '0' NOT NULL,
t3_origuid int(11) DEFAULT '0' NOT NULL,
PRIMARY KEY (uid)
);

CREATE TABLE tx_rescuereports_domain_model_brigade (
  uid int(11) NOT NULL auto_increment,
  pid int(11) DEFAULT '0' NOT NULL,
  name varchar(255) DEFAULT '' NOT NULL,
  organization INT(11) DEFAULT '0' NOT NULL,
  sorting int(11) DEFAULT '0' NOT NULL,
  stations int(11) DEFAULT '0' NOT NULL,
  is_primary int(1) DEFAULT 0 NOT NULL,
  hidden tinyint(4) DEFAULT '0' NOT NULL,
  deleted tinyint(4) DEFAULT '0' NOT NULL,
  tstamp int(11) DEFAULT '0' NOT NULL,
  crdate int(11) DEFAULT '0' NOT NULL,
  cruser_id int(11) DEFAULT '0' NOT NULL,
  sys_language_uid int(11) DEFAULT '0' NOT NULL,
  l18n_parent int(11) DEFAULT '0' NOT NULL,
  l18n_diffsource mediumblob,
  starttime int(11) DEFAULT '0' NOT NULL,
  endtime int(11) DEFAULT '0' NOT NULL,
  t3ver_oid int(11) DEFAULT '0' NOT NULL,
  t3ver_id int(11) DEFAULT '0' NOT NULL,
  t3ver_wsid int(11) DEFAULT '0' NOT NULL,
  t3ver_label varchar(255) DEFAULT '' NOT NULL,
  t3ver_state smallint(6) DEFAULT '0' NOT NULL,
  t3ver_stage int(11) DEFAULT '0' NOT NULL,
  t3ver_count int(11) DEFAULT '0' NOT NULL,
  t3ver_tstamp int(11) DEFAULT '0' NOT NULL,
  t3_origuid int(11) DEFAULT '0' NOT NULL,
  PRIMARY KEY (uid)
);

CREATE TABLE tx_rescuereports_brigade_station_mm (
  uid_local INT(11) DEFAULT 0 NOT NULL,
  uid_foreign INT(11) DEFAULT 0 NOT NULL,
  sorting INT(11) DEFAULT 0 NOT NULL,
  KEY uid_local (uid_local),
  KEY uid_foreign (uid_foreign)
);

CREATE TABLE tx_rescuereports_event_station_car_mm (
  uid_local INT(11) DEFAULT 0 NOT NULL,
  uid_station INT(11) DEFAULT 0 NOT NULL,
  uid_foreign INT(11) DEFAULT 0 NOT NULL,
  sorting INT(11) DEFAULT 0 NOT NULL,
  KEY uid_local (uid_local),
  KEY uid_station (uid_station),
  KEY uid_foreign (uid_foreign)
);

CREATE TABLE tx_rescuereports_station_car_mm (
  uid_local INT(11) DEFAULT 0 NOT NULL,
  uid_station INT(11) DEFAULT 0 NOT NULL,
  uid_foreign INT(11) DEFAULT 0 NOT NULL,
  sorting INT(11) DEFAULT 0 NOT NULL,
  KEY uid_local (uid_local),
  KEY uid_station (uid_station),
  KEY uid_foreign (uid_foreign)
);
CREATE TABLE tx_rescuereports_event_type_mm (
  uid_local INT(11) DEFAULT 0 NOT NULL,
  uid_foreign INT(11) DEFAULT 0 NOT NULL,
  sorting INT(11) DEFAULT 0 NOT NULL,
  KEY uid_local (uid_local),
  KEY uid_foreign (uid_foreign)
);

CREATE TABLE tx_rescuereports_event_keyword_escalation_mm (
  uid_local INT(11) DEFAULT 0 NOT NULL,
  uid_foreign INT(11) DEFAULT 0 NOT NULL,
  sorting INT(11) DEFAULT 0 NOT NULL,
  KEY uid_local (uid_local),
  KEY uid_foreign (uid_foreign)
);

CREATE TABLE tx_rescuereports_event_station_mm (
  uid_local INT(11) DEFAULT 0 NOT NULL,
  uid_foreign INT(11) DEFAULT 0 NOT NULL,
  sorting INT(11) DEFAULT 0 NOT NULL,
  KEY uid_local (uid_local),
  KEY uid_foreign (uid_foreign)
);

CREATE TABLE tx_rescuereports_event_deployment_mm (
  uid_local INT(11) DEFAULT 0 NOT NULL,
  uid_foreign INT(11) DEFAULT 0 NOT NULL,
  sorting INT(11) DEFAULT 0 NOT NULL,
  KEY uid_local (uid_local),
  KEY uid_foreign (uid_foreign)
);

CREATE TABLE tx_rescuereports_domain_model_station (
  uid INT(11) NOT NULL AUTO_INCREMENT,
  pid INT(11) DEFAULT '0' NOT NULL,
  name VARCHAR(255) DEFAULT '' NOT NULL,
  sorting INT(11) DEFAULT 0 NOT NULL,
  brigade INT(11) DEFAULT 0 NOT NULL,
  prefix varchar(10) DEFAULT '' NOT NULL,
  cars int(11) DEFAULT '0' NOT NULL,
  vehicles INT(11) DEFAULT 0 NOT NULL,
  exclude_from_filter int(1) DEFAULT 0 NOT NULL,
  hidden tinyint(4) DEFAULT '0' NOT NULL,
  deleted tinyint(4) DEFAULT '0' NOT NULL,
  tstamp int(11) DEFAULT '0' NOT NULL,
  crdate int(11) DEFAULT '0' NOT NULL,
  cruser_id int(11) DEFAULT '0' NOT NULL,
  sys_language_uid int(11) DEFAULT '0' NOT NULL,
  l18n_parent int(11) DEFAULT '0' NOT NULL,
  l18n_diffsource mediumblob,
  starttime int(11) DEFAULT '0' NOT NULL,
  endtime int(11) DEFAULT '0' NOT NULL,
  t3ver_oid int(11) DEFAULT '0' NOT NULL,
  t3ver_id int(11) DEFAULT '0' NOT NULL,
  t3ver_wsid int(11) DEFAULT '0' NOT NULL,
  t3ver_label varchar(255) DEFAULT '' NOT NULL,
  t3ver_state smallint(6) DEFAULT '0' NOT NULL,
  t3ver_stage int(11) DEFAULT '0' NOT NULL,
  t3ver_count int(11) DEFAULT '0' NOT NULL,
  t3ver_tstamp int(11) DEFAULT '0' NOT NULL,
  t3_origuid int(11) DEFAULT '0' NOT NULL,
  PRIMARY KEY (uid)
);

-- Weitere Tabellen
CREATE TABLE tx_rescuereports_domain_model_image (
  uid int(11) NOT NULL auto_increment,
  pid int(11) DEFAULT '0' NOT NULL,
  name varchar(255) DEFAULT '' NOT NULL,
  title varchar(255) DEFAULT '' NOT NULL,
  hidden tinyint(4) DEFAULT '0' NOT NULL,
  deleted tinyint(4) DEFAULT '0' NOT NULL,
  tstamp int(11) DEFAULT '0' NOT NULL,
  crdate int(11) DEFAULT '0' NOT NULL,
  cruser_id int(11) DEFAULT '0' NOT NULL,
  sys_language_uid int(11) DEFAULT '0' NOT NULL,
  l18n_parent int(11) DEFAULT '0' NOT NULL,
  l18n_diffsource mediumblob,
  starttime int(11) DEFAULT '0' NOT NULL,
  endtime int(11) DEFAULT '0' NOT NULL,
  t3ver_oid int(11) DEFAULT '0' NOT NULL,
  t3ver_id int(11) DEFAULT '0' NOT NULL,
  t3ver_wsid int(11) DEFAULT '0' NOT NULL,
  t3ver_label varchar(255) DEFAULT '' NOT NULL,
  t3ver_state smallint(6) DEFAULT '0' NOT NULL,
  t3ver_stage int(11) DEFAULT '0' NOT NULL,
  t3ver_count int(11) DEFAULT '0' NOT NULL,
  t3ver_tstamp int(11) DEFAULT '0' NOT NULL,
  t3_origuid int(11) DEFAULT '0' NOT NULL,
  PRIMARY KEY (uid)
);

CREATE TABLE tx_rescuereports_domain_model_category (
  uid int(11) NOT NULL auto_increment,
  pid int(11) DEFAULT '0' NOT NULL,
  title varchar(255) DEFAULT '' NOT NULL,
  color varchar(10) DEFAULT '#3498db' NOT NULL,
  image int(11) DEFAULT 0 NOT NULL,
  sorting int(11) DEFAULT '0' NOT NULL,
  hidden tinyint(4) DEFAULT '0' NOT NULL,
  deleted tinyint(4) DEFAULT '0' NOT NULL,
  tstamp int(11) DEFAULT '0' NOT NULL,
  crdate int(11) DEFAULT '0' NOT NULL,
  cruser_id int(11) DEFAULT '0' NOT NULL,
  PRIMARY KEY (uid)
);

CREATE TABLE tx_rescuereports_domain_model_type (
  uid int(11) NOT NULL auto_increment,
  pid int(11) DEFAULT '0' NOT NULL,
  title varchar(255) DEFAULT '' NOT NULL,
  category int(11) DEFAULT '0' NOT NULL,
  image int(11) DEFAULT 0 NOT NULL,
  deprecated tinyint(1) unsigned DEFAULT '0' NOT NULL,
  hidden tinyint(4) DEFAULT '0' NOT NULL,
  deleted tinyint(4) DEFAULT '0' NOT NULL,
  tstamp int(11) DEFAULT '0' NOT NULL,
  crdate int(11) DEFAULT '0' NOT NULL,
  cruser_id int(11) DEFAULT '0' NOT NULL,
  sys_language_uid int(11) DEFAULT '0' NOT NULL,
  l18n_parent int(11) DEFAULT '0' NOT NULL,
  l18n_diffsource mediumblob,
  starttime int(11) DEFAULT '0' NOT NULL,
  endtime int(11) DEFAULT '0' NOT NULL,
  t3ver_oid int(11) DEFAULT '0' NOT NULL,
  t3ver_id int(11) DEFAULT '0' NOT NULL,
  t3ver_wsid int(11) DEFAULT '0' NOT NULL,
  t3ver_label varchar(255) DEFAULT '' NOT NULL,
  t3ver_state smallint(6) DEFAULT '0' NOT NULL,
  t3ver_stage int(11) DEFAULT '0' NOT NULL,
  t3ver_count int(11) DEFAULT '0' NOT NULL,
  t3ver_tstamp int(11) DEFAULT '0' NOT NULL,
  t3_origuid int(11) DEFAULT '0' NOT NULL,
  PRIMARY KEY (uid)
);

CREATE TABLE tx_rescuereports_domain_model_deployment (
  uid INT(11) NOT NULL AUTO_INCREMENT,
  pid INT(11) DEFAULT '0' NOT NULL,
  title VARCHAR(255) DEFAULT '' NOT NULL,
  description TEXT,
  brigade INT(11) DEFAULT '0' NOT NULL,
  date DATETIME DEFAULT NULL,
  hidden TINYINT(4) DEFAULT '0' NOT NULL,
  deleted TINYINT(4) DEFAULT '0' NOT NULL,
  tstamp INT(11) DEFAULT '0' NOT NULL,
  crdate INT(11) DEFAULT '0' NOT NULL,
  cruser_id INT(11) DEFAULT '0' NOT NULL,
  sys_language_uid INT(11) DEFAULT '0' NOT NULL,
  l18n_parent INT(11) DEFAULT '0' NOT NULL,
  l18n_diffsource MEDIUMBLOB,
  starttime INT(11) DEFAULT '0' NOT NULL,
  endtime INT(11) DEFAULT '0' NOT NULL,
  t3ver_oid INT(11) DEFAULT '0' NOT NULL,
  t3ver_id INT(11) DEFAULT '0' NOT NULL,
  t3ver_wsid INT(11) DEFAULT '0' NOT NULL,
  t3ver_label VARCHAR(255) DEFAULT '' NOT NULL,
  t3ver_state SMALLINT(6) DEFAULT '0' NOT NULL,
  t3ver_stage INT(11) DEFAULT '0' NOT NULL,
  t3ver_count INT(11) DEFAULT '0' NOT NULL,
  t3ver_tstamp INT(11) DEFAULT '0' NOT NULL,
  t3_origuid INT(11) DEFAULT '0' NOT NULL,
  PRIMARY KEY (uid)
);

CREATE TABLE tx_rescuereports_event_car_mm (
  --uid INT(11) NOT NULL auto_increment,
  uid_local int(11) unsigned NOT NULL DEFAULT 0,
  uid_foreign int(11) unsigned NOT NULL DEFAULT 0,
  sorting int(11) unsigned NOT NULL DEFAULT 0,
  sorting_foreign int(11) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (uid_local, uid_foreign),
  KEY uid_local (uid_local),
  KEY uid_foreign (uid_foreign)
);


CREATE TABLE tx_rescuereports_domain_model_eventvehicleassignment (
  uid INT(11) NOT NULL AUTO_INCREMENT,
  pid INT(11) DEFAULT '0' NOT NULL,
  event INT(11) DEFAULT 0 NOT NULL,
  station INT(11) DEFAULT 0 NOT NULL,
  car INT(11) DEFAULT 0 NOT NULL,
  cars int(11) DEFAULT '0' NOT NULL,
  hidden TINYINT(4) DEFAULT '0' NOT NULL,
  deleted TINYINT(4) DEFAULT '0' NOT NULL,
  tstamp INT(11) DEFAULT '0' NOT NULL,
  crdate INT(11) DEFAULT '0' NOT NULL,
  cruser_id INT(11) DEFAULT '0' NOT NULL,
  sys_language_uid INT(11) DEFAULT '0' NOT NULL,
  l18n_parent INT(11) DEFAULT '0' NOT NULL,
  l18n_diffsource MEDIUMBLOB,
  starttime INT(11) DEFAULT '0' NOT NULL,
  endtime INT(11) DEFAULT '0' NOT NULL,
  t3ver_oid INT(11) DEFAULT '0' NOT NULL,
  t3ver_id INT(11) DEFAULT '0' NOT NULL,
  t3ver_wsid INT(11) DEFAULT '0' NOT NULL,
  t3ver_label VARCHAR(255) DEFAULT '' NOT NULL,
  t3ver_state SMALLINT(6) DEFAULT '0' NOT NULL,
  t3ver_stage INT(11) DEFAULT '0' NOT NULL,
  t3ver_count INT(11) DEFAULT '0' NOT NULL,
  t3ver_tstamp INT(11) DEFAULT '0' NOT NULL,
  t3_origuid INT(11) DEFAULT '0' NOT NULL,
  PRIMARY KEY (uid)
);

CREATE TABLE tx_rescuereports_eventvehicleassignment_car_mm (
  uid_local int(11) DEFAULT 0 NOT NULL,
  uid_foreign int(11) DEFAULT 0 NOT NULL,
  sorting int(11) DEFAULT 0 NOT NULL,
  sorting_foreign int(11) DEFAULT 0 NOT NULL,
  KEY uid_local (uid_local),
  KEY uid_foreign (uid_foreign)
);

CREATE TABLE tx_rescuereports_domain_model_vehicle (
  uid INT(11) NOT NULL AUTO_INCREMENT,
  pid INT(11) DEFAULT 0 NOT NULL,
  name VARCHAR(255) DEFAULT '' NOT NULL,
  car INT(11) DEFAULT 0 NOT NULL,
  station INT(11) DEFAULT 0 NOT NULL,
  link varchar(255) DEFAULT '' NOT NULL,
  image INT(11) DEFAULT 0 NOT NULL,
  sorting INT(11) DEFAULT 0 NOT NULL,
  tstamp INT(11) DEFAULT 0 NOT NULL,
  crdate INT(11) DEFAULT 0 NOT NULL,
  cruser_id INT(11) DEFAULT 0 NOT NULL,
  deleted TINYINT(4) DEFAULT 0 NOT NULL,
  hidden TINYINT(4) DEFAULT 0 NOT NULL,
  PRIMARY KEY (uid)
);

CREATE TABLE tx_rescuereports_domain_model_organisation (
  uid INT(11) NOT NULL AUTO_INCREMENT,
  pid INT(11) DEFAULT '0' NOT NULL,
  tstamp INT(11) DEFAULT '0' NOT NULL,
  crdate INT(11) DEFAULT '0' NOT NULL,
  cruser_id INT(11) DEFAULT '0' NOT NULL,
  deleted TINYINT(4) DEFAULT '0' NOT NULL,
  hidden TINYINT(4) DEFAULT '0' NOT NULL,
  sys_language_uid INT(11) DEFAULT '0' NOT NULL,
  l10n_parent INT(11) DEFAULT '0' NOT NULL,
  l10n_diffsource MEDIUMBLOB,
  name VARCHAR(255) DEFAULT '' NOT NULL,
  abbreviation VARCHAR(32) DEFAULT '' NOT NULL,
  icon varchar(10) DEFAULT '' NOT NULL,
  PRIMARY KEY (uid),
  KEY parent (pid)
);

CREATE TABLE tx_rescuereports_domain_model_snippet (
  uid int(11) NOT NULL auto_increment,
  pid int(11) DEFAULT '0' NOT NULL,
  title varchar(255) DEFAULT '' NOT NULL,
  category varchar(100) DEFAULT '' NOT NULL,
  content text,
  sorting int(11) DEFAULT '0' NOT NULL,
  hidden tinyint(4) DEFAULT '0' NOT NULL,
  deleted tinyint(4) DEFAULT '0' NOT NULL,
  tstamp int(11) DEFAULT '0' NOT NULL,
  crdate int(11) DEFAULT '0' NOT NULL,
  cruser_id int(11) DEFAULT '0' NOT NULL,
  PRIMARY KEY (uid)
);
