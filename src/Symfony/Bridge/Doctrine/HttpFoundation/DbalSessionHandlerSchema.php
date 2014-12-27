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

use Doctrine\DBAL\Schema\Schema;

/**
 * DBAL Session Storage Schema.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 * @author Hugo Hamon <hugohamon@neuf.fr>
 */
final class DbalSessionHandlerSchema extends Schema
{
    /**
     * Constructor.
     *
     * @param string $tableName  The session table name
     * @param string $idColumn   The session id table column
     * @param string $dataColumn The session data table column
     * @param string $timeColumn The session time table column
     */
    public function __construct($tableName = 'sessions', $idColumn = 'sess_id', $dataColumn = 'sess_data', $timeColumn = 'sess_time')
    {
        parent::__construct();

        $this->addSessionTable($tableName, $idColumn, $dataColumn, $timeColumn);
    }

    public function addToSchema(Schema $schema)
    {
        foreach ($this->getTables() as $table) {
            $schema->_addTable($table);
        }
    }

    private function addSessionTable($tableName, $idColumn, $dataColumn, $timeColumn)
    {
        $table = $this->createTable($tableName);
        $table->addColumn($idColumn, 'string');
        $table->addColumn($dataColumn, 'text')->setNotNull(true);
        $table->addColumn($timeColumn, 'integer')->setNotNull(true)->setUnsigned(true);
        $table->setPrimaryKey(array($idColumn));
    }
}
