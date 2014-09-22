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
use Symfony\Component\Debug\ErrorHandler;
use Symfony\Component\Debug\ExceptionHandler;
use Symfony\Component\HttpKernel\EventListener\DebugHandlersListener;

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
}
