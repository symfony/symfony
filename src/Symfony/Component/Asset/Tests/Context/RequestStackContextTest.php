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
        $requestStackContext = new RequestStackContext(new RequestStack());

        $this->assertSame('', $requestStackContext->getBasePath());
    }

    public function testGetBasePathSet()
    {
        $testBasePath = 'test-path';

        $request = $this->createMock(Request::class);
        $request->method('getBasePath')
            ->willReturn($testBasePath);
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $requestStackContext = new RequestStackContext($requestStack);

        $this->assertSame($testBasePath, $requestStackContext->getBasePath());
    }

    public function testIsSecureFalse()
    {
        $requestStackContext = new RequestStackContext(new RequestStack());

        $this->assertFalse($requestStackContext->isSecure());
    }

    public function testIsSecureTrue()
    {
        $request = $this->createMock(Request::class);
        $request->method('isSecure')
            ->willReturn(true);
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $requestStackContext = new RequestStackContext($requestStack);

        $this->assertTrue($requestStackContext->isSecure());
    }

    public function testDefaultContext()
    {
        $requestStackContext = new RequestStackContext(new RequestStack(), 'default-path', true);

        $this->assertSame('default-path', $requestStackContext->getBasePath());
        $this->assertTrue($requestStackContext->isSecure());
    }
}
