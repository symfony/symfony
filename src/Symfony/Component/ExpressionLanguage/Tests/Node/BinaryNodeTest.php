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

use Symfony\Component\ExpressionLanguage\Node\BinaryNode;
use Symfony\Component\ExpressionLanguage\Node\ArrayNode;
use Symfony\Component\ExpressionLanguage\Node\ConstantNode;

class BinaryNodeTest extends AbstractNodeTest
{
    public function getEvaluateData()
    {
        $array = new ArrayNode();
        $array->addElement(new ConstantNode('a'));
        $array->addElement(new ConstantNode('b'));

        return array(
            array(true, new BinaryNode('or', new ConstantNode(true), new ConstantNode(false))),
            array(true, new BinaryNode('||', new ConstantNode(true), new ConstantNode(false))),
            array(false, new BinaryNode('and', new ConstantNode(true), new ConstantNode(false))),
            array(false, new BinaryNode('&&', new ConstantNode(true), new ConstantNode(false))),

            array(0, new BinaryNode('&', new ConstantNode(2), new ConstantNode(4))),
            array(6, new BinaryNode('|', new ConstantNode(2), new ConstantNode(4))),
            array(6, new BinaryNode('^', new ConstantNode(2), new ConstantNode(4))),

            array(true, new BinaryNode('<', new ConstantNode(1), new ConstantNode(2))),
            array(true, new BinaryNode('<=', new ConstantNode(1), new ConstantNode(2))),
            array(true, new BinaryNode('<=', new ConstantNode(1), new ConstantNode(1))),

            array(false, new BinaryNode('>', new ConstantNode(1), new ConstantNode(2))),
            array(false, new BinaryNode('>=', new ConstantNode(1), new ConstantNode(2))),
            array(true, new BinaryNode('>=', new ConstantNode(1), new ConstantNode(1))),

            array(true, new BinaryNode('===', new ConstantNode(true), new ConstantNode(true))),
            array(false, new BinaryNode('!==', new ConstantNode(true), new ConstantNode(true))),

            array(false, new BinaryNode('==', new ConstantNode(2), new ConstantNode(1))),
            array(true, new BinaryNode('!=', new ConstantNode(2), new ConstantNode(1))),

            array(-1, new BinaryNode('-', new ConstantNode(1), new ConstantNode(2))),
            array(3, new BinaryNode('+', new ConstantNode(1), new ConstantNode(2))),
            array(4, new BinaryNode('*', new ConstantNode(2), new ConstantNode(2))),
            array(1, new BinaryNode('/', new ConstantNode(2), new ConstantNode(2))),
            array(1, new BinaryNode('%', new ConstantNode(5), new ConstantNode(2))),
            array(25, new BinaryNode('**', new ConstantNode(5), new ConstantNode(2))),
            array('ab', new BinaryNode('~', new ConstantNode('a'), new ConstantNode('b'))),

            array(true, new BinaryNode('in', new ConstantNode('a'), $array)),
            array(false, new BinaryNode('in', new ConstantNode('c'), $array)),
            array(true, new BinaryNode('not in', new ConstantNode('c'), $array)),
            array(false, new BinaryNode('not in', new ConstantNode('a'), $array)),

            array(array(1, 2, 3), new BinaryNode('..', new ConstantNode(1), new ConstantNode(3))),

            array(1, new BinaryNode('matches', new ConstantNode('abc'), new ConstantNode('/^[a-z]+$/'))),
        );
    }

    public function getCompileData()
    {
        $array = new ArrayNode();
        $array->addElement(new ConstantNode('a'));
        $array->addElement(new ConstantNode('b'));

        return array(
            array('(true || false)', new BinaryNode('or', new ConstantNode(true), new ConstantNode(false))),
            array('(true || false)', new BinaryNode('||', new ConstantNode(true), new ConstantNode(false))),
            array('(true && false)', new BinaryNode('and', new ConstantNode(true), new ConstantNode(false))),
            array('(true && false)', new BinaryNode('&&', new ConstantNode(true), new ConstantNode(false))),

            array('(2 & 4)', new BinaryNode('&', new ConstantNode(2), new ConstantNode(4))),
            array('(2 | 4)', new BinaryNode('|', new ConstantNode(2), new ConstantNode(4))),
            array('(2 ^ 4)', new BinaryNode('^', new ConstantNode(2), new ConstantNode(4))),

            array('(1 < 2)', new BinaryNode('<', new ConstantNode(1), new ConstantNode(2))),
            array('(1 <= 2)', new BinaryNode('<=', new ConstantNode(1), new ConstantNode(2))),
            array('(1 <= 1)', new BinaryNode('<=', new ConstantNode(1), new ConstantNode(1))),

            array('(1 > 2)', new BinaryNode('>', new ConstantNode(1), new ConstantNode(2))),
            array('(1 >= 2)', new BinaryNode('>=', new ConstantNode(1), new ConstantNode(2))),
            array('(1 >= 1)', new BinaryNode('>=', new ConstantNode(1), new ConstantNode(1))),

            array('(true === true)', new BinaryNode('===', new ConstantNode(true), new ConstantNode(true))),
            array('(true !== true)', new BinaryNode('!==', new ConstantNode(true), new ConstantNode(true))),

            array('(2 == 1)', new BinaryNode('==', new ConstantNode(2), new ConstantNode(1))),
            array('(2 != 1)', new BinaryNode('!=', new ConstantNode(2), new ConstantNode(1))),

            array('(1 - 2)', new BinaryNode('-', new ConstantNode(1), new ConstantNode(2))),
            array('(1 + 2)', new BinaryNode('+', new ConstantNode(1), new ConstantNode(2))),
            array('(2 * 2)', new BinaryNode('*', new ConstantNode(2), new ConstantNode(2))),
            array('(2 / 2)', new BinaryNode('/', new ConstantNode(2), new ConstantNode(2))),
            array('(5 % 2)', new BinaryNode('%', new ConstantNode(5), new ConstantNode(2))),
            array('pow(5, 2)', new BinaryNode('**', new ConstantNode(5), new ConstantNode(2))),
            array('("a" . "b")', new BinaryNode('~', new ConstantNode('a'), new ConstantNode('b'))),

            array('in_array("a", array(0 => "a", 1 => "b"))', new BinaryNode('in', new ConstantNode('a'), $array)),
            array('in_array("c", array(0 => "a", 1 => "b"))', new BinaryNode('in', new ConstantNode('c'), $array)),
            array('!in_array("c", array(0 => "a", 1 => "b"))', new BinaryNode('not in', new ConstantNode('c'), $array)),
            array('!in_array("a", array(0 => "a", 1 => "b"))', new BinaryNode('not in', new ConstantNode('a'), $array)),

            array('range(1, 3)', new BinaryNode('..', new ConstantNode(1), new ConstantNode(3))),

            array('preg_match("/^[a-z]+/i\$/", "abc")', new BinaryNode('matches', new ConstantNode('abc'), new ConstantNode('/^[a-z]+/i$/'))),
        );
    }
}
