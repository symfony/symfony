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
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\RememberMe\RememberMeDetails;

class RememberMeDetailsTest extends TestCase
{
    public function testFromRawCookie()
    {
        $details = RememberMeDetails::fromRawCookie('App.User:dXNlcg~~:1700000000:value');

        $this->assertSame('App\User', $details->getUserFqcn());
        $this->assertSame('user', $details->getUserIdentifier());
        $this->assertSame(1700000000, $details->getExpires());
        $this->assertSame('value', $details->getValue());
    }

    public function testFromRawCookieWithZeroExpires()
    {
        $details = RememberMeDetails::fromRawCookie('App.User:dXNlcg~~:0:value');

        $this->assertSame(0, $details->getExpires());
    }

    public function testFromRawCookieWithMaximumExpires()
    {
        $details = RememberMeDetails::fromRawCookie(base64_encode('App.User:dXNlcg~~:'.\PHP_INT_MAX.':value'));

        $this->assertSame(\PHP_INT_MAX, $details->getExpires());
    }

    public function testFromRawCookieWithEmptyExpires()
    {
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('The cookie contains invalid data.');

        RememberMeDetails::fromRawCookie('App.User:dXNlcg~~::value');
    }

    public function testFromRawCookieWithNonNumericExpires()
    {
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('The cookie contains invalid data.');

        RememberMeDetails::fromRawCookie('App.User:dXNlcg~~:abc:value');
    }

    public function testFromRawCookieWithFloatExpires()
    {
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('The cookie contains invalid data.');

        RememberMeDetails::fromRawCookie('App.User:dXNlcg~~:1.5:value');
    }

    public function testFromRawCookieWithNegativeExpires()
    {
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('The cookie contains invalid data.');

        RememberMeDetails::fromRawCookie('App.User:dXNlcg~~:-1:value');
    }

    public function testFromRawCookieWithOverflowingExpires()
    {
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('The cookie contains invalid data.');

        RememberMeDetails::fromRawCookie('App.User:dXNlcg~~:99999999999999999999:value');
    }
}
