<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests\ArgumentResolver\ValueResolver;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\ArgumentResolver\ValueResolver\TraceableValueResolver;
use Symfony\Component\Console\ArgumentResolver\ValueResolver\ValueResolverInterface;
use Symfony\Component\Console\Attribute\Reflection\ReflectionMember;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Stopwatch\Stopwatch;

class TraceableValueResolverTest extends TestCase
{
    public function testTimingsInResolve()
    {
        $stopwatch = new Stopwatch();
        $resolver = new TraceableValueResolver(new ResolverStub(), $stopwatch);
        $input = new ArrayInput([]);
        $reflectionParam = new \ReflectionParameter([TestInvokable::class, '__invoke'], 'value');
        $member = new ReflectionMember($reflectionParam);

        $iterable = $resolver->resolve('value', $input, $member);

        foreach ($iterable as $index => $resolved) {
            $event = $stopwatch->getEvent(ResolverStub::class.'::resolve');
            $this->assertTrue($event->isStarted());
            $this->assertSame([], $event->getPeriods());
            $this->assertSame(match ($index) {
                0 => 'first',
                1 => 'second',
            }, $resolved);
        }

        $event = $stopwatch->getEvent(ResolverStub::class.'::resolve');
        $this->assertCount(1, $event->getPeriods());
    }
}

class ResolverStub implements ValueResolverInterface
{
    public function resolve(string $argumentName, InputInterface $input, ReflectionMember $member): iterable
    {
        yield 'first';
        yield 'second';
    }
}

class TestInvokable
{
    public function __invoke(string $value): int
    {
        return 0;
    }
}
