<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\ExpressionLanguage\Tests\Node;

use Symphony\Component\ExpressionLanguage\Node\NameNode;

class NameNodeTest extends AbstractNodeTest
{
    public function getEvaluateData()
    {
        return array(
            array('bar', new NameNode('foo'), array('foo' => 'bar')),
        );
    }

    public function getCompileData()
    {
        return array(
            array('$foo', new NameNode('foo')),
        );
    }

    public function getDumpData()
    {
        return array(
            array('foo', new NameNode('foo')),
        );
    }
}
