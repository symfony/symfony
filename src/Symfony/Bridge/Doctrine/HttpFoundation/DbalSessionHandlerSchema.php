<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\HttpFoundation;

@trigger_error(sprintf('The class %s is deprecated since Symfony 3.4 and will be removed in 4.0. Use Symfony\Component\HttpFoundation\Session\Storage\Handler\PdoSessionHandler::createTable instead.', DbalSessionHandlerSchema::class), E_USER_DEPRECATED);

use Doctrine\DBAL\Schema\Schema;

/**
 * DBAL Session Storage Schema.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 *
 * @deprecated since version 3.4, to be removed in 4.0. Use Symfony\Component\HttpFoundation\Session\Storage\Handler\PdoSessionHandler::createTable instead.
 */
final class DbalSessionHandlerSchema extends Schema
{
    public function __construct($tableName = 'sessions')
    {
        parent::__construct();

        $this->addSessionTable($tableName);
    }

    public function addToSchema(Schema $schema)
    {
        foreach ($this->getTables() as $table) {
            $schema->_addTable($table);
        }
    }

    private function addSessionTable($tableName)
    {
        $table = $this->createTable($tableName);
        $table->addColumn('sess_id', 'string');
        $table->addColumn('sess_data', 'text')->setNotNull(true);
        $table->addColumn('sess_time', 'integer')->setNotNull(true)->setUnsigned(true);
        $table->setPrimaryKey(array('sess_id'));
    }
}
