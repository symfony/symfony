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
use Symfony\Bridge\Monolog\Handler\FirePHPHandler;
use Symfony\Bridge\Monolog\Logger;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

class FirePHPHandlerTest extends TestCase
{
    public function testLogHandling()
    {
        $handler = $this->createHandler();
        $logger = new Logger('my_logger', [$handler]);

        $logger->warning('This does not look right.');

        $request = new Request();
        $request->headers->set('User-Agent', 'Mozilla/5.0 (FirePHP/1.0)');

        $response = $this->dispatchResponseEvent($handler, $request);

        $logger->error('Something went wrong.');

        self::assertSame(
            [
                'x-wf-1-1-1-1' => ['85|[{"Type":"WARN","File":"","Line":"","Label":"my_logger"},"This does not look right."]|'],
                'x-wf-1-1-1-2' => ['82|[{"Type":"ERROR","File":"","Line":"","Label":"my_logger"},"Something went wrong."]|'],
            ],
            array_filter(
                $response->headers->all(),
                static fn (string $key): bool => str_starts_with($key, 'x-wf-1-1-1'),
                \ARRAY_FILTER_USE_KEY
            )
        );
    }

    public function testEmptyLog()
    {
        $handler = $this->createHandler();

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(KernelEvents::RESPONSE, $handler->onKernelResponse(...));

        $request = new Request();
        $request->headers->set('User-Agent', 'Mozilla/5.0 (FirePHP/1.0)');

        $response = $this->dispatchResponseEvent($handler, $request);

        self::assertSame(
            [],
            array_filter(
                $response->headers->all(),
                static fn (string $key): bool => str_starts_with($key, 'x-wf-1-1-1'),
                \ARRAY_FILTER_USE_KEY
            )
        );
    }

    public function testNoFirePhpClient()
    {
        $handler = $this->createHandler();
        $logger = new Logger('my_logger', [$handler]);

        $logger->warning('This does not look right.');

        $request = new Request();
        $request->headers->set('User-Agent', 'Mozilla/5.0');

        $response = $this->dispatchResponseEvent($handler, $request);

        $logger->error('Something went wrong.');

        self::assertSame(
            [],
            array_filter(
                $response->headers->all(),
                static fn (string $key): bool => str_starts_with($key, 'x-wf-1-1-1'),
                \ARRAY_FILTER_USE_KEY
            )
        );
    }

    private function createHandler(): FirePHPHandler
    {
        // Monolog 1
        if (!method_exists(FirePHPHandler::class, 'isWebRequest')) {
            return new FirePHPHandler();
        }

        $handler = $this->getMockBuilder(FirePHPHandler::class)
            ->onlyMethods(['isWebRequest'])
            ->getMock();
        // Disable web request detection
        $handler->method('isWebRequest')->willReturn(true);

        return $handler;
    }

    public function testOnKernelResponseShouldNotTriggerDeprecation()
    {
        $handler = $this->createHandler();

        $request = Request::create('/');
        $request->headers->remove('User-Agent');

        $error = null;
        set_error_handler(function ($type, $message) use (&$error) { $error = $message; }, \E_DEPRECATED);

        $this->dispatchResponseEvent($handler, $request);
        restore_error_handler();

        $this->assertNull($error);
    }

    private function dispatchResponseEvent(FirePHPHandler $handler, Request $request): Response
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(KernelEvents::RESPONSE, $handler->onKernelResponse(...));

        return $dispatcher
            ->dispatch(
                new ResponseEvent(
                    $this->createStub(HttpKernelInterface::class),
                    $request,
                    HttpKernelInterface::MAIN_REQUEST,
                    new Response()
                ),
                KernelEvents::RESPONSE
            )
            ->getResponse();
    }
}
