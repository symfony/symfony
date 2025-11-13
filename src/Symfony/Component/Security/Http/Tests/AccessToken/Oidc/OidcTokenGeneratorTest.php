<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\AccessToken\Oidc;

use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Core\JWK;
use Jose\Component\Core\JWKSet;
use Jose\Component\Signature\Algorithm\ES256;
use Jose\Component\Signature\Algorithm\ES512;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Security\Http\AccessToken\Oidc\OidcTokenGenerator;
use Symfony\Component\Security\Http\AccessToken\Oidc\OidcTokenHandler;

#[RequiresPhpExtension('openssl')]
class OidcTokenGeneratorTest extends TestCase
{
    public function testGenerate()
    {
        $algorithmManager = new AlgorithmManager([new ES256()]);
        $audience = 'Symfony OIDC';
        $issuers = ['https://www.example.com'];
        $clock = new MockClock('1998-07-12T22:45:00+02:00');

        $generator = new OidcTokenGenerator($algorithmManager, $this->getJWKSet(), $audience, $issuers, clock: $clock);
        $handler = new OidcTokenHandler($algorithmManager, $this->getJWKSet(), $audience, $issuers, clock: $clock);

        $token = $generator->generate('john_doe');

        $badge = $handler->getUserBadgeFrom($token);
        $this->assertSame('john_doe', $badge->getUser()->getUserIdentifier());
        $this->assertSame([
            'sub' => 'john_doe',
            'iat' => 900276300,
            'aud' => 'Symfony OIDC',
            'iss' => 'https://www.example.com',
        ], $badge->getAttributes());
    }

    #[DataProvider('provideGenerateWithInvalid')]
    public function testGenerateWithInvalid(?string $algorithm, ?string $issuer, ?int $ttl, ?int $notBefore, string $expectedMessage)
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessage);

        $generator = new OidcTokenGenerator(
            new AlgorithmManager([new ES256(), new ES512()]),
            $this->getJWKSet(),
            'Symfony OIDC',
            ['https://www.example1.com', 'https://www.example2.com'],
        );
        $generator->generate('john_doe', $algorithm, $issuer, $ttl, $notBefore);
    }

    public static function provideGenerateWithInvalid(): iterable
    {
        yield 'No algorithms' => [null, 'https://www.example1.com', null, null, 'Please choose an algorithm. Available algorithms: "ES256", "ES512"'];
        yield 'Invalid algorithm' => ['ES384', 'https://www.example1.com', null, null, '"ES384" is not a valid algorithm. Available algorithms: "ES256", "ES512"'];
        yield 'No issuers' => ['ES256', null, null, null, 'Please choose an issuer. Available issuers: "https://www.example1.com", "https://www.example2.com"'];
        yield 'Invalid issuer' => ['ES256', 'https://www.invalid.com', null, null, '"https://www.invalid.com" is not a valid issuer. Available issuers: "https://www.example1.com", "https://www.example2.com"'];
        yield 'Invalid TTL' => ['ES256', 'https://www.example1.com', -1, null, 'Time to live must be a positive integer.'];
    }

    private static function getJWKSet(): JWKSet
    {
        return new JWKSet([
            new JWK([
                'kty' => 'EC',
                'crv' => 'P-256',
                'x' => 'FtgMtrsKDboRO-Zo0XC7tDJTATHVmwuf9GK409kkars',
                'y' => 'rWDE0ERU2SfwGYCo1DWWdgFEbZ0MiAXLRBBOzBgs_jY',
                'd' => '4G7bRIiKih0qrFxc0dtvkHUll19tTyctoCR3eIbOrO0',
            ]),
        ]);
    }
}
