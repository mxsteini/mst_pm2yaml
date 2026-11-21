CREATE TABLE tx_powermail_domain_model_form (
    tx_mstpm2yaml_file varchar(255) DEFAULT '' NOT NULL,
    # Änderungszeit der Datei beim letzten Abgleich (Import oder Schreiben); weicht sie ab, wird importiert
    tx_mstpm2yaml_synced int(11) unsigned DEFAULT '0' NOT NULL
);

CREATE TABLE tx_powermail_domain_model_page (
    tx_mstpm2yaml_identifier varchar(255) DEFAULT '' NOT NULL
);
