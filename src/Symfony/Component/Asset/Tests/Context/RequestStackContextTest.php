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

use Symfony\Component\Asset\Context\RequestStackContext;

class RequestStackContextTest extends \PHPUnit_Framework_TestCase
{
    public function testGetBasePathEmpty()
    {
        $requestStack = $this->getMock('Symfony\Component\HttpFoundation\RequestStack');
        $requestStackContext = new RequestStackContext($requestStack);

        $this->assertEmpty($requestStackContext->getBasePath());
    }

    public function testGetBasePathSet()
    {
        $testBasePath = 'test-path';

        $request = $this->getMock('Symfony\Component\HttpFoundation\Request');
        $request->method('getBasePath')
            ->willReturn($testBasePath);
        $requestStack = $this->getMock('Symfony\Component\HttpFoundation\RequestStack');
        $requestStack->method('getMasterRequest')
            ->willReturn($request);

        $requestStackContext = new RequestStackContext($requestStack);

        $this->assertEquals($testBasePath, $requestStackContext->getBasePath());
    }

    public function testIsSecureFalse()
    {
        $requestStack = $this->getMock('Symfony\Component\HttpFoundation\RequestStack');
        $requestStackContext = new RequestStackContext($requestStack);

        $this->assertFalse($requestStackContext->isSecure());
    }

    public function testIsSecureTrue()
    {
        $request = $this->getMock('Symfony\Component\HttpFoundation\Request');
        $request->method('isSecure')
            ->willReturn(true);
        $requestStack = $this->getMock('Symfony\Component\HttpFoundation\RequestStack');
        $requestStack->method('getMasterRequest')
            ->willReturn($request);

        $requestStackContext = new RequestStackContext($requestStack);

        $this->assertTrue($requestStackContext->isSecure());
    }
}
