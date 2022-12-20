<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Asset\Tests\Context;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Asset\Context\RequestStackContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class RequestStackContextTest extends TestCase
{
    public function testGetBasePathEmpty()
    {
        $requestStack = self::createMock(RequestStack::class);
        $requestStackContext = new RequestStackContext($requestStack);

        self::assertEmpty($requestStackContext->getBasePath());
    }

    public function testGetBasePathSet()
    {
        $testBasePath = 'test-path';

        $request = self::createMock(Request::class);
        $request->method('getBasePath')
            ->willReturn($testBasePath);
        $requestStack = self::createMock(RequestStack::class);
        $requestStack->method('getMainRequest')
            ->willReturn($request);

        $requestStackContext = new RequestStackContext($requestStack);

        self::assertSame($testBasePath, $requestStackContext->getBasePath());
    }

    public function testIsSecureFalse()
    {
        $requestStack = self::createMock(RequestStack::class);
        $requestStackContext = new RequestStackContext($requestStack);

        self::assertFalse($requestStackContext->isSecure());
    }

    public function testIsSecureTrue()
    {
        $request = self::createMock(Request::class);
        $request->method('isSecure')
            ->willReturn(true);
        $requestStack = self::createMock(RequestStack::class);
        $requestStack->method('getMainRequest')
            ->willReturn($request);

        $requestStackContext = new RequestStackContext($requestStack);

        self::assertTrue($requestStackContext->isSecure());
    }

    public function testDefaultContext()
    {
        $requestStack = self::createMock(RequestStack::class);
        $requestStackContext = new RequestStackContext($requestStack, 'default-path', true);

        self::assertSame('default-path', $requestStackContext->getBasePath());
        self::assertTrue($requestStackContext->isSecure());
    }
}
