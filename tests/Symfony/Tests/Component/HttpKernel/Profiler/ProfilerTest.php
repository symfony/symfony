<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\HttpKernel\Profiler;

use Symfony\Component\HttpKernel\DataCollector\RequestDataCollector;
use Symfony\Component\HttpKernel\Profiler\SqliteProfilerStorage;
use Symfony\Component\HttpKernel\Profiler\Profiler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ProfilerTest extends \PHPUnit_Framework_TestCase
{
    public function testCollect()
    {
        if (!class_exists('SQLite3') && (!class_exists('PDO') || !in_array('sqlite', \PDO::getAvailableDrivers()))) {
            $this->markTestSkipped('This test requires SQLite support in your environment');
        }

        $request = new Request();
        $request->query->set('foo', 'bar');
        $response = new Response();
        $collector = new RequestDataCollector();

        $tmp = tempnam(sys_get_temp_dir(), 'sf2_profiler');
        if (file_exists($tmp)) {
            @unlink($tmp);
        }
        $storage = new SqliteProfilerStorage('sqlite:'.$tmp);
        $storage->purge();

        $profiler = new Profiler($storage);
        $profiler->add($collector);
        $profile = $profiler->collect($request, $response);

        $profile = $profiler->loadProfile($profile->getToken());
        $this->assertEquals(array('foo' => 'bar'), $profiler->get('request')->getRequestQuery()->all());

        @unlink($tmp);
    }
}
