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
use Symfony\Component\ExpressionLanguage\Node\FunctionNode;
use Symfony\Component\ExpressionLanguage\Node\Node;

class FunctionNodeTest extends AbstractNodeTest
{
    public function getEvaluateData()
    {
        return [
            ['bar', new FunctionNode('foo', new Node([new ConstantNode('bar')])), [], ['foo' => $this->getCallables()]],
        ];
    }

    public function getCompileData()
    {
        return [
            ['foo("bar")', new FunctionNode('foo', new Node([new ConstantNode('bar')])), ['foo' => $this->getCallables()]],
        ];
    }

    public function getDumpData()
    {
        return [
            ['foo("bar")', new FunctionNode('foo', new Node([new ConstantNode('bar')])), ['foo' => $this->getCallables()]],
        ];
    }

    protected function getCallables()
    {
        return [
            'compiler' => function ($arg) {
                return sprintf('foo(%s)', $arg);
            },
            'evaluator' => function ($variables, $arg) {
                return $arg;
            },
        ];
    }
}
