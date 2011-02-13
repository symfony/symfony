CREATE TABLE acl_classes (id INT AUTO_INCREMENT NOT NULL, class_type VARCHAR(200) NOT NULL, UNIQUE INDEX acl_classes_class_type_uniq (class_type), PRIMARY KEY(id)) ENGINE = InnoDB

CREATE TABLE acl_security_identities (id INT AUTO_INCREMENT NOT NULL, identifier VARCHAR(200) NOT NULL, username TINYINT(1) DEFAULT '0' NOT NULL, UNIQUE INDEX ecurity_identities_identifier_username_uniq (identifier, username), PRIMARY KEY(id)) ENGINE = InnoDB

CREATE TABLE acl_object_identities (id INT AUTO_INCREMENT NOT NULL, parent_object_identity_id INT DEFAULT NULL, class_id INT NOT NULL, object_identifier VARCHAR(100) NOT NULL, entries_inheriting TINYINT(1) DEFAULT '0' NOT NULL, UNIQUE INDEX object_identities_object_identifier_class_id_uniq (object_identifier, class_id), INDEX acl_object_identities_parent_object_identity_id_idx (parent_object_identity_id), PRIMARY KEY(id)) ENGINE = InnoDB

CREATE TABLE acl_object_identity_ancestors (object_identity_id INT NOT NULL, ancestor_id INT NOT NULL, INDEX acl_object_identity_ancestors_object_identity_id_idx (object_identity_id), INDEX acl_object_identity_ancestors_ancestor_id_idx (ancestor_id), PRIMARY KEY(object_identity_id, ancestor_id)) ENGINE = InnoDB

CREATE TABLE acl_entries (id INT AUTO_INCREMENT NOT NULL, class_id INT NOT NULL, object_identity_id INT DEFAULT NULL, security_identity_id INT NOT NULL, field_name VARCHAR(50) DEFAULT NULL, ace_order SMALLINT NOT NULL, mask INT NOT NULL, granting TINYINT(1) NOT NULL, granting_strategy VARCHAR(30) NOT NULL, audit_success TINYINT(1) DEFAULT '0' NOT NULL, audit_failure TINYINT(1) DEFAULT '0' NOT NULL, UNIQUE INDEX cl_entries_class_id_dentity_id_field_name_ace_order_uniq (class_id, object_identity_id, field_name, ace_order), INDEX acl_entries_class_id_ct_identity_id_ty_identity_id_idx (class_id, object_identity_id, security_identity_id), INDEX acl_entries_class_id_idx (class_id), INDEX acl_entries_object_identity_id_idx (object_identity_id), INDEX acl_entries_security_identity_id_idx (security_identity_id), PRIMARY KEY(id)) ENGINE = InnoDB

ALTER TABLE acl_object_identities ADD CONSTRAINT acl_object_identities_parent_object_identity_id_fk FOREIGN KEY (parent_object_identity_id) REFERENCES acl_object_identities(id) ON UPDATE RESTRICT ON DELETE RESTRICT

ALTER TABLE acl_object_identity_ancestors ADD CONSTRAINT acl_object_identity_ancestors_object_identity_id_fk FOREIGN KEY (object_identity_id) REFERENCES acl_object_identities(id) ON UPDATE CASCADE ON DELETE CASCADE

ALTER TABLE acl_object_identity_ancestors ADD CONSTRAINT acl_object_identity_ancestors_ancestor_id_fk FOREIGN KEY (ancestor_id) REFERENCES acl_object_identities(id) ON UPDATE CASCADE ON DELETE CASCADE

ALTER TABLE acl_entries ADD CONSTRAINT acl_entries_class_id_fk FOREIGN KEY (class_id) REFERENCES acl_classes(id) ON UPDATE CASCADE ON DELETE CASCADE

ALTER TABLE acl_entries ADD CONSTRAINT acl_entries_object_identity_id_fk FOREIGN KEY (object_identity_id) REFERENCES acl_object_identities(id) ON UPDATE CASCADE ON DELETE CASCADE

ALTER TABLE acl_entries ADD CONSTRAINT acl_entries_security_identity_id_fk FOREIGN KEY (security_identity_id) REFERENCES acl_security_identities(id) ON UPDATE CASCADE ON DELETE CASCADE