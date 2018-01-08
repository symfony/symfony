<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\EventListener;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\EventListener\ExceptionListener;
use Symfony\Component\HttpKernel\EventListener\LoggingExceptionListener;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Tests\Logger as TestLogger;

/**
 * LoggingExceptionListenerTest.
 *
 * @group time-sensitive
 */
class LoggingExceptionListenerTest extends TestCase
{
    public function provider()
    {
        if (!class_exists('Symfony\Component\HttpFoundation\Request')) {
            return array(array(null, null));
        }

        $request = new Request();
        $exception = new \Exception('foo');
        $event = new GetResponseForExceptionEvent(new LoggingExceptionListenerTestKernel(), $request, HttpKernelInterface::MASTER_REQUEST, $exception);

        $exception2 = new NotFoundHttpException('foo');
        $event2 = new GetResponseForExceptionEvent(new LoggingExceptionListenerTestKernel(), Request::create('/'), HttpKernelInterface::MASTER_REQUEST, $exception2);

        return array(
            array($event, $event2),
        );
    }

    public function testConstruct()
    {
        $logger = new TestLogger();
        $l = new LoggingExceptionListener($logger);

        $_logger = new \ReflectionProperty(get_class($l), 'logger');
        $_logger->setAccessible(true);

        $this->assertSame($logger, $_logger->getValue($l));
    }

    /**
     * @dataProvider provider
     */
    public function testHandleWithoutLogger($event, $event2)
    {
        $l = new LoggingExceptionListener();
        $l->logKernelException($event);
        $l->logKernelException($event2);

        // happy path when the logger is missing, assert no exceptions thrown
        $this->assertTrue(true);
    }

    /**
     * @dataProvider provider
     */
    public function testHandleWithLogger($event, $event2)
    {
        $logger = new TestLogger();
        $l = new LoggingExceptionListener($logger);
        $l->logKernelException($event);
        $l->logKernelException($event2);

        $this->assertCount(1, $logger->getLogs('warning'));
        $this->assertCount(1, $logger->getLogs('critical'));
    }

    /**
     * @dataProvider provider
     */
    public function testHttpLogLevelOverride($event, $event2)
    {
        $logger = new TestLogger();
        $l = new LoggingExceptionListener($logger, array(404 => 'notice'));
        $l->logKernelException($event);
        $l->logKernelException($event2);

        $this->assertCount(1, $logger->getLogs('notice'));
        $this->assertCount(1, $logger->getLogs('critical'));
    }

    /**
     * @dataProvider provider
     */
    public function testBackwardsCompatibilityWithExceptionListener($event, $event2)
    {
        $customExceptionListener = new TestExtendedExceptionListener(null, new NullLogger());

        $logger = new TestLogger();
        $l = new LoggingExceptionListener($logger, array(), $customExceptionListener);
        $logger->clear();
        $l->logKernelException($event);
        foreach ($logger->getLogs() as $logs) {
            $this->assertCount(0, $logs);
        }
    }
}

class LoggingExceptionListenerTestKernel implements HttpKernelInterface
{
    public function handle(Request $request, $type = self::MASTER_REQUEST, $catch = true)
    {
        return new Response('foo');
    }
}

class TestExtendedExceptionListener extends ExceptionListener
{
    protected function logException(\Exception $exception, $message)
    {
        // no-op
    }
}
