<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\OAuthServer\Tests\Request;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\OAuthServer\Request\AuthorizationRequest;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class AuthorizationRequestTest extends TestCase
{
    public function testCreationFromGlobals()
    {
        $_GET['client_id'] = \uniqid();
        $_GET['response_type'] = 'code';

        $request = AuthorizationRequest::create();

        static::assertNull($request->getValue('code'));
        static::assertNotNull($request->getValue('client_id'));
        static::assertNotNull($request->getValue('response_type'));
        static::assertArrayHasKey('client_id', $request->returnAsReadOnly());
        static::assertArrayHasKey('response_type', $request->returnAsReadOnly());

        unset($_GET['client_id']);
        unset($_GET['response_type']);
    }

    public function testCreationFromHttpFoundationRequest()
    {
        $requestMock = Request::create('/oauth', 'GET', [
            'response_type' => 'code',
            'client_id' => \uniqid()
        ]);

        $request = AuthorizationRequest::create($requestMock);

        static::assertNull($request->getValue('code'));
        static::assertNotNull($request->getValue('client_id'));
        static::assertNotNull($request->getValue('response_type'));
        static::assertArrayHasKey('client_id', $request->returnAsReadOnly());
        static::assertArrayHasKey('response_type', $request->returnAsReadOnly());
    }

    public function testCreationFromPsr7Request()
    {
        $requestMock = $this->createMock(ServerRequestInterface::class);
        $requestMock->method('getQueryParams')->willReturn([
            'response_type' => 'code',
            'client_id' => \uniqid()
        ]);
        $requestMock->method('getParsedBody')->willReturn([]);
        $requestMock->method('getServerParams')->willReturn([]);

        $request = AuthorizationRequest::create($requestMock);

        static::assertNull($request->getValue('code'));
        static::assertNotNull($request->getValue('client_id'));
        static::assertNotNull($request->getValue('response_type'));
        static::assertArrayHasKey('client_id', $request->returnAsReadOnly());
        static::assertArrayHasKey('response_type', $request->returnAsReadOnly());
    }
}
