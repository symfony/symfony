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
use Symfony\Component\HttpKernel\Controller\ArgumentResolverInterface;
use Symfony\Component\HttpKernel\Controller\TraceableArgumentResolver;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\Stopwatch\StopwatchEvent;

class TraceableArgumentResolverTest extends TestCase
{
    public function testStopwatchEventIsStoppedWhenResolverThrows()
    {
        $stopwatchEvent = $this->createMock(StopwatchEvent::class);
        $stopwatchEvent->expects(self::once())->method('stop');

        $stopwatch = $this->createStub(Stopwatch::class);
        $stopwatch->method('start')->willReturn($stopwatchEvent);

        $resolver = new class implements ArgumentResolverInterface {
            public function getArguments(Request $request, callable $controller, ?\ReflectionFunctionAbstract $reflector = null): array
            {
                throw new \Exception();
            }
        };

        $traceableResolver = new TraceableArgumentResolver($resolver, $stopwatch);

        try {
            $traceableResolver->getArguments(new Request(), function () {});
        } catch (\Exception $ex) {
        }
    }
}
