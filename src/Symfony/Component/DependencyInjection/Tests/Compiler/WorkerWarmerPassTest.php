<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Compiler;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Compiler\WorkerWarmerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\WorkerWarmer;

class WorkerWarmerPassTest extends TestCase
{
    public function testCompilerPass()
    {
        $container = new ContainerBuilder();
        $container->register('one', \stdClass::class)
            ->addTag('kernel.worker_warmer');
        $container->register('two', \stdClass::class)
            ->addTag('kernel.worker_warmer', ['method' => 'connect'])
            ->addTag('kernel.worker_warmer', ['method' => 'load']);
        $container->register('worker_warmer', WorkerWarmer::class)
            ->setPublic(true)
            ->setArguments([null, []]);

        (new WorkerWarmerPass())->process($container);

        $this->assertEquals(
            [
                new IteratorArgument([
                    'one' => new Reference('one'),
                    'two' => new Reference('two'),
                ]),
                [
                    'one' => ['__invoke'],
                    'two' => ['connect', 'load'],
                ],
            ],
            $container->getDefinition('worker_warmer')->getArguments()
        );
    }

    public function testWorkerWarmerIsRemovedWithoutWarmers()
    {
        $container = new ContainerBuilder();
        $container->register('worker_warmer', WorkerWarmer::class)
            ->setPublic(true)
            ->setArguments([null, []]);

        (new WorkerWarmerPass())->process($container);

        $this->assertFalse($container->has('worker_warmer'));
    }

    public function testWarmUpCallsEachMethod()
    {
        $calls = [];
        $warmer = new class($calls) {
            public function __construct(private array &$calls)
            {
            }

            public function __invoke(): void
            {
                $this->calls[] = '__invoke';
            }

            public function connect(): void
            {
                $this->calls[] = 'connect';
            }
        };

        (new WorkerWarmer(new \ArrayIterator(['one' => $warmer, 'two' => $warmer]), ['one' => ['__invoke'], 'two' => ['connect']]))->warmUp();

        $this->assertSame(['__invoke', 'connect'], $calls);
    }
}
