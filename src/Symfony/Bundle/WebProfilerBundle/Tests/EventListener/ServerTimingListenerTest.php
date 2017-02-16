<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\WebProfilerBundle\Tests\EventListener;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\WebProfilerBundle\EventListener\ServerTimingListener;
use Symfony\Bundle\WebProfilerBundle\EventListener\WebDebugToolbarListener;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Stopwatch\Stopwatch;

/**
 * @group time-sensitive
 */
class ServerTimingListenerTest extends TestCase
{
    /**
     * @dataProvider getInjectHeaderTests
     */
    public function testInjectHeader(array $events, $expected)
    {
        $stopWatch = new Stopwatch();
        $listener = new ServerTimingListener($stopWatch);

        $request = new Request();
        $response = new Response();

        foreach ($events as list($category, $duration)) {
            $stopWatch->start($category, $category);
            \usleep($duration * 1000);
            $stopWatch->stop($category);
        }

        $listener->onKernelResponse(new FilterResponseEvent($this->getMockBuilder(KernelInterface::class)->getMock(), $request, HttpKernelInterface::MASTER_REQUEST, $response));

        $this->assertEquals($expected, $response->headers->get('Server-Timing'));
    }

    public function getInjectHeaderTests()
    {
        return array(
            array(array(), ''),
            array(array(array('foo', 10)), '000-foo;dur=10.000;desc="foo"'),
            array(array(array('foo', 10), array('bar', 20)), '000-bar;dur=20.000;desc="bar",001-foo;dur=10.000;desc="foo"'),
            array(array(array('foo', 10), array('bar', 20), array('foo', 5)), '000-bar;dur=20.000;desc="bar",001-foo;dur=15.000;desc="foo"'),
        );
    }
}
