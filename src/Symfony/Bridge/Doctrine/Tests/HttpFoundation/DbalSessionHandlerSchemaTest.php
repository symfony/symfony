<?php

namespace Symfony\Bridge\Doctrine\Tests\HttpFoundation;

use Symfony\Bridge\Doctrine\HttpFoundation\DbalSessionHandlerSchema;

class DbalSessionHandlerSchemaTest extends \PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        if (!class_exists('Doctrine\DBAL\Schema\Schema')) {
            $this->markTestSkipped('The doctrine/dbal package dependency is required.');
        }
    }

    public function testCreateDefaultDbalSessionHandlerSchema()
    {
        $schema = new DbalSessionHandlerSchema();

        $this->assertTrue($schema->hasTable('sessions'));
        $this->assertTrue($schema->getTable('sessions')->hasColumn('sess_id'));
        $this->assertTrue($schema->getTable('sessions')->hasColumn('sess_data'));
        $this->assertTrue($schema->getTable('sessions')->hasColumn('sess_time'));
    }

    public function testCreateCustomDbalSessionHandlerSchema()
    {
        $schema = new DbalSessionHandlerSchema('sf_sessions', 'id', 'data', 'time');

        $this->assertTrue($schema->hasTable('sf_sessions'));
        $this->assertTrue($schema->getTable('sf_sessions')->hasColumn('id'));
        $this->assertTrue($schema->getTable('sf_sessions')->hasColumn('data'));
        $this->assertTrue($schema->getTable('sf_sessions')->hasColumn('time'));
    }
}
