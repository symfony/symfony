<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Monolog\Tests\Handler;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Monolog\Handler\ChromePhpHandler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class ChromePhpHandlerTest extends TestCase
{
    public function testOnKernelResponseShouldNotTriggerDeprecation()
    {
        $request = Request::create('/');
        $request->headers->remove('User-Agent');

        $response = new Response('foo');
        $event = new ResponseEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $error = null;
        set_error_handler(function ($type, $message) use (&$error) { $error = $message; }, \E_DEPRECATED);

        $listener = new ChromePhpHandler();
        $listener->onKernelResponse($event);
        restore_error_handler();

        $this->assertNull($error);
    }
}
