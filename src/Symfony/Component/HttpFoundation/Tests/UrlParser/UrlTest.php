<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Tests\UrlParser;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\UrlParser\Url;

class UrlTest extends TestCase
{
    /**
     * @dataProvider provideUserAndPass
     */
    public function testIsAuthenticated(?string $user, ?string $pass, bool $expected)
    {
        $params = new Url('http', $user, $pass);

        $this->assertSame($expected, $params->isAuthenticated());
    }

    public function provideUserAndPass()
    {
        yield 'no user, no pass' => [null, null, false];
        yield 'user, no pass' => ['user', null, true];
        yield 'no user, pass' => [null, 'pass', true];
        yield 'user, pass' => ['user', 'pass', true];
    }

    public function testToString()
    {
        $params = new Url(
            'http',
            'user',
            'pass',
            'localhost',
            8080,
            '/path',
            'query=1',
            'fragment'
        );

        $this->assertSame('http://user:pass@localhost:8080/path?query=1#fragment', (string) $params);
    }

    public function testToStringReencode()
    {
        $params = new Url(
            'http',
            'user one',
            'p@ss',
            'localhost',
            8080,
            '/p@th',
            'query=1',
            'fr%40gment%20with%20spaces'
        );

        $this->assertSame('http://user%20one:p%40ss@localhost:8080/p@th?query=1#fr%40gment%20with%20spaces', (string) $params);
    }
}
