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
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Bridge\PsrHttpMessage\ArgumentValueResolver\PsrServerRequestResolver;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

/**
 * @author Alexander M. Turek <me@derrabus.de>
 */
final class PsrServerRequestResolverTest extends TestCase
{
    use ExpectDeprecationTrait;

    public function testServerRequest()
    {
        $symfonyRequest = $this->createMock(Request::class);
        $psrRequest = $this->createMock(ServerRequestInterface::class);

        $resolver = $this->bootstrapResolver($symfonyRequest, $psrRequest);

        self::assertSame([$psrRequest], $resolver->getArguments($symfonyRequest, static function (ServerRequestInterface $serverRequest): void {}));
    }

    public function testRequest()
    {
        $symfonyRequest = $this->createMock(Request::class);
        $psrRequest = $this->createMock(ServerRequestInterface::class);

        $resolver = $this->bootstrapResolver($symfonyRequest, $psrRequest);

        self::assertSame([$psrRequest], $resolver->getArguments($symfonyRequest, static function (RequestInterface $request): void {}));
    }

    public function testMessage()
    {
        $symfonyRequest = $this->createMock(Request::class);
        $psrRequest = $this->createMock(ServerRequestInterface::class);

        $resolver = $this->bootstrapResolver($symfonyRequest, $psrRequest);

        self::assertSame([$psrRequest], $resolver->getArguments($symfonyRequest, static function (MessageInterface $request): void {}));
    }

    /**
     * @group legacy
     */
    public function testDeprecatedSupports()
    {
        if (!interface_exists(ValueResolverInterface::class)) {
            $this->markTestSkipped('Requires symfony/http-kernel 6.2.');
        }

        $resolver = new PsrServerRequestResolver($this->createStub(HttpMessageFactoryInterface::class));

        $this->expectDeprecation('Since symfony/psr-http-message-bridge 2.3: Method "Symfony\Bridge\PsrHttpMessage\ArgumentValueResolver\PsrServerRequestResolver::supports" is deprecated, call "resolve()" without calling "supports()" first.');
        $resolver->supports($this->createStub(Request::class), $this->createStub(ArgumentMetadata::class));
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
