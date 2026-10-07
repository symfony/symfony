<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\PsrHttpMessage\Tests\ArgumentValueResolver;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Bridge\PsrHttpMessage\ArgumentValueResolver\PsrServerRequestResolver;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

/**
 * @author Alexander M. Turek <me@derrabus.de>
 */
final class PsrServerRequestResolverTest extends TestCase
{
    public function testServerRequest()
    {
        $symfonyRequest = new Request();
        $psrRequest = $this->createStub(ServerRequestInterface::class);

        $resolver = $this->bootstrapResolver($symfonyRequest, $psrRequest);

        self::assertSame([$psrRequest], $resolver->getArguments($symfonyRequest, static function (ServerRequestInterface $serverRequest): void {}));
    }

    public function testRequest()
    {
        $symfonyRequest = new Request();
        $psrRequest = $this->createStub(ServerRequestInterface::class);

        $resolver = $this->bootstrapResolver($symfonyRequest, $psrRequest);

        self::assertSame([$psrRequest], $resolver->getArguments($symfonyRequest, static function (RequestInterface $request): void {}));
    }

    public function testMessage()
    {
        $symfonyRequest = new Request();
        $psrRequest = $this->createStub(ServerRequestInterface::class);

        $resolver = $this->bootstrapResolver($symfonyRequest, $psrRequest);

        self::assertSame([$psrRequest], $resolver->getArguments($symfonyRequest, static function (MessageInterface $request): void {}));
    }

    public function testUntypedArgumentIsNotResolved()
    {
        $resolver = new PsrServerRequestResolver($this->createStub(HttpMessageFactoryInterface::class));

        self::assertSame([], iterator_to_array($resolver->resolve(new Request(), new ArgumentMetadata('foo', null, false, false, null))));
    }

    private function bootstrapResolver(Request $symfonyRequest, ServerRequestInterface $psrRequest): ArgumentResolver
    {
        $messageFactory = $this->createMock(HttpMessageFactoryInterface::class);
        $messageFactory->expects(self::once())
            ->method('createRequest')
            ->with(self::identicalTo($symfonyRequest))
            ->willReturn($psrRequest);

        return new ArgumentResolver(null, [new PsrServerRequestResolver($messageFactory)]);
    }
}
