<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\OAuth\Tests\Server;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\OAuthServer\Exception\MissingGrantTypeException;
use Symfony\Component\Security\OAuthServer\AuthorizationServer;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class AuthorizationServerTest extends TestCase
{
    /**
     * @dataProvider provideWrongGrantTypes
     */
    public function testWrongGrantType(array $grantTypes)
    {
        static::expectException(MissingGrantTypeException::class);

        $requestMock = Request::create('/oauth', 'GET');

        (new AuthorizationServer($grantTypes))->handle($requestMock);
    }

    public function provideWrongGrantTypes(): \Generator
    {
        yield 'Empty grant types' => [
            []
        ];
    }
}
