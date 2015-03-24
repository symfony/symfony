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

use Psr\Log\LogLevel;
use Symfony\Component\Console\Event\ConsoleEvent;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Debug\ErrorHandler;
use Symfony\Component\Debug\ExceptionHandler;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpKernel\EventListener\DebugHandlersListener;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * DebugHandlersListenerTest
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class DebugHandlersListenerTest extends \PHPUnit_Framework_TestCase
{
    public function testConfigure()
    {
        $logger = $this->getMock('Psr\Log\LoggerInterface');
        $userHandler = function () {};
        $listener = new DebugHandlersListener($userHandler, $logger);
        $xHandler = new ExceptionHandler();
        $eHandler = new ErrorHandler();
        $eHandler->setExceptionHandler(array($xHandler, 'handle'));

        $exception = null;
        set_error_handler(array($eHandler, 'handleError'));
        set_exception_handler(array($eHandler, 'handleException'));
        try {
            $listener->configure();
        } catch (\Exception $exception) {
        }
        restore_exception_handler();
        restore_error_handler();

        if (null !== $exception) {
            throw $exception;
        }

        $this->assertSame($userHandler, $xHandler->setHandler('var_dump'));

        $loggers = $eHandler->setLoggers(array());

        $this->assertArrayHasKey(E_DEPRECATED, $loggers);
        $this->assertSame(array($logger, LogLevel::INFO), $loggers[E_DEPRECATED]);
    }

    public function testConsoleEvent()
    {
        $dispatcher = new EventDispatcher();
        $listener = new DebugHandlersListener(null);
        $app = $this->getMock('Symfony\Component\Console\Application');
        $app->expects($this->once())->method('getHelperSet')->will($this->returnValue(new HelperSet()));
        $command = new Command(__FUNCTION__);
        $command->setApplication($app);
        $event = new ConsoleEvent($command, new ArgvInput(), new ConsoleOutput());

        $dispatcher->addSubscriber($listener);

        $xListeners = array(
            KernelEvents::REQUEST => array(array($listener, 'configure')),
            ConsoleEvents::COMMAND => array(array($listener, 'configure')),
        );
        $this->assertSame($xListeners, $dispatcher->getListeners());

        $exception = null;
        $eHandler = new ErrorHandler();
        set_error_handler(array($eHandler, 'handleError'));
        set_exception_handler(array($eHandler, 'handleException'));
        try {
            $dispatcher->dispatch(ConsoleEvents::COMMAND, $event);
        } catch (\Exception $exception) {
        }
        restore_exception_handler();
        restore_error_handler();

        if (null !== $exception) {
            throw $exception;
        }

        $xHandler = $eHandler->setExceptionHandler('var_dump');
        $this->assertInstanceOf('Closure', $xHandler);

        $app->expects($this->once())
            ->method('renderException');

        $xHandler(new \Exception());
    }
}
