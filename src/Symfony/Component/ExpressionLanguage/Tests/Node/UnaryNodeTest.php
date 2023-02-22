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
use Symfony\Component\ExpressionLanguage\Node\UnaryNode;

class UnaryNodeTest extends AbstractNodeTestCase
{
    public static function getEvaluateData(): array
    {
        return [
            [-1, new UnaryNode('-', new ConstantNode(1))],
            [3, new UnaryNode('+', new ConstantNode(3))],
            [false, new UnaryNode('!', new ConstantNode(true))],
            [false, new UnaryNode('not', new ConstantNode(true))],
        ];
    }

    public static function getCompileData(): array
    {
        return [
            ['(-1)', new UnaryNode('-', new ConstantNode(1))],
            ['(+3)', new UnaryNode('+', new ConstantNode(3))],
            ['(!true)', new UnaryNode('!', new ConstantNode(true))],
            ['(!true)', new UnaryNode('not', new ConstantNode(true))],
        ];
    }

    public static function getDumpData(): array
    {
        return [
            ['(- 1)', new UnaryNode('-', new ConstantNode(1))],
            ['(+ 3)', new UnaryNode('+', new ConstantNode(3))],
            ['(! true)', new UnaryNode('!', new ConstantNode(true))],
            ['(not true)', new UnaryNode('not', new ConstantNode(true))],
        ];
    }
}
