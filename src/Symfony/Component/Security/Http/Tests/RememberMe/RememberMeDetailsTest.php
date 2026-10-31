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

    /**
     * @dataProvider provideInvalidExpires
     */
    public function testFromRawCookieWithInvalidExpires(string $expires)
    {
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('The cookie contains invalid data.');

        RememberMeDetails::fromRawCookie('App.User:dXNlcg~~:'.$expires.':value');
    }

    public static function provideInvalidExpires(): iterable
    {
        yield 'empty' => [''];
        yield 'non-numeric' => ['abc'];
        yield 'float' => ['1.5'];
        yield 'negative' => ['-1'];
        yield 'overflow' => ['99999999999999999999'];
    }
}
