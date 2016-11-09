<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Acl\Dbal;

/**
 * @author <daniel@headdev.com.br>
 */
class DefaultTables implements TablesInterface
{
    private $schema;

    public function __construct(Schema $schema)
    {
        $this->schema = $schema;
    }

    /**
     * Adds the class table to the schema.
     */
    public function addClassTable()
    {
        $table = $this->schema->createTable($this->schema->getOptions('class_table_name'));
        $table->addColumn('id', 'integer', array('unsigned' => true, 'autoincrement' => 'auto'));
        $table->addColumn('class_type', 'string', array('length' => 200));
        $table->setPrimaryKey(array('id'));
        $table->addUniqueIndex(array('class_type'));
    }

    /**
     * Adds the entry table to the schema.
     */
    public function addEntryTable()
    {
        $table = $this->schema->createTable($this->schema->getOptions('entry_table_name'));

        $table->addColumn('id', 'integer', array('unsigned' => true, 'autoincrement' => 'auto'));
        $table->addColumn('class_id', 'integer', array('unsigned' => true));
        $table->addColumn('object_identity_id', 'integer', array('unsigned' => true, 'notnull' => false));
        $table->addColumn('field_name', 'string', array('length' => 50, 'notnull' => false));
        $table->addColumn('ace_order', 'smallint', array('unsigned' => true));
        $table->addColumn('security_identity_id', 'integer', array('unsigned' => true));
        $table->addColumn('mask', 'integer');
        $table->addColumn('granting', 'boolean');
        $table->addColumn('granting_strategy', 'string', array('length' => 30));
        $table->addColumn('audit_success', 'boolean');
        $table->addColumn('audit_failure', 'boolean');

        $table->setPrimaryKey(array('id'));
        $table->addUniqueIndex(array('class_id', 'object_identity_id', 'field_name', 'ace_order'));
        $table->addIndex(array('class_id', 'object_identity_id', 'security_identity_id'));

        $table->addForeignKeyConstraint($this->schema->getTable($this->schema->getOptions('class_table_name')), array('class_id'), array('id'), array('onDelete' => 'CASCADE', 'onUpdate' => 'CASCADE'));
        $table->addForeignKeyConstraint($this->schema->getTable($this->schema->getOptions('oid_table_name')), array('object_identity_id'), array('id'), array('onDelete' => 'CASCADE', 'onUpdate' => 'CASCADE'));
        $table->addForeignKeyConstraint($this->schema->getTable($this->schema->getOptions('sid_table_name')), array('security_identity_id'), array('id'), array('onDelete' => 'CASCADE', 'onUpdate' => 'CASCADE'));
    }

    /**
     * Adds the object identity table to the schema.
     */
    public function addObjectIdentitiesTable()
    {
        $table = $this->schema->createTable($this->schema->getOptions('oid_table_name'));

        $table->addColumn('id', 'integer', array('unsigned' => true, 'autoincrement' => 'auto'));
        $table->addColumn('class_id', 'integer', array('unsigned' => true));
        $table->addColumn('object_identifier', 'string', array('length' => 100));
        $table->addColumn('parent_object_identity_id', 'integer', array('unsigned' => true, 'notnull' => false));
        $table->addColumn('entries_inheriting', 'boolean');

        $table->setPrimaryKey(array('id'));
        $table->addUniqueIndex(array('object_identifier', 'class_id'));
        $table->addIndex(array('parent_object_identity_id'));

        $table->addForeignKeyConstraint($table, array('parent_object_identity_id'), array('id'));
    }

    /**
     * Adds the object identity relation table to the schema.
     */
    public function addObjectIdentityAncestorsTable()
    {
        $table = $this->schema->createTable($this->schema->getOptions('oid_ancestors_table_name'));

        $table->addColumn('object_identity_id', 'integer', array('unsigned' => true));
        $table->addColumn('ancestor_id', 'integer', array('unsigned' => true));

        $table->setPrimaryKey(array('object_identity_id', 'ancestor_id'));

        $oidTable = $this->schema->getTable($this->schema->getOptions('oid_table_name'));
        $table->addForeignKeyConstraint($oidTable, array('object_identity_id'), array('id'), array('onDelete' => 'CASCADE', 'onUpdate' => 'CASCADE'));
        $table->addForeignKeyConstraint($oidTable, array('ancestor_id'), array('id'), array('onDelete' => 'CASCADE', 'onUpdate' => 'CASCADE'));
    }

    /**
     * Adds the security identity table to the schema.
     */
    public function addSecurityIdentitiesTable()
    {
        $table = $this->schema->createTable($this->schema->getOptions('sid_table_name'));

        $table->addColumn('id', 'integer', array('unsigned' => true, 'autoincrement' => 'auto'));
        $table->addColumn('identifier', 'string', array('length' => 200));
        $table->addColumn('username', 'boolean');

        $table->setPrimaryKey(array('id'));
        $table->addUniqueIndex(array('identifier', 'username'));
    }
}
