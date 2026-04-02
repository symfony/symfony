<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\Authenticator\Oidc;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Http\Authenticator\Oidc\OidcTokens;

class OidcTokensTest extends TestCase
{
    public function testFromTokenEndpointResponse()
    {
        $tokens = OidcTokens::fromTokenEndpointResponse([
            'access_token' => 'access-123',
            'id_token' => 'id-456',
            'refresh_token' => 'refresh-789',
            'expires_in' => 3600,
        ]);

        $this->assertSame('access-123', $tokens->accessToken);
        $this->assertSame('id-456', $tokens->idToken);
        $this->assertSame('refresh-789', $tokens->refreshToken);
        $this->assertSame(3600, $tokens->expiresIn);
    }

    public function testFromTokenEndpointResponseMinimal()
    {
        $tokens = OidcTokens::fromTokenEndpointResponse([
            'access_token' => 'access-123',
            'id_token' => 'id-456',
        ]);

        $this->assertSame('access-123', $tokens->accessToken);
        $this->assertSame('id-456', $tokens->idToken);
        $this->assertNull($tokens->refreshToken);
        $this->assertNull($tokens->expiresIn);
    }

    public function testFromTokenEndpointResponseMissingAccessToken()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('"access_token" and "id_token"');

        OidcTokens::fromTokenEndpointResponse(['id_token' => 'id-456']);
    }

    public function testFromTokenEndpointResponseMissingIdToken()
    {
        $this->expectException(\InvalidArgumentException::class);

        OidcTokens::fromTokenEndpointResponse(['access_token' => 'access-123']);
    }
}
