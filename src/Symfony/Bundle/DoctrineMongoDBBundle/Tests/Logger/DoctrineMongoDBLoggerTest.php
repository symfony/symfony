<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\DoctrineMongoDBBundle\Tests\Logger;

use Symfony\Bundle\DoctrineMongoDBBundle\Logger\DoctrineMongoDBLogger;

class DoctrineMongoDBLoggerTest extends \PHPUnit_Framework_TestCase
{
    protected $logger;

    protected function setUp()
    {
        $this->logger = new DoctrineMongoDBLogger();
    }

    /**
     * @dataProvider getQueries
     */
    public function testLogger($query, $formatted)
    {
        $this->logger->logQuery($query);

        $this->assertEquals($formatted, $this->logger->getQueries());
    }

    public function getQueries()
    {
        return array(
            // batchInsert
            array(
                array('db' => 'foo', 'collection' => 'bar', 'batchInsert' => true, 'num' => 1, 'data' => array('foo'), 'options' => array()),
                array('use foo;', 'db.bar.batchInsert(**1 item(s)**);'),
            ),
            // find
            array(
                array('db' => 'foo', 'collection' => 'bar', 'find' => true, 'query' => array('foo' => null), 'fields' => array()),
                array('use foo;', 'db.bar.find({ "foo": null });'),
            ),
        );
    }
}
