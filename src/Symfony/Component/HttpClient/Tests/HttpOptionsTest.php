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
    public static function provideSetAuthBasic(): iterable
    {
        yield ['user:password', 'user', 'password'];
        yield ['user:password', 'user:password'];
        yield ['user', 'user'];
        yield ['user:0', 'user', '0'];
    }

    /**
     * @dataProvider provideSetAuthBasic
     */
    public function testSetAuthBasic(string $expected, string $user, string $password = '')
    {
        $this->assertSame($expected, (new HttpOptions())->setAuthBasic($user, $password)->toArray()['auth_basic']);
    }

    public function testSetAuthBearer()
    {
        $this->assertSame('foobar', (new HttpOptions())->setAuthBearer('foobar')->toArray()['auth_bearer']);
    }

    public function testSetHeader()
    {
        $options = new HttpOptions();
        $options->setHeader('Accept', 'application/json');
        $this->assertSame(['Accept' => 'application/json'], $options->toArray()['headers']);
        $options->setHeader('Accept-Language', 'en-US,en;q=0.5');
        $this->assertSame(['Accept' => 'application/json', 'Accept-Language' => 'en-US,en;q=0.5'], $options->toArray()['headers']);
        $options->setHeader('Accept', 'application/html');
        $this->assertSame(['Accept' => 'application/html', 'Accept-Language' => 'en-US,en;q=0.5'], $options->toArray()['headers']);
    }
}
