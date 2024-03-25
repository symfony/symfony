<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AccessToken\Tests\Unit\Credentials;

use PHPUnit\Framework\TestCase;
use Symfony\Component\AccessToken\Credentials\Dsn;
use Symfony\Component\AccessToken\Exception\InvalidArgumentException;

/**
 * @author Pierre Rineau <pierre.rineau@processus.org>
 */
class DsnTest extends TestCase
{
    public function testBasics(): void
    {
        $dsn = Dsn::fromString('oauth://someUser:somePass@thirdpartyprovider.com:1234/oauth/2.0/token?grant_type=client_credentials&scope=someScope');

        self::assertSame('oauth', $dsn->getScheme());
        self::assertSame('someUser', $dsn->getUser());
        self::assertSame('somePass', $dsn->getPassword());
        self::assertSame(1234, $dsn->getPort());
        self::assertSame('thirdpartyprovider.com', $dsn->getHost());
        self::assertSame('/oauth/2.0/token', $dsn->getPath());
        self::assertSame('client_credentials', $dsn->getOption('grant_type'));
        self::assertSame('someScope', $dsn->getOption('scope'));
        self::assertNull($dsn->getOption('non_existing_option'));
    }

    public function testUserPassAreDecoded(): void
    {
        $dsn = Dsn::fromString('oauth://some%20user:some%20password@thirdpartyprovider.com');

        self::assertSame('some user', $dsn->getUser());
        self::assertSame('some password', $dsn->getPassword());
    }

    public function testNoSchemeRaiseException(): void
    {
        self::expectException(InvalidArgumentException::class);
        Dsn::fromString('some%20user:some%20password@thirdpartyprovider.com');
    }

    public function testNoHostRaiseException(): void
    {
        self::expectException(InvalidArgumentException::class);
        Dsn::fromString('oauth://some%20user:some%20password@?foo=bar');
    }

    public function testToEndpointUrl(): void
    {
        $dsn = Dsn::fromString('oauth://someUser:somePass@thirdpartyprovider.com:1234/oauth/2.0/token?foo=bar&fizz=buzz');

        self::assertSame('https://thirdpartyprovider.com:1234/oauth/2.0/token?fizz=buzz', $dsn->toEndpointUrl(['foo']));
    }
}
