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

use Symfony\Component\ExpressionLanguage\Node\ConditionalNode;
use Symfony\Component\ExpressionLanguage\Node\ConstantNode;

class ConditionalNodeTest extends AbstractNodeTest
{
    public function getEvaluateData()
    {
        return [
            [1, new ConditionalNode(new ConstantNode(true), new ConstantNode(1), new ConstantNode(2))],
            [2, new ConditionalNode(new ConstantNode(false), new ConstantNode(1), new ConstantNode(2))],
        ];
    }

    public function getCompileData()
    {
        return [
            ['((true) ? (1) : (2))', new ConditionalNode(new ConstantNode(true), new ConstantNode(1), new ConstantNode(2))],
            ['((false) ? (1) : (2))', new ConditionalNode(new ConstantNode(false), new ConstantNode(1), new ConstantNode(2))],
        ];
    }

    public function getDumpData()
    {
        return [
            ['(true ? 1 : 2)', new ConditionalNode(new ConstantNode(true), new ConstantNode(1), new ConstantNode(2))],
            ['(false ? 1 : 2)', new ConditionalNode(new ConstantNode(false), new ConstantNode(1), new ConstantNode(2))],
        ];
    }
}
