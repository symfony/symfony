<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\Debug;

use Symfony\Component\HttpKernel\Debug\ErrorHandler;
use Symfony\Component\HttpKernel\Debug\ErrorException;

/**
 * ErrorHandlerTest
 *
 * @author Robert Schönthal <seroscho@googlemail.com>
 */
class ErrorHandlerTest extends \PHPUnit_Framework_TestCase
{

    public function testConstruct()
    {
        $handler = ErrorHandler::register(3);

        $level = new \ReflectionProperty($handler, 'level');
        $level->setAccessible(true);

        $this->assertEquals(3, $level->getValue($handler));

        restore_error_handler();
    }

    public function testHandle()
    {
        $handler = ErrorHandler::register(0);
        $this->assertFalse($handler->handle(0, 'foo', 'foo.php', 12, 'foo'));

        restore_error_handler();

        $handler = ErrorHandler::register(3);
        $this->assertFalse($handler->handle(4, 'foo', 'foo.php', 12, 'foo'));

        restore_error_handler();

        $handler = ErrorHandler::register(3);
        try {
            $handler->handle(1, 'foo', 'foo.php', 12, 'foo');
        } catch (\ErrorException $e) {
            $this->assertSame('1: foo in foo.php line 12', $e->getMessage());
            $this->assertSame(1, $e->getSeverity());
            $this->assertSame('foo.php', $e->getFile());
            $this->assertSame(12, $e->getLine());
        }

        restore_error_handler();
    }
}
