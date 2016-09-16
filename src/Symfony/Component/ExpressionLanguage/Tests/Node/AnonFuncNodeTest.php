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

use Symfony\Component\ExpressionLanguage\Node\AnonFuncNode;
use Symfony\Component\ExpressionLanguage\Node\BinaryNode;
use Symfony\Component\ExpressionLanguage\Node\NameNode;
use Symfony\Component\ExpressionLanguage\Node\ConstantNode;

class AnonFuncNodeTest extends AbstractNodeTest
{
    /**
     * @dataProvider getEvaluateData
     */
    public function testEvaluate($expectedResult, $node, $variables = array(), $functions = array())
    {
        $callback = $node->evaluate($functions, $variables);
        $this->assertTrue(is_callable($callback));
        
        $actualResult = call_user_func_array($callback, $variables);
        $this->assertSame($expectedResult, $actualResult);
    }
    
    public function getEvaluateData()
    {
        return array(
            'parameterless call, null result' => array(
                null,
                new AnonFuncNode(
                    array(),
                    null
                )
            ),
            'one parameter, returned' => array(
                123,
                new AnonFuncNode(
                    array(new NameNode('foo')),
                    new NameNode('foo')
                ),
                array('foovalue' => 123)
            ),
            'two parameters, multiplied and result returned' => array(
                246,
                new AnonFuncNode(
                    array(new NameNode('foo'), new NameNode('bar')),
                    new BinaryNode('*', new NameNode('foo'), new NameNode('bar'))
                ),
                array('foovalue' => 123, 'barvalue' => 2)
            ),
            'one unused parameter, returns literal' => array(
                890,
                new AnonFuncNode(
                    array(new NameNode('foo')),
                    new ConstantNode(890)
                ),
                array('foovalue' => 123)
            ),
        );
    }

    public function getCompileData()
    {
        return array(
            array(
                'function () { return null; }',
                new AnonFuncNode(
                    array(),
                    null
                )
            ),
            array(
                'function ($foo) { return $foo; }',
                new AnonFuncNode(
                    array(new NameNode('foo')),
                    new NameNode('foo')
                )
            ),
            array(
                'function ($foo, $bar) { return ($foo * $bar); }',
                new AnonFuncNode(
                    array(new NameNode('foo'), new NameNode('bar')),
                    new BinaryNode('*', new NameNode('foo'), new NameNode('bar'))
                )
            ),
        );
    }

    public function getDumpData()
    {
        return array(
            array(
                '() -> {}',
                new AnonFuncNode(
                    array(),
                    null
                )
            ),
            array(
                '(foo) -> {foo}',
                new AnonFuncNode(
                    array(new NameNode('foo')),
                    new NameNode('foo')
                )
            ),
            array(
                '(foo) -> {"bar"}',
                new AnonFuncNode(
                    array(new NameNode('foo')),
                    new ConstantNode('bar')
                )
            ),
            array(
                '(foo, bar) -> {(foo * bar)}',
                new AnonFuncNode(
                    array(new NameNode('foo'), new NameNode('bar')),
                    new BinaryNode('*', new NameNode('foo'), new NameNode('bar'))
                )
            ),
        );
    }
}
