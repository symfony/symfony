<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Controller;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ControllerResolverInterface;
use Symfony\Component\HttpKernel\Controller\TraceableControllerResolver;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\Stopwatch\StopwatchEvent;

class TraceableControllerResolverTest extends TestCase
{
    public function testStopwatchEventIsStoppedWhenResolverThrows()
    {
        $stopwatchEvent = $this->createMock(StopwatchEvent::class);
        $stopwatchEvent->expects(self::once())->method('stop');

        $stopwatch = $this->createStub(Stopwatch::class);
        $stopwatch->method('start')->willReturn($stopwatchEvent);

        $resolver = new class() implements ControllerResolverInterface {
            public function getController(Request $request): callable|false
            {
                throw new \Exception();
            }
        };

        $traceableResolver = new TraceableControllerResolver($resolver, $stopwatch);
        try {
            $traceableResolver->getController(new Request());
        } catch (\Exception $ex) {
        }
    }
}
