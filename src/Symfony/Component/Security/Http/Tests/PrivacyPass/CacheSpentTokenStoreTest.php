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
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Security\Http\PrivacyPass\BlindRsaTokenType;
use Symfony\Component\Security\Http\PrivacyPass\CacheSpentTokenStore;
use Symfony\Component\Security\Http\PrivacyPass\PrivateToken;
use Symfony\Component\Security\Http\Tests\PrivacyPass\Fixtures\PrivacyPassTestVectors;

class CacheSpentTokenStoreTest extends TestCase
{
    public function testATokenIsSpentOnce()
    {
        $store = new CacheSpentTokenStore(new ArrayAdapter());
        $token = self::token(0);

        $this->assertTrue($store->spend($token));
        $this->assertFalse($store->spend($token));
        $this->assertFalse($store->spend(self::token(0)));
    }

    public function testEachTokenIsSpentOnItsOwn()
    {
        $store = new CacheSpentTokenStore(new ArrayAdapter());

        $this->assertTrue($store->spend(self::token(0)));
        $this->assertTrue($store->spend(self::token(1)));
    }

    public function testASpentTokenIsForgottenAfterItsLifetime()
    {
        $clock = new MockClock('2026-10-23 12:00:00');
        $store = new CacheSpentTokenStore(new ArrayAdapter(0, true, 0, 0, $clock), 60);
        $token = self::token(0);

        $this->assertTrue($store->spend($token));
        $clock->sleep(59);
        $this->assertFalse($store->spend($token));
        $clock->sleep(2);
        $this->assertTrue($store->spend($token));
    }

    public function testItRejectsALifetimeOfNoDuration()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The lifetime of a spent token must be at least 1 second, "0" given.');

        new CacheSpentTokenStore(new ArrayAdapter(), 0);
    }

    private static function token(int $vector): PrivateToken
    {
        return PrivateToken::fromBinary(hex2bin(PrivacyPassTestVectors::TOKENS[$vector]['token']), new BlindRsaTokenType());
    }
}
