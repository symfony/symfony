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
use Symfony\Component\Security\Http\Authenticator\Oidc\OidcConfiguration;

class OidcConfigurationTest extends TestCase
{
    public function testFromArray()
    {
        $config = OidcConfiguration::fromArray([
            'authorization_endpoint' => 'https://provider.example.com/authorize',
            'token_endpoint' => 'https://provider.example.com/token',
            'issuer' => 'https://provider.example.com',
            'jwks_uri' => 'https://provider.example.com/jwks',
            'userinfo_endpoint' => 'https://provider.example.com/userinfo',
            'end_session_endpoint' => 'https://provider.example.com/logout',
            'code_challenge_methods_supported' => ['S256'],
        ]);

        $this->assertSame('https://provider.example.com/authorize', $config->authorizationEndpoint);
        $this->assertSame('https://provider.example.com/token', $config->tokenEndpoint);
        $this->assertSame('https://provider.example.com', $config->issuer);
        $this->assertSame('https://provider.example.com/jwks', $config->jwksUri);
        $this->assertSame('https://provider.example.com/userinfo', $config->userinfoEndpoint);
        $this->assertSame('https://provider.example.com/logout', $config->endSessionEndpoint);
        $this->assertSame(['S256'], $config->codeChallengeMethodsSupported);
    }

    public function testFromArrayMinimal()
    {
        $config = OidcConfiguration::fromArray([
            'authorization_endpoint' => 'https://provider.example.com/authorize',
            'token_endpoint' => 'https://provider.example.com/token',
            'issuer' => 'https://provider.example.com',
            'jwks_uri' => 'https://provider.example.com/jwks',
        ]);

        $this->assertNull($config->userinfoEndpoint);
        $this->assertNull($config->endSessionEndpoint);
        $this->assertSame([], $config->codeChallengeMethodsSupported);
    }

    public function testFromArrayMissingRequiredField()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('"authorization_endpoint"');

        OidcConfiguration::fromArray([
            'token_endpoint' => 'https://provider.example.com/token',
            'issuer' => 'https://provider.example.com',
            'jwks_uri' => 'https://provider.example.com/jwks',
        ]);
    }

    public function testFromArrayEmptyRequiredField()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('"issuer"');

        OidcConfiguration::fromArray([
            'authorization_endpoint' => 'https://provider.example.com/authorize',
            'token_endpoint' => 'https://provider.example.com/token',
            'issuer' => '',
            'jwks_uri' => 'https://provider.example.com/jwks',
        ]);
    }
}
