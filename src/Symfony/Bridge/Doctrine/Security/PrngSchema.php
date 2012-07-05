<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Security;

use Doctrine\DBAL\Schema\Schema;

/**
 * The DBAL schema that will be used if you choose the database-based seed provider.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
final class PrngSchema extends Schema
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
