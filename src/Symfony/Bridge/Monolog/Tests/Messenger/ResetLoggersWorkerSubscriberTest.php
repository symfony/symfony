<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Monolog\Tests\Messenger;

use Monolog\Handler\BufferHandler;
use Monolog\Handler\TestHandler;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Monolog\Logger;
use Symfony\Bridge\Monolog\Messenger\ResetLoggersWorkerSubscriber;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\Messenger\Worker;

/** @group legacy */
class ResetLoggersWorkerSubscriberTest extends TestCase
{
    public function testLogsAreFlushed()
    {
        $loggerTestHandler = new TestHandler();
        $loggerTestHandler->setSkipReset(true);

        $logger = new Logger('', [new BufferHandler($loggerTestHandler)]);

        $message = new class() {
        };

        $handler = static function (object $message) use ($logger): void {
            $logger->info('Message of class {class} is being handled', ['class' => \get_class($message)]);
        };

        $handlersMiddleware = new HandleMessageMiddleware(new HandlersLocator([
            \get_class($message) => [$handler],
        ]));

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber(new ResetLoggersWorkerSubscriber([$logger]));
        $eventDispatcher->addListener(WorkerRunningEvent::class, static function (WorkerRunningEvent $event): void {
            $event->getWorker()->stop();  // Limit the worker to one loop
        });

        $bus = new MessageBus([$handlersMiddleware]);
        $worker = new Worker([$this->createReceiver($message)], $bus, $eventDispatcher);
        $worker->run();

        $this->assertCount(1, $loggerTestHandler->getRecords());
    }

    private function createReceiver(object $message): ReceiverInterface
    {
        return new class($message) implements ReceiverInterface {
            private $message;

            public function __construct(object $message)
            {
                $this->message = $message;
            }

            public function get(): iterable
            {
                return [new Envelope($this->message)];
            }

            public function ack(Envelope $envelope): void
            {
            }

            public function reject(Envelope $envelope): void
            {
            }
        };
    }
}
