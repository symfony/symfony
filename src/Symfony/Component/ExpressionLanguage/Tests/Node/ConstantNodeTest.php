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
            ['false', new ConstantNode(false)],
            ['true', new ConstantNode(true)],
            ['null', new ConstantNode(null)],
            ['3', new ConstantNode(3)],
            ['3.3', new ConstantNode(3.3)],
            ['"foo"', new ConstantNode('foo')],
            ['[0 => 1, "b" => "a"]', new ConstantNode([1, 'b' => 'a'])],
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
