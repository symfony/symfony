CREATE TABLE acl_classes (id NUMBER(10) NOT NULL, class_type VARCHAR2(200) NOT NULL, PRIMARY KEY(id))

DECLARE
  constraints_Count NUMBER;
BEGIN
  SELECT COUNT(CONSTRAINT_NAME) INTO constraints_Count FROM USER_CONSTRAINTS WHERE TABLE_NAME = 'ACL_CLASSES' AND CONSTRAINT_TYPE = 'P';
  IF constraints_Count = 0 OR constraints_Count = '' THEN
    EXECUTE IMMEDIATE 'ALTER TABLE ACL_CLASSES ADD CONSTRAINT ACL_CLASSES_AI_PK PRIMARY KEY (id)';
  END IF;
END;

CREATE SEQUENCE ACL_CLASSES_SEQ START WITH 1 MINVALUE 1 INCREMENT BY 1

CREATE TRIGGER ACL_CLASSES_AI_PK
   BEFORE INSERT
   ON ACL_CLASSES
   FOR EACH ROW
DECLARE
   last_Sequence NUMBER;
   last_InsertID NUMBER;
BEGIN
   SELECT ACL_CLASSES_SEQ.NEXTVAL INTO :NEW.id FROM DUAL;
   IF (:NEW.id IS NULL OR :NEW.id = 0) THEN
      SELECT ACL_CLASSES_SEQ.NEXTVAL INTO :NEW.id FROM DUAL;
   ELSE
      SELECT NVL(Last_Number, 0) INTO last_Sequence
        FROM User_Sequences
       WHERE Sequence_Name = 'ACL_CLASSES_SEQ';
      SELECT :NEW.id INTO last_InsertID FROM DUAL;
      WHILE (last_InsertID > last_Sequence) LOOP
         SELECT ACL_CLASSES_SEQ.NEXTVAL INTO last_Sequence FROM DUAL;
      END LOOP;
   END IF;
END;

CREATE UNIQUE INDEX acl_classes_class_type_uniq ON acl_classes (class_type)

CREATE TABLE acl_security_identities (id NUMBER(10) NOT NULL, identifier VARCHAR2(200) NOT NULL, username NUMBER(1) DEFAULT '0' NOT NULL, PRIMARY KEY(id))

DECLARE
  constraints_Count NUMBER;
BEGIN
  SELECT COUNT(CONSTRAINT_NAME) INTO constraints_Count FROM USER_CONSTRAINTS WHERE TABLE_NAME = 'ACL_SECURITY_IDENTITIES' AND CONSTRAINT_TYPE = 'P';
  IF constraints_Count = 0 OR constraints_Count = '' THEN
    EXECUTE IMMEDIATE 'ALTER TABLE ACL_SECURITY_IDENTITIES ADD CONSTRAINT ACL_SECURITY_IDENTITIES_AI_PK PRIMARY KEY (id)';
  END IF;
END;

CREATE SEQUENCE ACL_SECURITY_IDENTITIES_SEQ START WITH 1 MINVALUE 1 INCREMENT BY 1

CREATE TRIGGER ACL_SECURITY_IDENTITIES_AI_PK
   BEFORE INSERT
   ON ACL_SECURITY_IDENTITIES
   FOR EACH ROW
DECLARE
   last_Sequence NUMBER;
   last_InsertID NUMBER;
BEGIN
   SELECT ACL_SECURITY_IDENTITIES_SEQ.NEXTVAL INTO :NEW.id FROM DUAL;
   IF (:NEW.id IS NULL OR :NEW.id = 0) THEN
      SELECT ACL_SECURITY_IDENTITIES_SEQ.NEXTVAL INTO :NEW.id FROM DUAL;
   ELSE
      SELECT NVL(Last_Number, 0) INTO last_Sequence
        FROM User_Sequences
       WHERE Sequence_Name = 'ACL_SECURITY_IDENTITIES_SEQ';
      SELECT :NEW.id INTO last_InsertID FROM DUAL;
      WHILE (last_InsertID > last_Sequence) LOOP
         SELECT ACL_SECURITY_IDENTITIES_SEQ.NEXTVAL INTO last_Sequence FROM DUAL;
      END LOOP;
   END IF;
END;

CREATE UNIQUE INDEX ecurity_identities_identifier_username_uniq ON acl_security_identities (identifier, username)

CREATE TABLE acl_object_identities (id NUMBER(10) NOT NULL, parent_object_identity_id NUMBER(10) DEFAULT NULL, class_id NUMBER(10) NOT NULL, object_identifier VARCHAR2(100) NOT NULL, entries_inheriting NUMBER(1) DEFAULT '0' NOT NULL, PRIMARY KEY(id))

DECLARE
  constraints_Count NUMBER;
BEGIN
  SELECT COUNT(CONSTRAINT_NAME) INTO constraints_Count FROM USER_CONSTRAINTS WHERE TABLE_NAME = 'ACL_OBJECT_IDENTITIES' AND CONSTRAINT_TYPE = 'P';
  IF constraints_Count = 0 OR constraints_Count = '' THEN
    EXECUTE IMMEDIATE 'ALTER TABLE ACL_OBJECT_IDENTITIES ADD CONSTRAINT ACL_OBJECT_IDENTITIES_AI_PK PRIMARY KEY (id)';
  END IF;
END;

CREATE SEQUENCE ACL_OBJECT_IDENTITIES_SEQ START WITH 1 MINVALUE 1 INCREMENT BY 1

CREATE TRIGGER ACL_OBJECT_IDENTITIES_AI_PK
   BEFORE INSERT
   ON ACL_OBJECT_IDENTITIES
   FOR EACH ROW
DECLARE
   last_Sequence NUMBER;
   last_InsertID NUMBER;
BEGIN
   SELECT ACL_OBJECT_IDENTITIES_SEQ.NEXTVAL INTO :NEW.id FROM DUAL;
   IF (:NEW.id IS NULL OR :NEW.id = 0) THEN
      SELECT ACL_OBJECT_IDENTITIES_SEQ.NEXTVAL INTO :NEW.id FROM DUAL;
   ELSE
      SELECT NVL(Last_Number, 0) INTO last_Sequence
        FROM User_Sequences
       WHERE Sequence_Name = 'ACL_OBJECT_IDENTITIES_SEQ';
      SELECT :NEW.id INTO last_InsertID FROM DUAL;
      WHILE (last_InsertID > last_Sequence) LOOP
         SELECT ACL_OBJECT_IDENTITIES_SEQ.NEXTVAL INTO last_Sequence FROM DUAL;
      END LOOP;
   END IF;
END;

CREATE UNIQUE INDEX object_identities_object_identifier_class_id_uniq ON acl_object_identities (object_identifier, class_id)

CREATE INDEX acl_object_identities_parent_object_identity_id_idx ON acl_object_identities (parent_object_identity_id)

CREATE TABLE acl_object_identity_ancestors (object_identity_id NUMBER(10) NOT NULL, ancestor_id NUMBER(10) NOT NULL, PRIMARY KEY(object_identity_id, ancestor_id))

CREATE INDEX acl_object_identity_ancestors_object_identity_id_idx ON acl_object_identity_ancestors (object_identity_id)

CREATE INDEX acl_object_identity_ancestors_ancestor_id_idx ON acl_object_identity_ancestors (ancestor_id)

CREATE TABLE acl_entries (id NUMBER(10) NOT NULL, class_id NUMBER(10) NOT NULL, object_identity_id NUMBER(10) DEFAULT NULL, security_identity_id NUMBER(10) NOT NULL, field_name VARCHAR2(50) DEFAULT NULL, ace_order NUMBER(5) NOT NULL, mask NUMBER(10) NOT NULL, granting NUMBER(1) NOT NULL, granting_strategy VARCHAR2(30) NOT NULL, audit_success NUMBER(1) DEFAULT '0' NOT NULL, audit_failure NUMBER(1) DEFAULT '0' NOT NULL, PRIMARY KEY(id))

DECLARE
  constraints_Count NUMBER;
BEGIN
  SELECT COUNT(CONSTRAINT_NAME) INTO constraints_Count FROM USER_CONSTRAINTS WHERE TABLE_NAME = 'ACL_ENTRIES' AND CONSTRAINT_TYPE = 'P';
  IF constraints_Count = 0 OR constraints_Count = '' THEN
    EXECUTE IMMEDIATE 'ALTER TABLE ACL_ENTRIES ADD CONSTRAINT ACL_ENTRIES_AI_PK PRIMARY KEY (id)';
  END IF;
END;

CREATE SEQUENCE ACL_ENTRIES_SEQ START WITH 1 MINVALUE 1 INCREMENT BY 1

CREATE TRIGGER ACL_ENTRIES_AI_PK
   BEFORE INSERT
   ON ACL_ENTRIES
   FOR EACH ROW
DECLARE
   last_Sequence NUMBER;
   last_InsertID NUMBER;
BEGIN
   SELECT ACL_ENTRIES_SEQ.NEXTVAL INTO :NEW.id FROM DUAL;
   IF (:NEW.id IS NULL OR :NEW.id = 0) THEN
      SELECT ACL_ENTRIES_SEQ.NEXTVAL INTO :NEW.id FROM DUAL;
   ELSE
      SELECT NVL(Last_Number, 0) INTO last_Sequence
        FROM User_Sequences
       WHERE Sequence_Name = 'ACL_ENTRIES_SEQ';
      SELECT :NEW.id INTO last_InsertID FROM DUAL;
      WHILE (last_InsertID > last_Sequence) LOOP
         SELECT ACL_ENTRIES_SEQ.NEXTVAL INTO last_Sequence FROM DUAL;
      END LOOP;
   END IF;
END;

CREATE UNIQUE INDEX cl_entries_class_id_dentity_id_field_name_ace_order_uniq ON acl_entries (class_id, object_identity_id, field_name, ace_order)

CREATE INDEX acl_entries_class_id_ct_identity_id_ty_identity_id_idx ON acl_entries (class_id, object_identity_id, security_identity_id)

CREATE INDEX acl_entries_class_id_idx ON acl_entries (class_id)

CREATE INDEX acl_entries_object_identity_id_idx ON acl_entries (object_identity_id)

CREATE INDEX acl_entries_security_identity_id_idx ON acl_entries (security_identity_id)

ALTER TABLE acl_object_identities ADD CONSTRAINT acl_object_identities_parent_object_identity_id_fk FOREIGN KEY (parent_object_identity_id) REFERENCES acl_object_identities(id) ON DELETE RESTRICT

ALTER TABLE acl_object_identity_ancestors ADD CONSTRAINT acl_object_identity_ancestors_object_identity_id_fk FOREIGN KEY (object_identity_id) REFERENCES acl_object_identities(id) ON DELETE CASCADE

ALTER TABLE acl_object_identity_ancestors ADD CONSTRAINT acl_object_identity_ancestors_ancestor_id_fk FOREIGN KEY (ancestor_id) REFERENCES acl_object_identities(id) ON DELETE CASCADE

ALTER TABLE acl_entries ADD CONSTRAINT acl_entries_class_id_fk FOREIGN KEY (class_id) REFERENCES acl_classes(id) ON DELETE CASCADE

ALTER TABLE acl_entries ADD CONSTRAINT acl_entries_object_identity_id_fk FOREIGN KEY (object_identity_id) REFERENCES acl_object_identities(id) ON DELETE CASCADE

ALTER TABLE acl_entries ADD CONSTRAINT acl_entries_security_identity_id_fk FOREIGN KEY (security_identity_id) REFERENCES acl_security_identities(id) ON DELETE CASCADE