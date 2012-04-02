<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Tests\Http\EntryPoint;

use Symfony\Component\Security\Http\EntryPoint\BasicAuthenticationEntryPoint;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

class BasicAuthenticationEntryPointTest extends \PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        if (!class_exists('Symfony\Component\HttpFoundation\Request')) {
            $this->markTestSkipped('The "HttpFoundation" component is not available');
        }
    }

    public function testStart()
    {
        $request = $this->getMock('Symfony\Component\HttpFoundation\Request');

        $authException = new AuthenticationException('The exception message');

        $entryPoint = new BasicAuthenticationEntryPoint('TheRealmName');
        $response = $entryPoint->start($request, $authException);

        $this->assertEquals('Basic realm="TheRealmName"', $response->headers->get('WWW-Authenticate'));
        $this->assertEquals(401, $response->getStatusCode());
        $this->assertAttributeEquals('The exception message', 'statusText', $response);
    }

    public function testStartWithoutAuthException()
    {
        $request = $this->getMock('Symfony\Component\HttpFoundation\Request');

        $entryPoint = new BasicAuthenticationEntryPoint('TheRealmName');

        $response = $entryPoint->start($request);

        $this->assertEquals('Basic realm="TheRealmName"', $response->headers->get('WWW-Authenticate'));
        $this->assertEquals(401, $response->getStatusCode());
        $this->assertAttributeEquals('Unauthorized', 'statusText', $response);
    }
}
