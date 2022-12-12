<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\Controller\ArgumentResolver;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\TraceableValueResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\Stopwatch\Stopwatch;

class TraceableValueResolverTest extends TestCase
{
    /**
     * @group legacy
     */
    public function testTimingsInSupports()
    {
        $stopwatch = new Stopwatch();
        $resolver = new TraceableValueResolver(new ResolverStub(), $stopwatch);
        $argument = new ArgumentMetadata('dummy', 'string', false, false, null);
        $request = new Request();

        $this->assertTrue($resolver->supports($request, $argument));

        $event = $stopwatch->getEvent(ResolverStub::class.'::supports');
        $this->assertCount(1, $event->getPeriods());
    }

    public function testTimingsInResolve()
    {
        $stopwatch = new Stopwatch();
        $resolver = new TraceableValueResolver(new ResolverStub(), $stopwatch);
        $argument = new ArgumentMetadata('dummy', 'string', false, false, null);
        $request = new Request();

        $iterable = $resolver->resolve($request, $argument);

        foreach ($iterable as $index => $resolved) {
            $event = $stopwatch->getEvent(ResolverStub::class.'::resolve');
            $this->assertTrue($event->isStarted());
            $this->assertEmpty($event->getPeriods());
            switch ($index) {
                case 0:
                    $this->assertEquals('first', $resolved);
                    break;
                case 1:
                    $this->assertEquals('second', $resolved);
                    break;
            }
        }

        $event = $stopwatch->getEvent(ResolverStub::class.'::resolve');
        $this->assertCount(1, $event->getPeriods());
    }
}

class ResolverStub implements ArgumentValueResolverInterface
{
    public function supports(Request $request, ArgumentMetadata $argument): bool
    {
        return true;
    }

    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        yield 'first';
        yield 'second';
    }
}
