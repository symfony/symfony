<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\RememberMe;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\RememberMe\PersistentToken;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Http\RememberMe\RememberMeDetails;

class RememberMeDetailsTest extends TestCase
{
    public function testFromRawCookie()
    {
        $rememberMeDetails = RememberMeDetails::fromRawCookie(self::getRememberMeCookieValue());

        $this->assertSame(RememberMeDetails::class, $rememberMeDetails::class);
        $this->assertSame('wouter', $rememberMeDetails->getUserIdentifier());
        $this->assertSame(360, $rememberMeDetails->getExpires());
        $this->assertSame('series1:token_value', $rememberMeDetails->getValue());
    }

    public function testFromRawCookieChildClassWithNewConstructorSignature()
    {
        $rememberMeDetails = RememberMeDetailsChild::fromRawCookie(self::getRememberMeCookieValue());

        $this->assertSame(RememberMeDetailsChild::class, $rememberMeDetails::class);
        $this->assertSame('wouter', $rememberMeDetails->getUserIdentifier());
        $this->assertSame(360, $rememberMeDetails->getExpires());
        $this->assertSame('series1:token_value', $rememberMeDetails->getValue());
    }

    public function testFromRawCookieChildClassWithoutConstructor()
    {
        $rememberMeDetails = RememberMeDetailsChildWithoutConstructor::fromRawCookie(self::getRememberMeCookieValue());

        $this->assertSame(RememberMeDetailsChildWithoutConstructor::class, $rememberMeDetails::class);
        $this->assertSame('wouter', $rememberMeDetails->getUserIdentifier());
        $this->assertSame(360, $rememberMeDetails->getExpires());
        $this->assertSame('series1:token_value', $rememberMeDetails->getValue());
    }

    public function testFromLegacyRawCookie()
    {
        $rememberMeDetails = RememberMeDetails::fromRawCookie(self::getLegacyRememberMeCookieValue());

        $this->assertSame(RememberMeDetails::class, $rememberMeDetails::class);
        $this->assertSame('wouter', $rememberMeDetails->getUserIdentifier());
        $this->assertSame(360, $rememberMeDetails->getExpires());
        $this->assertSame('series1:token_value', $rememberMeDetails->getValue());
    }

    public function testFromLegacyRawCookieChildClassWithNewConstructorSignature()
    {
        $rememberMeDetails = RememberMeDetailsChild::fromRawCookie(self::getLegacyRememberMeCookieValue());

        $this->assertSame(RememberMeDetailsChild::class, $rememberMeDetails::class);
        $this->assertSame('wouter', $rememberMeDetails->getUserIdentifier());
        $this->assertSame(360, $rememberMeDetails->getExpires());
        $this->assertSame('series1:token_value', $rememberMeDetails->getValue());
    }

    public function testFromLegacyRawCookieChildClassWithoutConstructor()
    {
        $rememberMeDetails = RememberMeDetailsChildWithoutConstructor::fromRawCookie(self::getLegacyRememberMeCookieValue());

        $this->assertSame(RememberMeDetailsChildWithoutConstructor::class, $rememberMeDetails::class);
        $this->assertSame('wouter', $rememberMeDetails->getUserIdentifier());
        $this->assertSame(360, $rememberMeDetails->getExpires());
        $this->assertSame('series1:token_value', $rememberMeDetails->getValue());
    }

    public function testFromPersistentToken()
    {
        if (method_exists(PersistentToken::class, 'getClass')) {
            $token = new PersistentToken(InMemoryUser::class, 'wouter', 'series1', 'token_value', new \DateTimeImmutable(), false);
        } else {
            $token = new PersistentToken('wouter', 'series1', 'token_value', new \DateTimeImmutable());
        }

        $rememberMeDetails = RememberMeDetails::fromPersistentToken($token, 360);

        $this->assertSame(RememberMeDetails::class, $rememberMeDetails::class);
        $this->assertSame('wouter', $rememberMeDetails->getUserIdentifier());
        $this->assertSame(360, $rememberMeDetails->getExpires());
        $this->assertSame('series1:token_value', $rememberMeDetails->getValue());
    }

    public function testFromPersistentTokenChildClassWithNewConstructorSignature()
    {
        if (method_exists(PersistentToken::class, 'getClass')) {
            $token = new PersistentToken(InMemoryUser::class, 'wouter', 'series1', 'token_value', new \DateTimeImmutable(), false);
        } else {
            $token = new PersistentToken('wouter', 'series1', 'token_value', new \DateTimeImmutable());
        }

        $rememberMeDetails = RememberMeDetailsChild::fromPersistentToken($token, 360);

        $this->assertSame(RememberMeDetailsChild::class, $rememberMeDetails::class);
        $this->assertSame('wouter', $rememberMeDetails->getUserIdentifier());
        $this->assertSame(360, $rememberMeDetails->getExpires());
        $this->assertSame('series1:token_value', $rememberMeDetails->getValue());
    }

    public function testFromPersistentTokenChildClassWithoutConstructor()
    {
        if (method_exists(PersistentToken::class, 'getClass')) {
            $token = new PersistentToken(InMemoryUser::class, 'wouter', 'series1', 'token_value', new \DateTimeImmutable(), false);
        } else {
            $token = new PersistentToken('wouter', 'series1', 'token_value', new \DateTimeImmutable());
        }

        $rememberMeDetails = RememberMeDetailsChildWithoutConstructor::fromPersistentToken($token, 360);

        $this->assertSame(RememberMeDetailsChildWithoutConstructor::class, $rememberMeDetails::class);
        $this->assertSame('wouter', $rememberMeDetails->getUserIdentifier());
        $this->assertSame(360, $rememberMeDetails->getExpires());
        $this->assertSame('series1:token_value', $rememberMeDetails->getValue());
    }

    private static function getRememberMeCookieValue(): string
    {
        return base64_encode((new RememberMeDetails('wouter', 360, 'series1:token_value'))->toString());
    }

    private static function getLegacyRememberMeCookieValue(): string
    {
        return base64_encode(strtr(InMemoryUser::class, '\\', '.').(new RememberMeDetails('wouter', 360, 'series1:token_value'))->toString());
    }
}

class RememberMeDetailsChild extends RememberMeDetails
{
    public function __construct(string $userIdentifier, int $expires, string $value)
    {
        parent::__construct($userIdentifier, $expires, $value);
    }
}

class RememberMeDetailsChildWithoutConstructor extends RememberMeDetails
{
}
