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

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
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

    #[Group('legacy')]
    #[IgnoreDeprecations]
    public function testFromRawCookieChildClassWithLegacyConstructorSignature()
    {
        $this->expectUserDeprecationMessage(\sprintf('Since symfony/security-http 7.4: Extending the "%s" class and overriding the constructor with four required arguments is deprecated. Change the constructor signature to __construct(string $userIdentifier, int $expires, string $value).', RememberMeDetails::class));

        $rememberMeDetails = RememberMeDetailsChildLegacyConstructorSignature::fromRawCookie(self::getRememberMeCookieValue());

        $this->assertSame(RememberMeDetailsChildLegacyConstructorSignature::class, $rememberMeDetails::class);
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

    #[Group('legacy')]
    #[IgnoreDeprecations]
    public function testFromLegacyRawCookie()
    {
        $rememberMeDetails = RememberMeDetails::fromRawCookie(self::getLegacyRememberMeCookieValue());

        $this->assertSame(RememberMeDetails::class, $rememberMeDetails::class);
        $this->assertSame(InMemoryUser::class, $rememberMeDetails->getUserFqcn());
        $this->assertSame('wouter', $rememberMeDetails->getUserIdentifier());
        $this->assertSame(360, $rememberMeDetails->getExpires());
        $this->assertSame('series1:token_value', $rememberMeDetails->getValue());
    }

    #[Group('legacy')]
    #[IgnoreDeprecations]
    public function testFromLegacyRawCookieChildClassWithNewConstructorSignature()
    {
        $rememberMeDetails = RememberMeDetailsChild::fromRawCookie(self::getLegacyRememberMeCookieValue());

        $this->assertSame(RememberMeDetailsChild::class, $rememberMeDetails::class);
        $this->assertSame('', $rememberMeDetails->getUserFqcn());
        $this->assertSame('wouter', $rememberMeDetails->getUserIdentifier());
        $this->assertSame(360, $rememberMeDetails->getExpires());
        $this->assertSame('series1:token_value', $rememberMeDetails->getValue());
    }

    #[Group('legacy')]
    #[IgnoreDeprecations]
    public function testFromLegacyRawCookieChildClassWithLegacyConstructorSignature()
    {
        $this->expectUserDeprecationMessage(\sprintf('Since symfony/security-http 7.4: Extending the "%s" class and overriding the constructor with four required arguments is deprecated. Change the constructor signature to __construct(string $userIdentifier, int $expires, string $value).', RememberMeDetails::class));

        $rememberMeDetails = RememberMeDetailsChildLegacyConstructorSignature::fromRawCookie(self::getLegacyRememberMeCookieValue());

        $this->assertSame(RememberMeDetailsChildLegacyConstructorSignature::class, $rememberMeDetails::class);
        $this->assertSame(InMemoryUser::class, $rememberMeDetails->getUserFqcn());
        $this->assertSame('wouter', $rememberMeDetails->getUserIdentifier());
        $this->assertSame(360, $rememberMeDetails->getExpires());
        $this->assertSame('series1:token_value', $rememberMeDetails->getValue());
    }

    #[Group('legacy')]
    #[IgnoreDeprecations]
    public function testFromLegacyRawCookieChildClassWithoutConstructor()
    {
        $rememberMeDetails = RememberMeDetailsChildWithoutConstructor::fromRawCookie(self::getLegacyRememberMeCookieValue());

        $this->assertSame(RememberMeDetailsChildWithoutConstructor::class, $rememberMeDetails::class);
        $this->assertSame(InMemoryUser::class, $rememberMeDetails->getUserFqcn());
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

    #[Group('legacy')]
    #[IgnoreDeprecations]
    public function testFromPersistentTokenChildClassWithLegacyConstructorSignature()
    {
        $this->expectUserDeprecationMessage(\sprintf('Since symfony/security-http 7.4: Extending the "%s" class and overriding the constructor with four required arguments is deprecated. Change the constructor signature to __construct(string $userIdentifier, int $expires, string $value).', RememberMeDetails::class));

        if (method_exists(PersistentToken::class, 'getClass')) {
            $token = new PersistentToken(InMemoryUser::class, 'wouter', 'series1', 'token_value', new \DateTimeImmutable(), false);
        } else {
            $token = new PersistentToken('wouter', 'series1', 'token_value', new \DateTimeImmutable());
        }

        $rememberMeDetails = RememberMeDetailsChildLegacyConstructorSignature::fromPersistentToken($token, 360);

        $this->assertSame(RememberMeDetailsChildLegacyConstructorSignature::class, $rememberMeDetails::class);
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
        return base64_encode((new RememberMeDetails(InMemoryUser::class, 'wouter', 360, 'series1:token_value', false))->toString());
    }
}

class RememberMeDetailsChild extends RememberMeDetails
{
    public function __construct(string $userIdentifier, int $expires, string $value)
    {
        parent::__construct($userIdentifier, $expires, $value);
    }
}

class RememberMeDetailsChildLegacyConstructorSignature extends RememberMeDetails
{
    public function __construct(string $userFqcn, string $userIdentifier, int $expires, string $value)
    {
        parent::__construct($userFqcn, $userIdentifier, $expires, $value);
    }
}

class RememberMeDetailsChildWithoutConstructor extends RememberMeDetails
{
}
