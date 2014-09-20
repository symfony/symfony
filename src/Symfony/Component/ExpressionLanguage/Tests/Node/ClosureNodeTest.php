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

use Symfony\Component\ExpressionLanguage\Node\ClosureNode;
use Symfony\Component\ExpressionLanguage\Node\ConstantNode;
use Symfony\Component\ExpressionLanguage\Node\Node;

class ClosureNodeTest extends AbstractNodeTest
{
    public function getEvaluateData()
    {
        $foo = function ($a, $b) {
            return $a + $b;
        };

        return array(
            array(2, new ClosureNode('foo', new Node(array(new ConstantNode(1), new ConstantNode(1)))), array('foo' => $foo)),
            array(3, new ClosureNode('foo', new Node(array(new ClosureNode('foo', new Node(array(new ConstantNode(1), new ConstantNode(1)))), new ConstantNode(1)))), array('foo' => $foo)),
        );
    }

    public function getCompileData()
    {
        return array(
            array('$foo()', new ClosureNode('foo', new Node(array()))),
            array('$foo(1, 1)', new ClosureNode('foo', new Node(array(new ConstantNode(1), new ConstantNode(1))))),
            array('$foo($bar())', new ClosureNode('foo', new Node(array(new ClosureNode('bar', new Node(array())))))),
        );
    }
}
