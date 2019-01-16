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

use Symfony\Component\ExpressionLanguage\Node\ArrayNode;
use Symfony\Component\ExpressionLanguage\Node\BinaryNode;
use Symfony\Component\ExpressionLanguage\Node\ConstantNode;

class BinaryNodeTest extends AbstractNodeTest
{
    public function getEvaluateData()
    {
        $array = new ArrayNode();
        $array->addElement(new ConstantNode('a'));
        $array->addElement(new ConstantNode('b'));

        return [
            [true, new BinaryNode('or', new ConstantNode(true), new ConstantNode(false))],
            [true, new BinaryNode('||', new ConstantNode(true), new ConstantNode(false))],
            [false, new BinaryNode('and', new ConstantNode(true), new ConstantNode(false))],
            [false, new BinaryNode('&&', new ConstantNode(true), new ConstantNode(false))],

            [0, new BinaryNode('&', new ConstantNode(2), new ConstantNode(4))],
            [6, new BinaryNode('|', new ConstantNode(2), new ConstantNode(4))],
            [6, new BinaryNode('^', new ConstantNode(2), new ConstantNode(4))],

            [true, new BinaryNode('<', new ConstantNode(1), new ConstantNode(2))],
            [true, new BinaryNode('<=', new ConstantNode(1), new ConstantNode(2))],
            [true, new BinaryNode('<=', new ConstantNode(1), new ConstantNode(1))],

            [false, new BinaryNode('>', new ConstantNode(1), new ConstantNode(2))],
            [false, new BinaryNode('>=', new ConstantNode(1), new ConstantNode(2))],
            [true, new BinaryNode('>=', new ConstantNode(1), new ConstantNode(1))],

            [true, new BinaryNode('===', new ConstantNode(true), new ConstantNode(true))],
            [false, new BinaryNode('!==', new ConstantNode(true), new ConstantNode(true))],

            [false, new BinaryNode('==', new ConstantNode(2), new ConstantNode(1))],
            [true, new BinaryNode('!=', new ConstantNode(2), new ConstantNode(1))],

            [-1, new BinaryNode('-', new ConstantNode(1), new ConstantNode(2))],
            [3, new BinaryNode('+', new ConstantNode(1), new ConstantNode(2))],
            [4, new BinaryNode('*', new ConstantNode(2), new ConstantNode(2))],
            [1, new BinaryNode('/', new ConstantNode(2), new ConstantNode(2))],
            [1, new BinaryNode('%', new ConstantNode(5), new ConstantNode(2))],
            [25, new BinaryNode('**', new ConstantNode(5), new ConstantNode(2))],
            ['ab', new BinaryNode('~', new ConstantNode('a'), new ConstantNode('b'))],

            [true, new BinaryNode('in', new ConstantNode('a'), $array)],
            [false, new BinaryNode('in', new ConstantNode('c'), $array)],
            [true, new BinaryNode('not in', new ConstantNode('c'), $array)],
            [false, new BinaryNode('not in', new ConstantNode('a'), $array)],

            [[1, 2, 3], new BinaryNode('..', new ConstantNode(1), new ConstantNode(3))],

            [1, new BinaryNode('matches', new ConstantNode('abc'), new ConstantNode('/^[a-z]+$/'))],
        ];
    }

    public function getCompileData()
    {
        $array = new ArrayNode();
        $array->addElement(new ConstantNode('a'));
        $array->addElement(new ConstantNode('b'));

        return [
            ['(true || false)', new BinaryNode('or', new ConstantNode(true), new ConstantNode(false))],
            ['(true || false)', new BinaryNode('||', new ConstantNode(true), new ConstantNode(false))],
            ['(true && false)', new BinaryNode('and', new ConstantNode(true), new ConstantNode(false))],
            ['(true && false)', new BinaryNode('&&', new ConstantNode(true), new ConstantNode(false))],

            ['(2 & 4)', new BinaryNode('&', new ConstantNode(2), new ConstantNode(4))],
            ['(2 | 4)', new BinaryNode('|', new ConstantNode(2), new ConstantNode(4))],
            ['(2 ^ 4)', new BinaryNode('^', new ConstantNode(2), new ConstantNode(4))],

            ['(1 < 2)', new BinaryNode('<', new ConstantNode(1), new ConstantNode(2))],
            ['(1 <= 2)', new BinaryNode('<=', new ConstantNode(1), new ConstantNode(2))],
            ['(1 <= 1)', new BinaryNode('<=', new ConstantNode(1), new ConstantNode(1))],

            ['(1 > 2)', new BinaryNode('>', new ConstantNode(1), new ConstantNode(2))],
            ['(1 >= 2)', new BinaryNode('>=', new ConstantNode(1), new ConstantNode(2))],
            ['(1 >= 1)', new BinaryNode('>=', new ConstantNode(1), new ConstantNode(1))],

            ['(true === true)', new BinaryNode('===', new ConstantNode(true), new ConstantNode(true))],
            ['(true !== true)', new BinaryNode('!==', new ConstantNode(true), new ConstantNode(true))],

            ['(2 == 1)', new BinaryNode('==', new ConstantNode(2), new ConstantNode(1))],
            ['(2 != 1)', new BinaryNode('!=', new ConstantNode(2), new ConstantNode(1))],

            ['(1 - 2)', new BinaryNode('-', new ConstantNode(1), new ConstantNode(2))],
            ['(1 + 2)', new BinaryNode('+', new ConstantNode(1), new ConstantNode(2))],
            ['(2 * 2)', new BinaryNode('*', new ConstantNode(2), new ConstantNode(2))],
            ['(2 / 2)', new BinaryNode('/', new ConstantNode(2), new ConstantNode(2))],
            ['(5 % 2)', new BinaryNode('%', new ConstantNode(5), new ConstantNode(2))],
            ['pow(5, 2)', new BinaryNode('**', new ConstantNode(5), new ConstantNode(2))],
            ['("a" . "b")', new BinaryNode('~', new ConstantNode('a'), new ConstantNode('b'))],

            ['in_array("a", [0 => "a", 1 => "b"])', new BinaryNode('in', new ConstantNode('a'), $array)],
            ['in_array("c", [0 => "a", 1 => "b"])', new BinaryNode('in', new ConstantNode('c'), $array)],
            ['!in_array("c", [0 => "a", 1 => "b"])', new BinaryNode('not in', new ConstantNode('c'), $array)],
            ['!in_array("a", [0 => "a", 1 => "b"])', new BinaryNode('not in', new ConstantNode('a'), $array)],

            ['range(1, 3)', new BinaryNode('..', new ConstantNode(1), new ConstantNode(3))],

            ['preg_match("/^[a-z]+/i\$/", "abc")', new BinaryNode('matches', new ConstantNode('abc'), new ConstantNode('/^[a-z]+/i$/'))],
        ];
    }

    public function getDumpData()
    {
        $array = new ArrayNode();
        $array->addElement(new ConstantNode('a'));
        $array->addElement(new ConstantNode('b'));

        return [
            ['(true or false)', new BinaryNode('or', new ConstantNode(true), new ConstantNode(false))],
            ['(true || false)', new BinaryNode('||', new ConstantNode(true), new ConstantNode(false))],
            ['(true and false)', new BinaryNode('and', new ConstantNode(true), new ConstantNode(false))],
            ['(true && false)', new BinaryNode('&&', new ConstantNode(true), new ConstantNode(false))],

            ['(2 & 4)', new BinaryNode('&', new ConstantNode(2), new ConstantNode(4))],
            ['(2 | 4)', new BinaryNode('|', new ConstantNode(2), new ConstantNode(4))],
            ['(2 ^ 4)', new BinaryNode('^', new ConstantNode(2), new ConstantNode(4))],

            ['(1 < 2)', new BinaryNode('<', new ConstantNode(1), new ConstantNode(2))],
            ['(1 <= 2)', new BinaryNode('<=', new ConstantNode(1), new ConstantNode(2))],
            ['(1 <= 1)', new BinaryNode('<=', new ConstantNode(1), new ConstantNode(1))],

            ['(1 > 2)', new BinaryNode('>', new ConstantNode(1), new ConstantNode(2))],
            ['(1 >= 2)', new BinaryNode('>=', new ConstantNode(1), new ConstantNode(2))],
            ['(1 >= 1)', new BinaryNode('>=', new ConstantNode(1), new ConstantNode(1))],

            ['(true === true)', new BinaryNode('===', new ConstantNode(true), new ConstantNode(true))],
            ['(true !== true)', new BinaryNode('!==', new ConstantNode(true), new ConstantNode(true))],

            ['(2 == 1)', new BinaryNode('==', new ConstantNode(2), new ConstantNode(1))],
            ['(2 != 1)', new BinaryNode('!=', new ConstantNode(2), new ConstantNode(1))],

            ['(1 - 2)', new BinaryNode('-', new ConstantNode(1), new ConstantNode(2))],
            ['(1 + 2)', new BinaryNode('+', new ConstantNode(1), new ConstantNode(2))],
            ['(2 * 2)', new BinaryNode('*', new ConstantNode(2), new ConstantNode(2))],
            ['(2 / 2)', new BinaryNode('/', new ConstantNode(2), new ConstantNode(2))],
            ['(5 % 2)', new BinaryNode('%', new ConstantNode(5), new ConstantNode(2))],
            ['(5 ** 2)', new BinaryNode('**', new ConstantNode(5), new ConstantNode(2))],
            ['("a" ~ "b")', new BinaryNode('~', new ConstantNode('a'), new ConstantNode('b'))],

            ['("a" in ["a", "b"])', new BinaryNode('in', new ConstantNode('a'), $array)],
            ['("c" in ["a", "b"])', new BinaryNode('in', new ConstantNode('c'), $array)],
            ['("c" not in ["a", "b"])', new BinaryNode('not in', new ConstantNode('c'), $array)],
            ['("a" not in ["a", "b"])', new BinaryNode('not in', new ConstantNode('a'), $array)],

            ['(1 .. 3)', new BinaryNode('..', new ConstantNode(1), new ConstantNode(3))],

            ['("abc" matches "/^[a-z]+/i$/")', new BinaryNode('matches', new ConstantNode('abc'), new ConstantNode('/^[a-z]+/i$/'))],
        ];
    }
}
