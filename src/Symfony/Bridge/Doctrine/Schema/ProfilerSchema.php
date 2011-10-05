<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Schema;

use Doctrine\DBAL\Schema\Schema as BaseSchema;

/**
 * The schema used for the WebProfiler.
 *
 * @author Joseph Bielawski <stloyd@gmail.com>
 */
final class ProfilerSchema extends BaseSchema
{
    protected $tableName;

    /**
     * Constructor.
     *
     * @param string $tableName the name for profiler table
     */
    public function __construct($tableName)
    {
        parent::__construct();
        
        $this->tableName = $tableName;
    }

    /**
     * Adds the table to the schema
     *
     * @return \Doctrine\DBAL\Schema\Schema
     */
    public function createNewTable()
    {
        $table = $this->createTable($this->tableName);

        $table->addColumn('token', 'string', array('length' => 255));
        $table->addColumn('data', 'text');
        $table->addColumn('ip', 'string', array('length' => 64));
        $table->addColumn('method', 'string', array('length' => 6));
        $table->addColumn('url', 'string', array('length' => 255));
        $table->addColumn('time', 'integer', array('unsigned' => true));
        $table->addColumn('parent', 'string', array('length' => 255, 'notnull' => false));
        $table->addColumn('created_at', 'integer', array('unsigned' => true));

        $table->setPrimaryKey(array('token'));
        $table->addIndex(array('ip', 'method', 'url', 'parent', 'created_at'));

        return $table;
    }

    /**
     * Returns the table in old format
     *
     * @return \Doctrine\DBAL\Schema\Schema
     */
    protected function createOldTable()
    {
        $table = $this->createTable($this->tableName);

        $table->addColumn('token', 'string', array('length' => 255));
        $table->addColumn('data', 'text');
        $table->addColumn('ip', 'string', array('length' => 64));
        $table->addColumn('url', 'string', array('length' => 255));
        $table->addColumn('time', 'integer', array('unsigned' => true));
        $table->addColumn('parent', 'string', array('length' => 255, 'notnull' => false));
        $table->addColumn('created_at', 'integer', array('unsigned' => true));

        $table->setPrimaryKey(array('token'));
        $table->addIndex(array('ip', 'url', 'parent', 'created_at'));

        return $table;
    }
}