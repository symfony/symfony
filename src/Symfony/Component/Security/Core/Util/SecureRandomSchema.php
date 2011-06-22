<?php

namespace Symfony\Component\Security\Core\Util;

use Doctrine\DBAL\Schema\Schema;

/**
 * The DBAL schema that will be used if you choose the database-based
 * seed provider.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
final class SecureRandomSchema extends Schema
{
    public function __construct($tableName)
    {
        parent::__construct();

        $table = $this->createTable($tableName);
        $table->addColumn('seed', 'string', array(
            'length'   => 88,
            'not_null' => true,
        ));
        $table->addColumn('updated_at', 'datetime', array(
            'not_null' => true,
        ));
    }

    public function addToSchema(Schema $schema)
    {
        foreach ($this->getTables() as $table) {
            $schema->_addTable($table);
        }
    }
}