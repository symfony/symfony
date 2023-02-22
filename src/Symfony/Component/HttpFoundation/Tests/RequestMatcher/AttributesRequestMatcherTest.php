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
use Symfony\Component\HttpFoundation\RequestMatcher\AttributesRequestMatcher;
use Symfony\Component\HttpFoundation\Response;

class AttributesRequestMatcherTest extends TestCase
{
    /**
     * @dataProvider getData
     */
    public function test(string $key, string $regexp, bool $expected)
    {
        $matcher = new AttributesRequestMatcher([$key => $regexp]);
        $request = Request::create('/admin/foo');
        $request->attributes->set('foo', 'foo_bar');
        $request->attributes->set('_controller', fn () => new Response('foo'));
        $this->assertSame($expected, $matcher->matches($request));
    }

    public static function getData(): array
    {
        return [
            ['foo', 'foo_.*', true],
            ['foo', 'foo', true],
            ['foo', '^foo_bar$', true],
            ['foo', 'babar', false],
            'with-closure' => ['_controller', 'babar', false],
        ];
    }
}
