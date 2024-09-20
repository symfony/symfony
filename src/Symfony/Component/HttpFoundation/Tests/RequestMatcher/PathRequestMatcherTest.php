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
use Symfony\Component\HttpFoundation\RequestMatcher\PathRequestMatcher;

class PathRequestMatcherTest extends TestCase
{
    /**
     * @dataProvider getData
     */
    public function test(string $regexp, bool $expected)
    {
        $matcher = new PathRequestMatcher($regexp);
        $request = Request::create('/admin/foo');
        $this->assertSame($expected, $matcher->matches($request));
    }

    public static function getData()
    {
        return [
            ['/admin/.*', true],
            ['/admin', true],
            ['^/admin/.*$', true],
            ['/blog/.*', false],
        ];
    }

    public function testWithLocaleIsNotSupported()
    {
        $matcher = new PathRequestMatcher('^/{_locale}/login$');
        $request = Request::create('/en/login');
        $request->setLocale('en');
        $this->assertFalse($matcher->matches($request));
    }

    public function testWithEncodedCharacters()
    {
        $matcher = new PathRequestMatcher('^/admin/fo o*$');
        $request = Request::create('/admin/fo%20o');
        $this->assertTrue($matcher->matches($request));
    }
}
