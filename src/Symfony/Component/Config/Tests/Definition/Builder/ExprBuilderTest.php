<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Tests\Definition\Builder;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;


class ExprBuilderTest extends \PHPUnit_Framework_TestCase
{

    public function testAlwaysExpression()
    {
        $test = $this->initScenario()
            ->always($this->returnClosure('new_value'))
        ->end();

        $this->assertFinalizedValueIs('new_value', $test);
    }

    public function testIfTrueExpression()
    {
        $test = $this->initScenario()
            ->ifTrue()
            ->then($this->returnClosure('new_value'))
        ->end();
        $this->assertFinalizedValueIs('new_value', $test, array('key'=>true));

        $test = $this->initScenario()
            ->ifTrue( function($v){ return true; })
            ->then($this->returnClosure('new_value'))
        ->end();
        $this->assertFinalizedValueIs('new_value', $test);

        $test = $this->initScenario()
            ->ifTrue( function($v){ return false; })
            ->then($this->returnClosure('new_value'))
        ->end();
        $this->assertFinalizedValueIs('value',$test);
    }

    public function testIfStringExpression()
    {
        $test = $this->initScenario()
            ->ifString()
            ->then($this->returnClosure('new_value'))
        ->end();
        $this->assertFinalizedValueIs('new_value', $test);

        $test = $this->initScenario()
            ->ifString()
            ->then($this->returnClosure('new_value'))
        ->end();
        $this->assertFinalizedValueIs(45, $test, array('key'=>45));
        
    }

    public function testIfNullExpression()
    {
        $test = $this->initScenario()
            ->ifNull()
            ->then($this->returnClosure('new_value'))
        ->end();
        $this->assertFinalizedValueIs('new_value', $test, array('key'=>null));

        $test = $this->initScenario()
            ->ifNull()
            ->then($this->returnClosure('new_value'))
        ->end();
        $this->assertFinalizedValueIs('value', $test);
    }

    public function testIfArrayExpression()
    {
        $test = $this->initScenario()
            ->ifArray()
            ->then($this->returnClosure('new_value'))
        ->end();
        $this->assertFinalizedValueIs('new_value', $test, array('key'=>array()));

        $test = $this->initScenario()
            ->ifArray()
            ->then($this->returnClosure('new_value'))
        ->end();
        $this->assertFinalizedValueIs('value', $test);
    }

    public function testIfInArrayExpression()
    {
        $test = $this->initScenario()
            ->ifInArray(array('foo', 'bar', 'value'))
            ->then($this->returnClosure('new_value'))
        ->end();
        $this->assertFinalizedValueIs('new_value', $test);

        $test = $this->initScenario()
            ->ifInArray(array('foo', 'bar'))
            ->then($this->returnClosure('new_value'))
        ->end();
        $this->assertFinalizedValueIs('value', $test);
    }

    public function testIfNotInArrayExpression()
    {
        $test = $this->initScenario()
            ->ifNotInArray(array('foo', 'bar'))
            ->then($this->returnClosure('new_value'))
        ->end();
        $this->assertFinalizedValueIs('new_value', $test);

        $test = $this->initScenario()
            ->ifNotInArray(array('foo', 'bar', 'value_from_config' ))
            ->then($this->returnClosure('new_value'))
        ->end();
        $this->assertFinalizedValueIs('new_value', $test);
    }

    public function testThenEmptyArrayExpression()
    {
        $test = $this->initScenario()
            ->ifString()
            ->thenEmptyArray()
        ->end();
        $this->assertFinalizedValueIs(array(), $test);
    }

    /**
     * @expectedException Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     */
    public function testThenInvalid()
    {
        $test = $this->initScenario()
            ->ifString()
            ->thenInvalid('Invalid value')
        ->end();
        $this->finalizeScenario($test);
    }

    public function testThenUnsetExpression()
    {
        $test = $this->initScenario()
            ->ifString()
            ->thenUnset()
        ->end();
        $this->assertEquals(array(), $this->finalizeScenario($test));
    }

    /**
     * Create a test treebuilder with a variable node, and init the validation
     */
    protected function initScenario()
    {
        $builder = new TreeBuilder();
        return $builder
            ->root('test')
            ->children()
            ->variableNode('key')
            ->validate()
       ;
    }

    /**
     * Close the validation process and finalize with the given config
     * @param $config The config you want to use for the finalization, by default
     *                it's a simple array('key'=>'value')
     */
    protected function finalizeScenario($fuildInterface, $config=null)
    {
        return $fuildInterface
            ->end()
            ->end()
            ->end()
            ->buildTree()
            ->finalize($config === null ? array('key'=>'value') : $config)
        ;
    }

    /**
     * Return a closure that will return the given value
     * @param $val The value that the closure must return
     * @return Closure
     */
    protected function returnClosure($val) {
        return function($v) use ($val) {
            return $val;
        };
    }

    /**
     * Assert that the given test builder, will return the given value
     * @param mixed $value
     * @param TreeBuilder $test The tree builder to finalize
     * @param mixed The config values that new to be finalized 
     */
    protected function assertFinalizedValueIs($value, $test, $config=null)
    {
        $this->assertEquals(array('key'=>$value), $this->finalizeScenario($test, $config));
    }
}
