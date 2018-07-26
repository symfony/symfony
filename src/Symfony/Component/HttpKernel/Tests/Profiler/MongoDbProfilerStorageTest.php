<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\Profiler;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\HttpKernel\Profiler\MongoDbProfilerStorage;
use Symfony\Component\HttpKernel\Profiler\Profile;

class MongoDbProfilerStorageTestDataCollector extends DataCollector
{
    public function setData($data)
    {
        $this->data = $data;
    }

    public function getData()
    {
        return $this->data;
    }

    public function collect(Request $request, Response $response, \Exception $exception = null)
    {
    }

    public function getName()
    {
        return 'test_data_collector';
    }
}

/**
 * @group legacy
 * @requires extension mongo
 */
class MongoDbProfilerStorageTest extends AbstractProfilerStorageTest
{
    private $storage;

    public function getDsns()
    {
        return array(
            array('mongodb://localhost/symfony_tests/profiler_data', array(
                'mongodb://localhost/symfony_tests',
                'symfony_tests',
                'profiler_data',
            )),
            array('mongodb://user:password@localhost/symfony_tests/profiler_data', array(
                'mongodb://user:password@localhost/symfony_tests',
                'symfony_tests',
                'profiler_data',
            )),
            array('mongodb://user:password@localhost/admin/symfony_tests/profiler_data', array(
                'mongodb://user:password@localhost/admin',
                'symfony_tests',
                'profiler_data',
            )),
            array('mongodb://user:password@localhost:27009,localhost:27010/?replicaSet=rs-name&authSource=admin/symfony_tests/profiler_data', array(
                'mongodb://user:password@localhost:27009,localhost:27010/?replicaSet=rs-name&authSource=admin',
                'symfony_tests',
                'profiler_data',
            )),
        );
    }

    public function testCleanup()
    {
        $dt = new \DateTime('-2 day');
        for ($i = 0; $i < 3; ++$i) {
            $dt->modify('-1 day');
            $profile = new Profile('time_'.$i);
            $profile->setTime($dt->getTimestamp());
            $profile->setMethod('GET');
            $this->storage->write($profile);
        }
        $records = $this->storage->find('', '', 3, 'GET');
        $this->assertCount(1, $records, '->find() returns only one record');
        $this->assertEquals($records[0]['token'], 'time_2', '->find() returns the latest added record');
        $this->storage->purge();
    }

    /**
     * @dataProvider getDsns
     */
    public function testDsnParser($dsn, $expected)
    {
        $m = new \ReflectionMethod($this->storage, 'parseDsn');
        $m->setAccessible(true);

        $this->assertEquals($expected, $m->invoke($this->storage, $dsn));
    }

    public function testUtf8()
    {
        $profile = new Profile('utf8_test_profile');

        $data = 'HЁʃʃϿ, ϢorЃd!';
        $nonUtf8Data = iconv('UTF-8', 'UCS-2', $data);

        $collector = new MongoDbProfilerStorageTestDataCollector();
        $collector->setData($nonUtf8Data);

        $profile->setCollectors(array($collector));

        $this->storage->write($profile);

        $readProfile = $this->storage->read('utf8_test_profile');
        $collectors = $readProfile->getCollectors();

        $this->assertCount(1, $collectors);
        $this->assertArrayHasKey('test_data_collector', $collectors);
        $this->assertEquals($nonUtf8Data, $collectors['test_data_collector']->getData(), 'Non-UTF8 data is properly encoded/decoded');
    }

    /**
     * @return \Symfony\Component\HttpKernel\Profiler\ProfilerStorageInterface
     */
    protected function getStorage()
    {
        return $this->storage;
    }

    protected function setUp()
    {
        $this->storage = new MongoDbProfilerStorage('mongodb://localhost/symfony_tests/profiler_data', '', '', 86400);
        $m = new \ReflectionMethod($this->storage, 'getMongo');
        $m->setAccessible(true);
        try {
            $m->invoke($this->storage);
        } catch (\MongoConnectionException $e) {
            $this->markTestSkipped('A MongoDB server on localhost is required.');
        }

        $this->storage->purge();
    }

    protected function tearDown()
    {
        $this->storage->purge();
    }
}
