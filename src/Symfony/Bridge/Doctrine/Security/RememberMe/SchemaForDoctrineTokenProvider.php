<?php

namespace Symfony\Bridge\Doctrine\Security\RememberMe;

use Doctrine\DBAL\Schema\Schema;

$schema = new Schema();
$rememberTable = $schema->createTable('rememberme_token');
$rememberTable->addColumn('series', "string", array('Length' => 88,
                                                    'Notnull' => true));
$rememberTable->addColumn('value', "string", array('Length' => 88,
                                                   'Notnull' => true));

$rememberTable->addColumn('lastUsed', 'datetime', array('Notnull' => true));

$rememberTable->addColumn('class', 'string', array('Length' => 100,
                                                   'Notnull' => true));
$rememberTable->addColumn('username', 'string', array('Length' => 200,
                                                      'Notnull' => true));

$rememberTable->setPrimaryKey(array('series'));
$rememberTable->addUniqueIndex(array('series'));


$queries = $schema->toSql($myPlatform); // get queries to create this schema.
