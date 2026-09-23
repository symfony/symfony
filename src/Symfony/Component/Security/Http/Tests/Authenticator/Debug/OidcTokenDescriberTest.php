<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\Authenticator\Debug;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Http\Authenticator\Debug\OidcTokenDescriber;

class OidcTokenDescriberTest extends TestCase
{
    public function testDescribeDecodesAJwt()
    {
        $jwt = self::encode(['alg' => 'RS256', 'kid' => 'key-1', 'typ' => 'JWT']).'.'.self::encode(['sub' => 'user-42', 'exp' => 1700000000]).'.signature';

        $description = OidcTokenDescriber::describe($jwt);

        $this->assertSame('jwt', $description['format']);
        $this->assertSame(substr(hash('sha256', $jwt), 0, 8), $description['fingerprint']);
        $this->assertSame(['alg' => 'RS256', 'kid' => 'key-1', 'typ' => 'JWT'], $description['header']);
        $this->assertSame(['sub' => 'user-42', 'exp' => 1700000000], $description['claims']);
        $this->assertSame(1700000000, $description['expires_at']);

        // neither the compact form nor the signature is part of the description
        $this->assertStringNotContainsString('signature', json_encode($description));
        $this->assertStringNotContainsString($jwt, json_encode($description));
    }

    public function testDescribeKeepsTheHeaderOfAJwe()
    {
        $jwe = self::encode(['alg' => 'RSA-OAEP', 'enc' => 'A256GCM']).'.key.iv.ciphertext.tag';

        $description = OidcTokenDescriber::describe($jwe);

        $this->assertSame('jwe', $description['format']);
        $this->assertSame(['alg' => 'RSA-OAEP', 'enc' => 'A256GCM'], $description['header']);
        $this->assertNull($description['claims']);
        $this->assertNull($description['expires_at']);
    }

    #[DataProvider('provideOpaqueTokens')]
    public function testDescribeReportsAnythingElseAsOpaque(string $token)
    {
        $description = OidcTokenDescriber::describe($token);

        $this->assertSame('opaque', $description['format']);
        $this->assertSame(8, \strlen($description['fingerprint']));
        $this->assertNull($description['header']);
        $this->assertNull($description['claims']);
        $this->assertNull($description['expires_at']);

        if ('' !== $token) {
            $this->assertStringNotContainsString($token, json_encode($description));
        }
    }

    public static function provideOpaqueTokens(): iterable
    {
        yield 'random string' => ['5f3a9c2e-opaque-token'];
        yield 'two segments' => [self::encode(['alg' => 'RS256']).'.'.self::encode(['sub' => 'user-42'])];
        yield 'header that is not JSON' => ['not-json.'.self::encode(['sub' => 'user-42']).'.signature'];
        yield 'header that is not an object' => [self::encode('"RS256"').'.'.self::encode(['sub' => 'user-42']).'.signature'];
        yield 'payload that is not JSON' => [self::encode(['alg' => 'RS256']).'.not-json.signature'];
        yield 'invalid base64url' => ['$$$.$$$.$$$'];
        yield 'empty' => [''];
    }

    public function testDescribeIgnoresANonNumericExpiry()
    {
        $jwt = self::encode(['alg' => 'RS256']).'.'.self::encode(['exp' => 'soon']).'.signature';

        $this->assertNull(OidcTokenDescriber::describe($jwt)['expires_at']);
    }

    public function testDescribeAcceptsAPaddedSegment()
    {
        // base64url segments carry no padding, but a hand-made token may
        $jwt = base64_encode('{"alg":"RS256"}').'.'.base64_encode('{"sub":"user-42"}').'.signature';

        $this->assertSame(['sub' => 'user-42'], OidcTokenDescriber::describe($jwt)['claims']);
    }

    private static function encode(mixed $value): string
    {
        return rtrim(strtr(base64_encode(json_encode($value)), '+/', '-_'), '=');
    }
}
