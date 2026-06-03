<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests\ArgumentResolver;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\ArgumentResolver\ArgumentResolverInterface;
use Symfony\Component\Console\ArgumentResolver\TraceableArgumentResolver;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Stopwatch\Stopwatch;

class TraceableArgumentResolverTest extends TestCase
{
    public function testTimingsInGetArguments()
    {
        $stopwatch = new Stopwatch();
        $innerResolver = new class implements ArgumentResolverInterface {
            public function getArguments(InputInterface $input, callable $command, ?\ReflectionFunctionAbstract $reflector = null): array
            {
                return ['arg1', 'arg2'];
            }
        };

        $resolver = new TraceableArgumentResolver($innerResolver, $stopwatch);
        $input = new ArrayInput([]);
        $command = static fn () => 0;

        $arguments = $resolver->getArguments($input, $command);

        $this->assertSame(['arg1', 'arg2'], $arguments);

        $event = $stopwatch->getEvent('command.get_arguments');
        $this->assertCount(1, $event->getPeriods());
    }

    public function testTimingsAreRecordedOnException()
    {
        $stopwatch = new Stopwatch();
        $innerResolver = new class implements ArgumentResolverInterface {
            public function getArguments(InputInterface $input, callable $command, ?\ReflectionFunctionAbstract $reflector = null): array
            {
                throw new \RuntimeException('Test exception');
            }
        };

        $resolver = new TraceableArgumentResolver($innerResolver, $stopwatch);
        $input = new ArrayInput([]);
        $command = static fn () => 0;

        try {
            $resolver->getArguments($input, $command);
            $this->fail('Expected exception was not thrown');
        } catch (\RuntimeException $e) {
            $this->assertSame('Test exception', $e->getMessage());
        }

        $event = $stopwatch->getEvent('command.get_arguments');
        $this->assertCount(1, $event->getPeriods());
    }
}
