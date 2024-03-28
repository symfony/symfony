<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests;

use Amp\Parallel\Worker\Worker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\ParallelMessageBus;
use Symfony\Component\Messenger\Stamp\FutureStamp;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;

class ParallelMessageBusTest extends TestCase
{
    private string $env = 'dev';
    private bool $debug = false;
    private string $projectDir = 'path/to/project';

    public function testItHasTheRightInterface1()
    {
        if (!class_exists(Worker::class)) {
            $this->markTestSkipped(sprintf('%s not available.', Worker::class));
        }

        $bus = new ParallelMessageBus([], $this->env, $this->debug, $this->projectDir);

        $this->assertInstanceOf(MessageBusInterface::class, $bus);
    }

    public function testItReturnsWithFutureStamp()
    {
        if (!class_exists(Worker::class)) {
            $this->markTestSkipped(sprintf('%s not available.', Worker::class));
        }

        $message = new DummyMessage('Hello');

        $bus = new ParallelMessageBus([], $this->env, $this->debug, $this->projectDir);

        $envelope = $bus->dispatch(new Envelope($message));

        $this->assertInstanceOf(FutureStamp::class, $envelope->last(FutureStamp::class));
    }
}
