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

use Doctrine\DBAL\Schema\Schema as BaseSchema;
use Doctrine\DBAL\Connection;

/**
 * The schema used for the ACL system.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 * @author Daniel Oliveira <daniel@headdev.com.br>
 */
final class Schema extends BaseSchema
{
    protected $options;
    private $schemaStrategy;

    /**
     * Constructor.
     *
     * @param array      $options    the names for tables
     * @param Connection $connection
     */
    public function __construct(array $options, Connection $connection = null)
    {
        $schemaConfig = null === $connection ? null : $connection->getSchemaManager()->createSchemaConfig();

        parent::__construct(array(), array(), $schemaConfig);

        $this->options = $options;
        $databasePlatform = null;

        if ($connection) {
            $databasePlatform = $connection->getDatabasePlatform()->getName();
        }

        switch ($databasePlatform) {
            case 'mssql':
                $this->schemaStrategy = new MssqlTables($this);
            default:
                $this->schemaStrategy = new DefaultTables($this);
        }

        $this->addClassTable();
        $this->addSecurityIdentitiesTable();
        $this->addObjectIdentitiesTable();
        $this->addObjectIdentityAncestorsTable();
        $this->addEntryTable();
    }

    /**
     * Merges ACL schema with the given schema.
     *
     * @param BaseSchema $schema
     */
    public function addToSchema(BaseSchema $schema)
    {
        foreach ($this->getTables() as $table) {
            $schema->_addTable($table);
        }

        foreach ($this->getSequences() as $sequence) {
            $schema->_addSequence($sequence);
        }
    }

    /**
     * Adds the class table to the schema.
     */
    protected function addClassTable()
    {
        $this->schemaStrategy->addClassTable();
    }

    /**
     * Adds the entry table to the schema.
     */
    protected function addEntryTable()
    {
        $this->schemaStrategy->addEntryTable();
    }

    /**
     * Adds the object identity table to the schema.
     */
    protected function addObjectIdentitiesTable()
    {
        $this->schemaStrategy->addObjectIdentitiesTable();
    }

    /**
     * Adds the object identity relation table to the schema.
     */
    protected function addObjectIdentityAncestorsTable()
    {
        $this->schemaStrategy->addObjectIdentityAncestorsTable();
    }

    /**
     * Adds the security identity table to the schema.
     */
    protected function addSecurityIdentitiesTable()
    {
        $this->schemaStrategy->addSecurityIdentitiesTable();
    }

    /**
     * @param $key
     * @return mixed
     */
    public function getOptions($key)
    {
        return $this->options[$key];
    }
}
