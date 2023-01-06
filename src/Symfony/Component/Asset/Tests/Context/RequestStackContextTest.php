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
        $requestStack = $this->createMock(RequestStack::class);
        $requestStackContext = new RequestStackContext($requestStack);

        $this->assertEmpty($requestStackContext->getBasePath());
    }

    public function testGetBasePathSet()
    {
        $testBasePath = 'test-path';

        $request = $this->createMock(Request::class);
        $request->method('getBasePath')
            ->willReturn($testBasePath);
        $requestStack = $this->createMock(RequestStack::class);
        $requestStack->method('getMainRequest')
            ->willReturn($request);

        $requestStackContext = new RequestStackContext($requestStack);

        $this->assertSame($testBasePath, $requestStackContext->getBasePath());
    }

    public function testIsSecureFalse()
    {
        $requestStack = $this->createMock(RequestStack::class);
        $requestStackContext = new RequestStackContext($requestStack);

        $this->assertFalse($requestStackContext->isSecure());
    }

    public function testIsSecureTrue()
    {
        $request = $this->createMock(Request::class);
        $request->method('isSecure')
            ->willReturn(true);
        $requestStack = $this->createMock(RequestStack::class);
        $requestStack->method('getMainRequest')
            ->willReturn($request);

        $requestStackContext = new RequestStackContext($requestStack);

        $this->assertTrue($requestStackContext->isSecure());
    }

    public function testDefaultContext()
    {
        $requestStack = $this->createMock(RequestStack::class);
        $requestStackContext = new RequestStackContext($requestStack, 'default-path', true);

        $this->assertSame('default-path', $requestStackContext->getBasePath());
        $this->assertTrue($requestStackContext->isSecure());
    }
}
