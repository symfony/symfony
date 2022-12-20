<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\EntryPoint;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\EntryPoint\BasicAuthenticationEntryPoint;

/**
 * @group legacy
 */
class BasicAuthenticationEntryPointTest extends TestCase
{
    public function testStart()
    {
        $request = self::createMock(Request::class);

        $authException = new AuthenticationException('The exception message');

        $entryPoint = new BasicAuthenticationEntryPoint('TheRealmName');
        $response = $entryPoint->start($request, $authException);

        self::assertEquals('Basic realm="TheRealmName"', $response->headers->get('WWW-Authenticate'));
        self::assertEquals(401, $response->getStatusCode());
    }

    public function testStartWithoutAuthException()
    {
        $request = self::createMock(Request::class);

        $entryPoint = new BasicAuthenticationEntryPoint('TheRealmName');

        $response = $entryPoint->start($request);

        self::assertEquals('Basic realm="TheRealmName"', $response->headers->get('WWW-Authenticate'));
        self::assertEquals(401, $response->getStatusCode());
    }
}
