<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Uri\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Uri\FragmentTextDirective;

/**
 * @covers \Symfony\Component\Uri\FragmentTextDirective
 */
class FragmentTextDirectiveTest extends TestCase
{
    /**
     * @dataProvider provideValidFragmentTextDirectives
     */
    public function testToString(FragmentTextDirective $fragmentTextDirective, string $expected)
    {
        $this->assertSame($expected, (string) $fragmentTextDirective);
    }

    public function testToStringEncodesSpecialCharacters()
    {
        $fragmentTextDirective = new FragmentTextDirective('st&rt', 'e,nd', 'prefix-', '-&suffix');

        $this->assertSame(':~:text=prefix%2D-,st%26rt,e%2Cnd,-%2D%26suffix', (string) $fragmentTextDirective);
    }

    public static function provideValidFragmentTextDirectives(): iterable
    {
        yield [new FragmentTextDirective('start'), ':~:text=start'];
        yield [new FragmentTextDirective('start', 'end'), ':~:text=start,end'];
        yield [new FragmentTextDirective('start', 'end', 'prefix'), ':~:text=prefix-,start,end'];
        yield [new FragmentTextDirective('start', 'end', 'prefix', 'suffix'), ':~:text=prefix-,start,end,-suffix'];
        yield [new FragmentTextDirective('start', prefix: 'prefix', suffix: 'suffix'), ':~:text=prefix-,start,-suffix'];
        yield [new FragmentTextDirective('start', suffix: 'suffix'), ':~:text=start,-suffix'];
        yield [new FragmentTextDirective('start', prefix: 'prefix'), ':~:text=prefix-,start'];
    }
}
