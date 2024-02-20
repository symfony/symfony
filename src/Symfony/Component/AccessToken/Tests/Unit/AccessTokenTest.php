<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare (strict_types=1);

namespace Symfony\Component\AccessToken\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\AccessToken\AccessToken;

/**
 * @author Pierre Rineau <pierre.rineau@processus.org>
 */
class AccessTokenTest extends TestCase
{
    public function testBasics(): void
    {
        $now = new \DateTimeImmutable();
        $accessToken = new AccessToken('token_value', 'some_type', 571, $now, 'foo');

        self::assertSame('token_value', $accessToken->getValue());
        self::assertSame('token_value', (string) $accessToken);
        self::assertSame(571, $accessToken->getExpiresIn());
        self::assertSame($now, $accessToken->getIssuedAt());
        self::assertSame('some_type', $accessToken->getType());
        self::assertSame('foo', $accessToken->getCredentialsId());
    }

    public function testExpiresAt(): void
    {
        $issuedAt = new \DateTimeImmutable('2024-02-16 11:27:00');
        $accessToken = new AccessToken(value: 'token_value', issuedAt: $issuedAt, expiresIn: 27);

        self::assertSame('2024-02-16 11:27:27', $accessToken->getExpiresAt()->format('Y-m-d H:i:s'));
    }

    public function testHasExpired(): void
    {
        $issuedAt = new \DateTimeImmutable('1998-02-16 11:27:00');
        $accessToken = new AccessToken(value: 'token_value', issuedAt: $issuedAt, expiresIn: 27);

        self::assertTrue($accessToken->hasExpired());

        $issuedAt = new \DateTimeImmutable();
        $accessToken = new AccessToken(value: 'token_value', issuedAt: $issuedAt, expiresIn: 100000000);

        self::assertFalse($accessToken->hasExpired());
    }

    public function testHasExpiredAt()
    {
        $issuedAt = new \DateTimeImmutable('1998-02-16 11:27:00');
        $accessToken = new AccessToken(value: 'token_value', issuedAt: $issuedAt, expiresIn: 27);

        self::assertTrue($accessToken->hasExpired());
        self::assertFalse($accessToken->hasExpiredAt(new \DateTimeImmutable('1998-02-16 11:27:25')));
    }
}
