<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Tests\RequestMatcher;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestMatcher\PortRequestMatcher;

class PortRequestMatcherTest extends TestCase
{
    /**
     * @dataProvider getData
     */
    public function test(int $port, bool $expected)
    {
        $matcher = new PortRequestMatcher($port);
        $request = Request::create('', 'get', [], [], [], ['HTTP_HOST' => null, 'SERVER_PORT' => 8000]);
        $this->assertSame($expected, $matcher->matches($request));
    }

    public static function getData()
    {
        return [
            [8000, true],
            [9000, false],
        ];
    }
}
