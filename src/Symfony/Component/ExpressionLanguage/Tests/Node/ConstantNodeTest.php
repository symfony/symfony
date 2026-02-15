<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ExpressionLanguage\Tests\Node;

use Symfony\Component\ExpressionLanguage\Node\ConstantNode;

class ConstantNodeTest extends AbstractNodeTestCase
{
    public static function getEvaluateData(): array
    {
        return [
            [false, new ConstantNode(false)],
            [true, new ConstantNode(true)],
            [null, new ConstantNode(null)],
            [3, new ConstantNode(3)],
            [3.3, new ConstantNode(3.3)],
            ['foo', new ConstantNode('foo')],
            [[1, 'b' => 'a'], new ConstantNode([1, 'b' => 'a'])],
        ];
    }

    public static function getCompileData(): array
    {
        return [
            // Booleans
            ['false', new ConstantNode(false)],
            ['true', new ConstantNode(true)],

            // Null
            ['null', new ConstantNode(null)],

            // Integers
            ['3', new ConstantNode(3)],
            ['-10', new ConstantNode(-10)],
            ['0', new ConstantNode(0)],

            // Floats
            ['3.3', new ConstantNode(3.3)],
            ['42.0', new ConstantNode(42.0)],
            ['-1.23', new ConstantNode(-1.23)],
            ['0.1', new ConstantNode(0.1)],
            ['1.0', new ConstantNode(1.0)],
            ['1.0E-6', new ConstantNode(1.0e-6)],
            ['1.23456789E+20', new ConstantNode(1.23456789e+20)],
            ['3.3', new ConstantNode(3.2999999999999998)],
            ['0.30000000000000004', new ConstantNode(0.1 + 0.2)],
            ['INF', new ConstantNode(\INF)],
            ['-INF', new ConstantNode(-\INF)],
            ['NAN', new ConstantNode(\NAN)],

            // Strings
            ['"foo"', new ConstantNode('foo')],
            ['""', new ConstantNode('')],
            ['"a\\"b"', new ConstantNode('a"b')],

            // Arrays
            ['[0 => 1, "b" => "a"]', new ConstantNode([1, 'b' => 'a'])],
            ['[]', new ConstantNode([])],
        ];
    }

    public static function getDumpData(): array
    {
        return [
            ['false', new ConstantNode(false)],
            ['true', new ConstantNode(true)],
            ['null', new ConstantNode(null)],
            ['3', new ConstantNode(3)],
            ['3.3', new ConstantNode(3.3)],
            ['"foo"', new ConstantNode('foo')],
            ['foo', new ConstantNode('foo', true)],
            ['{0: 1, "b": "a", 1: true}', new ConstantNode([1, 'b' => 'a', true])],
            ['{"a\\"b": "c", "a\\\\b": "d"}', new ConstantNode(['a"b' => 'c', 'a\\b' => 'd'])],
            ['["c", "d"]', new ConstantNode(['c', 'd'])],
            ['{"a": ["b"]}', new ConstantNode(['a' => ['b']])],
        ];
    }
}
