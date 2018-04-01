<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Security\Http\Tests\EntryPoint;

use PHPUnit\Framework\TestCase;
use Symphony\Component\Security\Http\EntryPoint\BasicAuthenticationEntryPoint;
use Symphony\Component\Security\Core\Exception\AuthenticationException;

class BasicAuthenticationEntryPointTest extends TestCase
{
    public function testStart()
    {
        $request = $this->getMockBuilder('Symphony\Component\HttpFoundation\Request')->getMock();

        $authException = new AuthenticationException('The exception message');

        $entryPoint = new BasicAuthenticationEntryPoint('TheRealmName');
        $response = $entryPoint->start($request, $authException);

        $this->assertEquals('Basic realm="TheRealmName"', $response->headers->get('WWW-Authenticate'));
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testStartWithoutAuthException()
    {
        $request = $this->getMockBuilder('Symphony\Component\HttpFoundation\Request')->getMock();

        $entryPoint = new BasicAuthenticationEntryPoint('TheRealmName');

        $response = $entryPoint->start($request);

        $this->assertEquals('Basic realm="TheRealmName"', $response->headers->get('WWW-Authenticate'));
        $this->assertEquals(401, $response->getStatusCode());
    }
}
