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

use Symfony\Component\ExpressionLanguage\Node\BetweenNode;
use Symfony\Component\ExpressionLanguage\Node\ConstantNode;

class BetweenNodeTest extends AbstractNodeTest
{
    public function getEvaluateData()
    {
        return array(
            array(false, new BetweenNode(new ConstantNode(10), new ConstantNode(20), new ConstantNode(30))),
            array(true, new BetweenNode(new ConstantNode(25), new ConstantNode(20), new ConstantNode(30))),
        );
    }

    public function getCompileData()
    {
        return array(
            array('(10 >= 20 && 10 <= 30)', new BetweenNode(new ConstantNode(10), new ConstantNode(20), new ConstantNode(30))),
            array('(25 >= 20 && 25 <= 30)', new BetweenNode(new ConstantNode(25), new ConstantNode(20), new ConstantNode(30))),
        );
    }
}
