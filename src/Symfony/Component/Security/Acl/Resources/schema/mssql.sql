CREATE TABLE acl_classes (id INT IDENTITY NOT NULL, class_type NVARCHAR(200) NOT NULL, PRIMARY KEY(id))

CREATE UNIQUE INDEX acl_classes_class_type_uniq ON acl_classes (class_type) WHERE class_type IS NOT NULL

CREATE TABLE acl_security_identities (id INT IDENTITY NOT NULL, identifier NVARCHAR(200) NOT NULL, username BIT DEFAULT '0' NOT NULL, PRIMARY KEY(id))

CREATE UNIQUE INDEX ecurity_identities_identifier_username_uniq ON acl_security_identities (identifier, username) WHERE identifier IS NOT NULL AND username IS NOT NULL

CREATE TABLE acl_object_identities (id INT IDENTITY NOT NULL, parent_object_identity_id INT DEFAULT NULL, class_id INT NOT NULL, object_identifier NVARCHAR(100) NOT NULL, entries_inheriting BIT DEFAULT '0' NOT NULL, PRIMARY KEY(id))

CREATE UNIQUE INDEX object_identities_object_identifier_class_id_uniq ON acl_object_identities (object_identifier, class_id) WHERE object_identifier IS NOT NULL AND class_id IS NOT NULL

CREATE INDEX acl_object_identities_parent_object_identity_id_idx ON acl_object_identities (parent_object_identity_id)

CREATE TABLE acl_object_identity_ancestors (object_identity_id INT NOT NULL, ancestor_id INT NOT NULL, PRIMARY KEY(object_identity_id, ancestor_id))

CREATE INDEX acl_object_identity_ancestors_object_identity_id_idx ON acl_object_identity_ancestors (object_identity_id)

CREATE INDEX acl_object_identity_ancestors_ancestor_id_idx ON acl_object_identity_ancestors (ancestor_id)

CREATE TABLE acl_entries (id INT IDENTITY NOT NULL, class_id INT NOT NULL, object_identity_id INT DEFAULT NULL, security_identity_id INT NOT NULL, field_name NVARCHAR(50) DEFAULT NULL, ace_order SMALLINT NOT NULL, mask INT NOT NULL, granting BIT NOT NULL, granting_strategy NVARCHAR(30) NOT NULL, audit_success BIT DEFAULT '0' NOT NULL, audit_failure BIT DEFAULT '0' NOT NULL, PRIMARY KEY(id))

CREATE UNIQUE INDEX cl_entries_class_id_dentity_id_field_name_ace_order_uniq ON acl_entries (class_id, object_identity_id, field_name, ace_order) WHERE class_id IS NOT NULL AND object_identity_id IS NOT NULL AND field_name IS NOT NULL AND ace_order IS NOT NULL

CREATE INDEX acl_entries_class_id_ct_identity_id_ty_identity_id_idx ON acl_entries (class_id, object_identity_id, security_identity_id)

CREATE INDEX acl_entries_class_id_idx ON acl_entries (class_id)

CREATE INDEX acl_entries_object_identity_id_idx ON acl_entries (object_identity_id)

CREATE INDEX acl_entries_security_identity_id_idx ON acl_entries (security_identity_id)

ALTER TABLE acl_object_identities ADD CONSTRAINT acl_object_identities_parent_object_identity_id_fk FOREIGN KEY (parent_object_identity_id) REFERENCES acl_object_identities(id) ON UPDATE RESTRICT ON DELETE RESTRICT

ALTER TABLE acl_object_identity_ancestors ADD CONSTRAINT acl_object_identity_ancestors_object_identity_id_fk FOREIGN KEY (object_identity_id) REFERENCES acl_object_identities(id) ON UPDATE CASCADE ON DELETE CASCADE

ALTER TABLE acl_object_identity_ancestors ADD CONSTRAINT acl_object_identity_ancestors_ancestor_id_fk FOREIGN KEY (ancestor_id) REFERENCES acl_object_identities(id) ON UPDATE CASCADE ON DELETE CASCADE

ALTER TABLE acl_entries ADD CONSTRAINT acl_entries_class_id_fk FOREIGN KEY (class_id) REFERENCES acl_classes(id) ON UPDATE CASCADE ON DELETE CASCADE

ALTER TABLE acl_entries ADD CONSTRAINT acl_entries_object_identity_id_fk FOREIGN KEY (object_identity_id) REFERENCES acl_object_identities(id) ON UPDATE CASCADE ON DELETE CASCADE

ALTER TABLE acl_entries ADD CONSTRAINT acl_entries_security_identity_id_fk FOREIGN KEY (security_identity_id) REFERENCES acl_security_identities(id) ON UPDATE CASCADE ON DELETE CASCADE