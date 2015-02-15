<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Tests\Session\Storage\Handler;

use Symfony\Component\HttpFoundation\Session\Storage\Handler\NativeSessionHandler;

/**
 * Test class for NativeSessionHandler.
 *
 * @author Drak <drak@zikula.org>
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class NativeSessionHandlerTest extends \PHPUnit_Framework_TestCase
{
    public function testConstruct()
    {
        $handler = new NativeSessionHandler();

<<<<<<< HEAD
        // note for PHPUnit optimisers - the use of assertTrue/False
        // here is deliberate since the tests do not require the classes to exist - drak
        if (PHP_VERSION_ID < 50400) {
            $this->assertFalse($handler instanceof \SessionHandler);
            $this->assertTrue($handler instanceof NativeSessionHandler);
        } else {
            $this->assertTrue($handler instanceof \SessionHandler);
            $this->assertTrue($handler instanceof NativeSessionHandler);
        }
=======
        $this->assertTrue($handler instanceof \SessionHandler);
        $this->assertTrue($handler instanceof NativeSessionHandler);
>>>>>>> 22cd78c4a87e94b59ad313d11b99acb50aa17b8d
    }
}
