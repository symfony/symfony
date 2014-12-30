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

        switch ($connection->getDatabasePlatform()->getName()) {

            case 'mssql':
                $schemaStrategy = new MssqlTables($this);
                break;
            default:
                $schemaStrategy = new DefaultTables($this);
                break;
        }

        $schemaStrategy->addClassTable();
        $schemaStrategy->addSecurityIdentitiesTable();
        $schemaStrategy->addObjectIdentitiesTable();
        $schemaStrategy->addObjectIdentityAncestorsTable();
        $schemaStrategy->addEntryTable();
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
     * @param $key
     * @return mixed
     */
    public function getOptions($key)
    {
        return $this->options[$key];
    }
}
