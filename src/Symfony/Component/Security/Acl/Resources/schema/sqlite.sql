CREATE TABLE acl_classes (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, class_type VARCHAR(200) NOT NULL)

CREATE UNIQUE INDEX acl_classes_class_type_uniq ON acl_classes (class_type)

CREATE TABLE acl_security_identities (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, identifier VARCHAR(200) NOT NULL, username BOOLEAN DEFAULT '0' NOT NULL)

CREATE UNIQUE INDEX ecurity_identities_identifier_username_uniq ON acl_security_identities (identifier, username)

CREATE TABLE acl_object_identities (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, parent_object_identity_id INTEGER DEFAULT NULL, class_id INTEGER NOT NULL, object_identifier VARCHAR(100) NOT NULL, entries_inheriting BOOLEAN DEFAULT '0' NOT NULL)

CREATE UNIQUE INDEX object_identities_object_identifier_class_id_uniq ON acl_object_identities (object_identifier, class_id)

CREATE INDEX acl_object_identities_parent_object_identity_id_idx ON acl_object_identities (parent_object_identity_id)

CREATE TABLE acl_object_identity_ancestors (object_identity_id INTEGER NOT NULL, ancestor_id INTEGER NOT NULL, PRIMARY KEY("object_identity_id", "ancestor_id"))

CREATE INDEX acl_object_identity_ancestors_object_identity_id_idx ON acl_object_identity_ancestors (object_identity_id)

CREATE INDEX acl_object_identity_ancestors_ancestor_id_idx ON acl_object_identity_ancestors (ancestor_id)

CREATE TABLE acl_entries (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, class_id INTEGER NOT NULL, object_identity_id INTEGER DEFAULT NULL, security_identity_id INTEGER NOT NULL, field_name VARCHAR(50) DEFAULT NULL, ace_order INTEGER NOT NULL, mask INTEGER NOT NULL, granting BOOLEAN NOT NULL, granting_strategy VARCHAR(30) NOT NULL, audit_success BOOLEAN DEFAULT '0' NOT NULL, audit_failure BOOLEAN DEFAULT '0' NOT NULL)

CREATE UNIQUE INDEX cl_entries_class_id_dentity_id_field_name_ace_order_uniq ON acl_entries (class_id, object_identity_id, field_name, ace_order)

CREATE INDEX acl_entries_class_id_ct_identity_id_ty_identity_id_idx ON acl_entries (class_id, object_identity_id, security_identity_id)

CREATE INDEX acl_entries_class_id_idx ON acl_entries (class_id)

CREATE INDEX acl_entries_object_identity_id_idx ON acl_entries (object_identity_id)

CREATE INDEX acl_entries_security_identity_id_idx ON acl_entries (security_identity_id)