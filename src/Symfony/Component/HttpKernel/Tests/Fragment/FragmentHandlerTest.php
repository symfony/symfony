<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\Fragment;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Fragment\FragmentHandler;
use Symfony\Component\HttpKernel\Fragment\FragmentRendererInterface;

class FragmentHandlerTest extends TestCase
{
    private $requestStack;

    protected function setUp(): void
    {
        $this->requestStack = self::createMock(RequestStack::class);
        $this->requestStack
            ->expects(self::any())
            ->method('getCurrentRequest')
            ->willReturn(Request::create('/'))
        ;
    }

    public function testRenderWhenRendererDoesNotExist()
    {
        self::expectException(\InvalidArgumentException::class);
        $handler = new FragmentHandler($this->requestStack);
        $handler->render('/', 'foo');
    }

    public function testRenderWithUnknownRenderer()
    {
        self::expectException(\InvalidArgumentException::class);
        $handler = $this->getHandler(self::returnValue(new Response('foo')));

        $handler->render('/', 'bar');
    }

    public function testDeliverWithUnsuccessfulResponse()
    {
        $handler = $this->getHandler(self::returnValue(new Response('foo', 404)));
        try {
            $handler->render('/', 'foo');
            self::fail('->render() throws a \RuntimeException exception if response is not successful');
        } catch (\Exception $e) {
            self::assertInstanceOf(\RuntimeException::class, $e);
            self::assertEquals(0, $e->getCode());
            self::assertEquals('Error when rendering "http://localhost/" (Status code is 404).', $e->getMessage());

            $previousException = $e->getPrevious();
            self::assertInstanceOf(HttpException::class, $previousException);
            self::assertEquals(404, $previousException->getStatusCode());
            self::assertEquals(0, $previousException->getCode());
        }
    }

    public function testRender()
    {
        $expectedRequest = Request::create('/');
        $handler = $this->getHandler(
            self::returnValue(new Response('foo')),
            [
                '/',
                self::callback(function (Request $request) use ($expectedRequest) {
                    $expectedRequest->server->remove('REQUEST_TIME_FLOAT');
                    $request->server->remove('REQUEST_TIME_FLOAT');

                    return $expectedRequest == $request;
                }),
                ['foo' => 'foo', 'ignore_errors' => true],
            ]
        );

        self::assertEquals('foo', $handler->render('/', 'foo', ['foo' => 'foo']));
    }

    protected function getHandler($returnValue, $arguments = [])
    {
        $renderer = self::createMock(FragmentRendererInterface::class);
        $renderer
            ->expects(self::any())
            ->method('getName')
            ->willReturn('foo')
        ;
        $e = $renderer
            ->expects(self::any())
            ->method('render')
            ->will($returnValue)
        ;

        if ($arguments) {
            $e->with(...$arguments);
        }

        $handler = new FragmentHandler($this->requestStack);
        $handler->addRenderer($renderer);

        return $handler;
    }
}
