<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\PrivacyPass;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Http\PrivacyPass\BlindRsaTokenKey;
use Symfony\Component\Security\Http\PrivacyPass\BlindRsaTokenType;
use Symfony\Component\Security\Http\Tests\PrivacyPass\Fixtures\PrivacyPassTestVectors;

class BlindRsaTokenTypeTest extends TestCase
{
    public function testItDeclaresTheStructureOfTheRegistry()
    {
        $tokenType = new BlindRsaTokenType();

        $this->assertSame(0x0002, $tokenType->getValue());
        $this->assertSame(32, $tokenType->getKeyIdLength());
        $this->assertSame(256, $tokenType->getAuthenticatorLength());
    }

    public function testItCreatesAKeyFromTheTokenKeyParameter()
    {
        $tokenKey = (new BlindRsaTokenType())->createTokenKey(PrivacyPassTestVectors::HEADER_TOKEN_KEY_BASE64URL);

        $this->assertInstanceOf(BlindRsaTokenKey::class, $tokenKey);
        $this->assertSame(PrivacyPassTestVectors::TOKEN_KEY_ID, bin2hex($tokenKey->getId()));
    }

    public function testItRejectsAKeyThatIsNotBase64Url()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The token key is not base64url-encoded.');

        (new BlindRsaTokenType())->createTokenKey('@@@');
    }

    public function testItRejectsAKeyOfAnotherKind()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The token key must use the id-RSASSA-PSS object identifier.');

        (new BlindRsaTokenType())->createTokenKey(strtr(base64_encode(hex2bin(PrivacyPassTestVectors::TOKEN_KEY_RSA_ENCRYPTION)), '+/', '-_'));
    }
}
