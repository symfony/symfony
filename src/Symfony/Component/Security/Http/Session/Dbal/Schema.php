<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Session\Dbal;

use Doctrine\DBAL\Schema\Schema as BaseSchema;

/**
 * The schema used for the ACL system.
 *
 * @author Stefan Paschke <stefan.paschke@gmail.com>
 */
final class Schema extends BaseSchema
{
    protected $options;

    /**
     * Constructor
     *
     * @param array $options the names for tables
     */
    public function __construct(array $options)
    {
        parent::__construct();

        $this->options = $options;

        $this->addSessionInformationTable();
    }

    /**
     * Adds the session_information table to the schema
     */
    protected function addSessionInformationTable()
    {
        $table = $this->createTable($this->options['session_information_table_name']);
        $table->addColumn('session_id', 'string');
        $table->addColumn('username', 'string');
        $table->addColumn('expired', 'datetime', array('unsigned' => true, 'notnull' => false));
        $table->addColumn('last_request', 'datetime', array('unsigned' => true, 'notnull' => false));
        $table->setPrimaryKey(array('session_id'));
        $table->addUniqueIndex(array('session_id'));
    }
}
