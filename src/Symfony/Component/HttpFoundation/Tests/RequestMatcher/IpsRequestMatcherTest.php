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
use Symfony\Component\HttpFoundation\RequestMatcher\IpsRequestMatcher;

class IpsRequestMatcherTest extends TestCase
{
    /**
     * @dataProvider getData
     */
    public function test($ips, bool $expected)
    {
        $matcher = new IpsRequestMatcher($ips);
        $request = Request::create('', 'GET', [], [], [], ['REMOTE_ADDR' => '127.0.0.1']);
        $this->assertSame($expected, $matcher->matches($request));
    }

    public static function getData()
    {
        return [
            [[], true],
            ['127.0.0.1', true],
            ['192.168.0.1', false],
            ['127.0.0.1', true],
            ['127.0.0.1, ::1', true],
            ['192.168.0.1, ::1', false],
            [['127.0.0.1', '::1'], true],
            [['192.168.0.1', '::1'], false],
            [['1.1.1.1', '2.2.2.2', '127.0.0.1, ::1'], true],
            [['1.1.1.1', '2.2.2.2', '192.168.0.1, ::1'], false],
            [['192.168.1.0/24'], false],
            [['127.0.0.1/32'], true],
        ];
    }
}
