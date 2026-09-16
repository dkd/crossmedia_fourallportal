#
# Table structure for table 'tx_fourallportal_domain_model_server'
#
CREATE TABLE tx_fourallportal_domain_model_server
(

    uid                int(11)                          NOT NULL auto_increment,
    pid                int(11)              DEFAULT '0' NOT NULL,

    domain             varchar(255)         DEFAULT ''  NOT NULL,
    customer_name      varchar(255)         DEFAULT ''  NOT NULL,
    username           varchar(255)         DEFAULT ''  NOT NULL,
    password           varchar(255)         DEFAULT ''  NOT NULL,
    active             smallint(5) unsigned DEFAULT '0' NOT NULL,
    modules            int(11) unsigned     DEFAULT '0' NOT NULL,
    dimension_mappings int(11) unsigned     DEFAULT '0' NOT NULL,

    tstamp             int(11) unsigned     DEFAULT '0' NOT NULL,
    crdate             int(11) unsigned     DEFAULT '0' NOT NULL,
    deleted            smallint(5) unsigned DEFAULT '0' NOT NULL,
    hidden             smallint(5) unsigned DEFAULT '0' NOT NULL,

    PRIMARY KEY (uid),
    KEY parent (pid)

);

#
# Table structure for table 'tx_fourallportal_domain_model_module'
#
CREATE TABLE tx_fourallportal_domain_model_module
(

    uid                    int(11)                          NOT NULL auto_increment,
    pid                    int(11)              DEFAULT '0' NOT NULL,
    sorting                int(11) unsigned     DEFAULT '0' NOT NULL,

    server                 int(11) unsigned     DEFAULT '0' NOT NULL,

    shell_path             varchar(255)         DEFAULT ''  NOT NULL,
    connector_name         varchar(255)         DEFAULT ''  NOT NULL,
    module_name            varchar(255)         DEFAULT ''  NOT NULL,
    mapping_class          varchar(255)         DEFAULT ''  NOT NULL,
    enable_dynamic_model   int(4) unsigned      DEFAULT '1' NOT NULL,
    contains_dimensions    smallint(5) unsigned DEFAULT '1' NOT NULL,
    config_hash            varchar(255)         DEFAULT ''  NOT NULL,
    last_event_id          int(11)              DEFAULT '0' NOT NULL,
    last_received_event_id int(11)              DEFAULT '0' NOT NULL,
    storage_pid            int(11)              DEFAULT '0' NOT NULL,
    fal_storage            int(11)              DEFAULT '0' NOT NULL,
    usage_flag             varchar(32)          DEFAULT ''  NOT NULL,
    test_object_uuid       varchar(255)         DEFAULT ''  NOT NULL,

    tstamp                 int(11) unsigned     DEFAULT '0' NOT NULL,
    crdate                 int(11) unsigned     DEFAULT '0' NOT NULL,

    PRIMARY KEY (uid),
    KEY parent (pid)
);

#
# Table structure for table 'tx_fourallportal_domain_model_complextype'
#
CREATE TABLE tx_fourallportal_domain_model_complextype
(

    uid                  int(11)                          NOT NULL auto_increment,
    pid                  int(11)              DEFAULT '0' NOT NULL,

    name                 varchar(255)         DEFAULT ''  NOT NULL,
    field_name           varchar(255)         DEFAULT ''  NOT NULL,
    table_name           varchar(255)         DEFAULT ''  NOT NULL,
    parent_uid           int(11)              DEFAULT '0' NOT NULL,
    type                 varchar(255)         DEFAULT ''  NOT NULL,
    label                varchar(255)         DEFAULT ''  NOT NULL,
    label_max            varchar(255)         DEFAULT ''  NOT NULL,
    normalized_value     varchar(255)         DEFAULT ''  NOT NULL,
    actual_value         varchar(255)         DEFAULT ''  NOT NULL,
    normalized_value_max varchar(255)         DEFAULT ''  NOT NULL,
    actual_value_max     varchar(255)         DEFAULT ''  NOT NULL,
    cast_type            varchar(16)          DEFAULT ''  NOT NULL,

    tstamp               int(11) unsigned     DEFAULT '0' NOT NULL,
    crdate               int(11) unsigned     DEFAULT '0' NOT NULL,
    deleted              smallint(5) unsigned DEFAULT '0' NOT NULL,
    sys_language_uid     INT(11)              DEFAULT '0' NOT NULL,
    l10n_state           TEXT                 DEFAULT NULL,
    l10n_parent          INT(11)              DEFAULT '0' NOT NULL,
    l10n_diffsource      mediumblob,

    PRIMARY KEY (uid),
    KEY parent (pid),
    KEY field_name (field_name),
    KEY table_name (table_name),
    KEY name (name),
    KEY sys_language_uid (sys_language_uid),
    KEY l10n_parent (l10n_parent)

);

#
# Table structure for table 'tx_fourallportal_domain_model_event'
#
CREATE TABLE tx_fourallportal_domain_model_event
(

    uid        int(11)                          NOT NULL auto_increment,
    pid        int(11)              DEFAULT '0' NOT NULL,

    event_id   int(11)              DEFAULT '0' NOT NULL,
    event_type varchar(255)         DEFAULT ''  NOT NULL,
    status     varchar(255)         DEFAULT ''  NOT NULL,
    skip_until int(11)              DEFAULT '0' NOT NULL,
    next_retry int(11)              DEFAULT '0' NOT NULL,
    retries    int(11)              DEFAULT '0' NOT NULL,
    object_id  varchar(255)         DEFAULT ''  NOT NULL,
    module     int(11) unsigned     DEFAULT '0',
    processing smallint(5) unsigned DEFAULT '0' NOT NULL,

    url        text,
    headers    text,
    response   longtext,
    payload    text,
    message    text,

    tstamp     int(11) unsigned     DEFAULT '0' NOT NULL,
    crdate     int(11) unsigned     DEFAULT '0' NOT NULL,
    deleted    smallint(5) unsigned DEFAULT '0' NOT NULL,

    PRIMARY KEY (uid),
    KEY parent (pid),
    KEY event_type (event_type),
    KEY object_id (object_id),
    KEY status (status),
    KEY module (module),
    KEY next_retry (next_retry)

);

#
# Table structure for table 'sys_file'
#
CREATE TABLE sys_file
(
    remote_id varchar(255) DEFAULT '' NOT NULL,
    KEY remote_id (remote_id)
);

#
# Table structure for table 'tx_fourallportal_domain_model_dimension_mapping'
#
CREATE TABLE tx_fourallportal_domain_model_dimensionmapping
(

    uid                int(11)                               NOT NULL auto_increment,
    pid                int(11)              DEFAULT '0'      NOT NULL,

    server             int(11) unsigned     DEFAULT '0'      NOT NULL,
    dimensions         int(11) unsigned     DEFAULT '0'      NOT NULL,

    language           varchar(255)         DEFAULT ''       NOT NULL,
    metric_or_imperial varchar(10)          DEFAULT 'Metric' NOT NULL,
    active             smallint(5) unsigned DEFAULT '1'      NOT NULL,

    tstamp             int(11) unsigned     DEFAULT '0'      NOT NULL,
    crdate             int(11) unsigned     DEFAULT '0'      NOT NULL,

    PRIMARY KEY (uid),
    KEY parent (pid)

);

#
# Table structure for table 'tx_fourallportal_domain_model_dimension'
#
CREATE TABLE tx_fourallportal_domain_model_dimension
(

    uid               int(11)                      NOT NULL auto_increment,
    pid               int(11)          DEFAULT '0' NOT NULL,

    dimension_mapping int(11) unsigned DEFAULT '0' NOT NULL,

    name              varchar(255)     DEFAULT ''  NOT NULL,
    value             varchar(255)     DEFAULT ''  NOT NULL,

    tstamp            int(11) unsigned DEFAULT '0' NOT NULL,
    crdate            int(11) unsigned DEFAULT '0' NOT NULL,

    PRIMARY KEY (uid),
    KEY parent (pid)

);
