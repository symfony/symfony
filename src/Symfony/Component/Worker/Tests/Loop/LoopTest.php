<?php

namespace Symfony\Component\Worker\Tests\Loop;

use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Worker\Consumer\ConsumerInterface;
use Symfony\Component\Worker\Exception\StopException;
use Symfony\Component\Worker\Loop\Loop;
use Symfony\Component\Worker\MessageCollection;
use Symfony\Component\Worker\MessageFetcher\MessageFetcherInterface;
use Symfony\Component\Worker\Router\DirectRouter;

/**
 * @group time-sensitive
 */
class LoopTest extends TestCase
{
    private $messageFetcher;
    private $consumer;
    private $broker;
    private $eventDispatcher;
    private $logger;
    private $loop;

    protected function setUp()
    {
        $this->messageFetcher = new MessageFetcher();
        $this->consumer = new ConsumerMock();
        $this->eventDispatcher = new EventDispatcherMock();
        $this->logger = new LoggerMock();

        $this->loop = new Loop(new DirectRouter($this->messageFetcher, $this->consumer), $this->eventDispatcher, $this->logger, 'a_queue_name');
    }

    public function provideReturnStatus()
    {
        yield array(false, 'warning  Messages consumed with failure.');
        yield array(true, 'info     Messages consumed successfully.');
    }

    /**
     * @dataProvider provideReturnStatus
     */
    public function testConsumeAllPendingMessagesInOneRow($returnStatus, $expectedLog)
    {
        $this->messageFetcher->messages = array('a', 'b');
        $this->consumer->setConsumeCode(function () use ($returnStatus) {
            return $returnStatus;
        });

        $this->loop->run();

        $this->assertSame(array('a', 'b'), $this->consumer->messages);

        $expectedEvents = array(
            'worker.run',
            'worker.health_check',
            'worker.wake_up',
            'worker.stop',
        );

        $this->assertSame($expectedEvents, $this->eventDispatcher->dispatchedEvents);

        $expectedLogs = array(
            'notice   Worker a_queue_name started.',
            'notice   New message.',
            $expectedLog,
            'notice   New message.',
            $expectedLog,
            'notice   Worker a_queue_name stopped (Force shut down of the worker because a StopException has been thrown.).',
        );

        $this->assertEquals($expectedLogs, $this->logger->logs);
    }

    public function testConsumePendingMessages()
    {
        $this->messageFetcher->messages = array('a', false, 'b');

        $this->loop->run();

        $this->assertSame(array('a', 'b'), $this->consumer->messages);

        $expectedEvents = array(
            'worker.run',
            'worker.health_check',
            'worker.wake_up',
            'worker.sleep',
            'worker.wake_up',
            'worker.stop',
        );

        $this->assertEquals($expectedEvents, $this->eventDispatcher->dispatchedEvents);

        $expectedLogs = array(
            'notice   Worker a_queue_name started.',
            'notice   New message.',
            'info     Messages consumed successfully.',
            'notice   New message.',
            'info     Messages consumed successfully.',
            'notice   Worker a_queue_name stopped (Force shut down of the worker because a StopException has been thrown.).',
        );

        $this->assertSame($expectedLogs, $this->logger->logs);
    }

    public function testSignal()
    {
        $this->messageFetcher->messages = array('a');

        // After 1 second a SIGALRM signal will be fired and it will stop the
        // loop.
        pcntl_signal(SIGALRM, function () {
            $this->loop->stop('Signaled with SIGALRM');
        });
        pcntl_alarm(1);

        // Let's wait 1 second in the consumer, to avoid too many loop iteration
        // in order to avoid too many event.
        $this->consumer->setConsumeCode(function () {
            // we don't want to use the mock sleep here
            \sleep(1);
        });

        $this->loop->run();

        $expectedEvents = array(
            'worker.run',
            'worker.health_check',
            'worker.wake_up',
            'worker.stop',
        );

        $this->assertSame($expectedEvents, $this->eventDispatcher->dispatchedEvents);

        $expectedLogs = array(
            'notice   Worker a_queue_name started.',
            'notice   New message.',
            'info     Messages consumed successfully.',
            'notice   Worker a_queue_name stopped (Signaled with SIGALRM).',
        );

        $this->assertSame($expectedLogs, $this->logger->logs);
    }

    public function testHealthCheck()
    {
        $this->messageFetcher->messages = array('a');

        // default health check is done every 10 seconds
        $this->consumer->setConsumeCode(function () {
            sleep(10);
        });

        $this->loop->run();

        $expectedEvents = array(
            'worker.run',
            'worker.health_check',
            'worker.wake_up',
            'worker.health_check',
            'worker.stop',
        );

        $this->assertEquals($expectedEvents, $this->eventDispatcher->dispatchedEvents);
    }

    public function provideException()
    {
        yield array(new \AMQPConnectionException('AMQP connexion error.'), 'error    Worker a_queue_name has errored, shutting down. (AMQP connexion error.)');
        yield array(new \Exception('oups.'), 'error    Worker a_queue_name has errored, shutting down. (oups.)');
    }

    /**
     * @dataProvider provideException
     */
    public function testException(\Exception $exception, $expectedLog)
    {
        $this->messageFetcher->messages = array('a');

        $this->consumer->setConsumeCode(function () use ($exception) {
            throw $exception;
        });

        $expectedLogs = array(
            'notice   Worker a_queue_name started.',
            'notice   New message.',
            $expectedLog,
        );

        try {
            $this->loop->run();

            $this->fail('An exception should be thrown.');
        } catch (\Exception $e) {
            $this->assertSame($e, $exception);
        }

        $this->assertSame($expectedLogs, $this->logger->logs);
    }
}

class ConsumerMock implements ConsumerInterface
{
    public $loop;
    public $messages = array();

    private $consumeCode;

    public function consume(MessageCollection $messageCollection)
    {
        foreach ($messageCollection as $message) {
            $this->messages[] = $message;
        }

        if ($this->consumeCode) {
            return call_user_func($this->consumeCode);
        }
    }

    public function setConsumeCode(callable $consumeCode)
    {
        $this->consumeCode = $consumeCode;
    }
}

class MessageFetcher implements MessageFetcherInterface
{
    public $messages = array();

    public function fetchMessages()
    {
        if (!$this->messages) {
            throw new StopException();
        }

        $message = array_shift($this->messages);

        if (false === $message) {
            return false;
        }

        return new MessageCollection($message);
    }
}

class EventDispatcherMock extends EventDispatcher
{
    public $dispatchedEvents = array();

    public function dispatch($eventName, \Symfony\Component\EventDispatcher\Event $event = null)
    {
        $this->dispatchedEvents[] = $eventName;
    }
}

class LoggerMock extends AbstractLogger
{
    public $logs = array();

    public function log($level, $message, array $context = array())
    {
        $replacements = array();
        foreach ($context as $key => $val) {
            if (null === $val || is_scalar($val) || (is_object($val) && method_exists($val, '__toString'))) {
                $replacements['{'.$key.'}'] = $val;
            }
        }

        $message = strtr($message, $replacements);

        $this->logs[] = sprintf('%-8s %s', $level, $message);
    }
}
