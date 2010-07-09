<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Foundation\Debug;

use Symfony\Framework\Debug\ErrorHandler;

class ErrorHandlerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @covers Symfony\Framework\Debug\ErrorHandler::register
     */
    public function testRegister()
    {
        $fakeHandler = function($errno, $errstr)
        {
            die('Fake error handler triggered');
        };

        $oldHandler = set_error_handler($fakeHandler);
        restore_error_handler();

        $handler = new ErrorHandler();
        $handler->register(false);
        $newHandler = set_error_handler($fakeHandler);
        restore_error_handler();
        $this->assertEquals($oldHandler, $newHandler);

        $handler = new ErrorHandler();
        $handler->register(true);
        $newHandler = set_error_handler($fakeHandler);
        restore_error_handler();
        $this->assertNotEquals($oldHandler, $newHandler);
        restore_error_handler();
    }
}
