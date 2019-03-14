<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\HttpOptions;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class HttpOptionsTest extends TestCase
{
    public function provideSetAuth()
    {
        yield ['user:password', 'user', 'password'];
        yield ['user:password', 'user:password'];
        yield ['user', 'user'];
        yield ['user:0', 'user', '0'];
    }

    /**
     * @dataProvider provideSetAuth
     */
    public function testSetAuth(string $expected, string $user, string $password = '')
    {
        $this->assertSame($expected, (new HttpOptions())->setAuthBasic($user, $password)->toArray()['auth_basic']);
    }
}
