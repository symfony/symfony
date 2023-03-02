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
use Symfony\Component\HttpFoundation\RequestMatcher\IsJsonRequestMatcher;

class IsJsonRequestMatcherTest extends TestCase
{
    /**
     * @dataProvider getData
     */
    public function test($json, bool $isValid)
    {
        $matcher = new IsJsonRequestMatcher();
        $request = Request::create('', 'GET', [], [], [], [], $json);
        $this->assertSame($isValid, $matcher->matches($request));
    }

    public static function getData()
    {
        return [
            ['not json', false],
            ['"json_string"', true],
            ['11', true],
            ['["json", "array"]', true],
        ];
    }
}
