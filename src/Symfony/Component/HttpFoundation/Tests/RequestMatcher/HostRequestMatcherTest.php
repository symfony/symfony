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
use Symfony\Component\HttpFoundation\RequestMatcher\HostRequestMatcher;

class HostRequestMatcherTest extends TestCase
{
    /**
     * @dataProvider getData
     */
    public function test($pattern, $isMatch)
    {
        $matcher = new HostRequestMatcher($pattern);
        $request = Request::create('', 'get', [], [], [], ['HTTP_HOST' => 'foo.example.com']);
        $this->assertSame($isMatch, $matcher->matches($request));
    }

    public static function getData()
    {
        return [
            ['.*\.example\.com', true],
            ['\.example\.com$', true],
            ['^.*\.example\.com$', true],
            ['.*\.sensio\.com', false],
            ['.*\.example\.COM', true],
            ['\.example\.COM$', true],
            ['^.*\.example\.COM$', true],
            ['.*\.sensio\.COM', false],
        ];
    }
}
