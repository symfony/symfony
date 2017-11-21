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

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface;
use Symfony\Component\HttpKernel\DataCollector\RequestDataCollector;
use Symfony\Component\HttpKernel\Profiler\FileProfilerStorage;
use Symfony\Component\HttpKernel\Profiler\Profiler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ProfilerTest extends TestCase
{
    private $tmp;
    private $storage;

    public function testCollect()
    {
        $request = new Request();
        $request->query->set('foo', 'bar');
        $response = new Response('', 204);
        $collector = new RequestDataCollector();

        $profiler = new Profiler($this->storage);
        $profiler->add($collector);
        $profile = $profiler->collect($request, $response);
        $profiler->saveProfile($profile);

        $this->assertSame(204, $profile->getStatusCode());
        $this->assertSame('GET', $profile->getMethod());
        $this->assertSame('bar', $profile->getCollector('request')->getRequestQuery()->all()['foo']->getValue());
    }

    public function testReset()
    {
        $collector = $this->getMockBuilder(DataCollectorInterface::class)
            ->setMethods(['collect', 'getName', 'reset'])
            ->getMock();
        $collector->expects($this->any())->method('getName')->willReturn('mock');
        $collector->expects($this->once())->method('reset');

        $profiler = new Profiler($this->storage);
        $profiler->add($collector);
        $profiler->reset();
    }

    public function testFindWorksWithDates()
    {
        $profiler = new Profiler($this->storage);

        $this->assertCount(0, $profiler->find(null, null, null, null, '7th April 2014', '9th April 2014'));
    }

    public function testFindWorksWithTimestamps()
    {
        $profiler = new Profiler($this->storage);

        $this->assertCount(0, $profiler->find(null, null, null, null, '1396828800', '1397001600'));
    }

    public function testFindWorksWithInvalidDates()
    {
        $profiler = new Profiler($this->storage);

        $this->assertCount(0, $profiler->find(null, null, null, null, 'some string', ''));
    }

    public function testFindWorksWithStatusCode()
    {
        $profiler = new Profiler($this->storage);

        $this->assertCount(0, $profiler->find(null, null, null, null, null, null, '204'));
    }

    protected function setUp()
    {
        $this->tmp = tempnam(sys_get_temp_dir(), 'sf2_profiler');
        if (file_exists($this->tmp)) {
            @unlink($this->tmp);
        }

        $this->storage = new FileProfilerStorage('file:'.$this->tmp);
        $this->storage->purge();
    }

    protected function tearDown()
    {
        if (null !== $this->storage) {
            $this->storage->purge();
            $this->storage = null;

            @unlink($this->tmp);
        }
    }
}
