<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AccessToken\Tests\Unit\Bridge\OAuth;

use PHPUnit\Framework\TestCase;
use Symfony\Component\AccessToken\Bridge\OAuth\ClientCredentials;

/**
 * @author Pierre Rineau <pierre.rineau@processus.org>
 */
class ClientCredentialsTest extends TestCase
{
    public function testBasics(): void
    {
        $credentials = new ClientCredentials('superclientid', 'supersecret', 'sometenant', 'scope_1 scope_2', 'https://some.endpoint/token');

        self::assertSame('client_credentials', $credentials->getGrantType());

        self::assertSame('superclientid', $credentials->getClientId());
        self::assertSame('supersecret', $credentials->getClientSecret());
        self::assertSame('sometenant', $credentials->getTenant());
        self::assertSame(['scope_1', 'scope_2'], $credentials->getScope());
        self::assertSame('scope_1 scope_2', $credentials->getScopeAsString());
        self::assertSame('https://some.endpoint/token', $credentials->getEndpoint());
    }

    public function testComputeId(): void
    {
        $credentials = new ClientCredentials('superclientid', 'supersecret', 'sometenant', 'scope_1 scope_2', 'https://some.endpoint/token');

        $reference = md5('https://some.endpoint/token' . 'superclientid' . 'sometenant' . 'scope_1 scope_2');

        self::assertSame($reference, $credentials->getId());
    }

    public function testCreateRefreshToken(): void
    {
        $credentials = new ClientCredentials('superclientid', 'supersecret', 'sometenant', 'scope_1 scope_2', 'https://some.endpoint/token');

        $refreshToken = $credentials->createRefreshToken('refresh_token_value');

        self::assertSame('refresh_token', $refreshToken->getGrantType());

        self::assertSame('refresh_token_value', $refreshToken->getRefreshToken());
        self::assertSame('superclientid', $refreshToken->getClientId());
        self::assertSame('supersecret', $refreshToken->getClientSecret());
        self::assertSame('sometenant', $refreshToken->getTenant());
        self::assertSame(['scope_1', 'scope_2'], $refreshToken->getScope());
        self::assertSame('scope_1 scope_2', $refreshToken->getScopeAsString());
        self::assertSame('https://some.endpoint/token', $refreshToken->getEndpoint());

        self::assertSame($credentials->getId(), $refreshToken->getId());
    }
}
