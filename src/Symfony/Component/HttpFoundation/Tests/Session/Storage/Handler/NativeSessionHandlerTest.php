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

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\NativeSessionHandler;

/**
 * Test class for NativeSessionHandler.
 *
 * @author Drak <drak@zikula.org>
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 * @group legacy
 */
class NativeSessionHandlerTest extends TestCase
{
    /**
     * @expectedDeprecation The Symfony\Component\HttpFoundation\Session\Storage\Handler\NativeSessionHandler class is deprecated since version 3.4 and will be removed in 4.0. Use the \SessionHandler class instead.
     */
    public function testConstruct()
    {
        $handler = new NativeSessionHandler();

        $this->assertTrue($handler instanceof \SessionHandler);
        $this->assertTrue($handler instanceof NativeSessionHandler);
    }
}
