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
        $test = $this->getTestBuilder()
            ->always($this->returnClosure('new_value'))
        ->end();

        $this->assertFinalizedValueIs('new_value', $test);
    }

    public function testIfTrueExpression()
    {
        $test = $this->getTestBuilder()
            ->ifTrue()
            ->then($this->returnClosure('new_value'))
        ->end();
        $this->assertFinalizedValueIs('new_value', $test, array('key'=>true));

        $test = $this->getTestBuilder()
            ->ifTrue( function($v){ return true; })
            ->then($this->returnClosure('new_value'))
        ->end();
        $this->assertFinalizedValueIs('new_value', $test);

        $test = $this->getTestBuilder()
            ->ifTrue( function($v){ return false; })
            ->then($this->returnClosure('new_value'))
        ->end();
        $this->assertFinalizedValueIs('value',$test);
    }

    public function testIfStringExpression()
    {
        $test = $this->getTestBuilder()
            ->ifString()
            ->then($this->returnClosure('new_value'))
        ->end();
        $this->assertFinalizedValueIs('new_value', $test);

        $test = $this->getTestBuilder()
            ->ifString()
            ->then($this->returnClosure('new_value'))
        ->end();
        $this->assertFinalizedValueIs(45, $test, array('key'=>45));

    }

    public function testIfNullExpression()
    {
        $test = $this->getTestBuilder()
            ->ifNull()
            ->then($this->returnClosure('new_value'))
        ->end();
        $this->assertFinalizedValueIs('new_value', $test, array('key'=>null));

        $test = $this->getTestBuilder()
            ->ifNull()
            ->then($this->returnClosure('new_value'))
        ->end();
        $this->assertFinalizedValueIs('value', $test);
    }

    public function testIfArrayExpression()
    {
        $test = $this->getTestBuilder()
            ->ifArray()
            ->then($this->returnClosure('new_value'))
        ->end();
        $this->assertFinalizedValueIs('new_value', $test, array('key'=>array()));

        $test = $this->getTestBuilder()
            ->ifArray()
            ->then($this->returnClosure('new_value'))
        ->end();
        $this->assertFinalizedValueIs('value', $test);
    }

    public function testIfInArrayExpression()
    {
        $test = $this->getTestBuilder()
            ->ifInArray(array('foo', 'bar', 'value'))
            ->then($this->returnClosure('new_value'))
        ->end();
        $this->assertFinalizedValueIs('new_value', $test);

        $test = $this->getTestBuilder()
            ->ifInArray(array('foo', 'bar'))
            ->then($this->returnClosure('new_value'))
        ->end();
        $this->assertFinalizedValueIs('value', $test);
    }

    public function testIfNotInArrayExpression()
    {
        $test = $this->getTestBuilder()
            ->ifNotInArray(array('foo', 'bar'))
            ->then($this->returnClosure('new_value'))
        ->end();
        $this->assertFinalizedValueIs('new_value', $test);

        $test = $this->getTestBuilder()
            ->ifNotInArray(array('foo', 'bar', 'value_from_config' ))
            ->then($this->returnClosure('new_value'))
        ->end();
        $this->assertFinalizedValueIs('new_value', $test);
    }

    public function testIfLessThanExpression()
    {
        $test = $this->getTestBuilder()
            ->ifLessThan(5)
            ->then($this->returnClosure(0))
        ->end();
        $this->assertFinalizedValueIs(0, $test, array('key'=>4));

        $test = $this->getTestBuilder()
            ->ifLessThan(5)
            ->then($this->returnClosure(0))
        ->end();
        $this->assertFinalizedValueIs(6, $test, array('key'=>6));
    }

    public function testIfGreaterThanExpression()
    {
        $test = $this->getTestBuilder()
            ->ifGreaterThan(5)
            ->then($this->returnClosure(100))
        ->end();
        $this->assertFinalizedValueIs(4, $test, array('key'=>4));

        $test = $this->getTestBuilder()
            ->ifGreaterThan(5)
            ->then($this->returnClosure(100))
        ->end();
        $this->assertFinalizedValueIs(100, $test, array('key'=>6));
    }

    public function testIfInRangeExpression()
    {
        $test = $this->getTestBuilder()
            ->ifInRange(4,6)
            ->then($this->returnClosure('in_range'))
        ->end();
        $this->assertFinalizedValueIs('in_range', $test, array('key'=>5));

        $test = $this->getTestBuilder()
            ->ifInRange(4,6)
            ->then($this->returnClosure('in_range'))
        ->end();
        $this->assertFinalizedValueIs(3, $test, array('key'=>3));

        $test = $this->getTestBuilder()
            ->ifInRange(4,6)
            ->then($this->returnClosure('in_range'))
        ->end();
        $this->assertFinalizedValueIs(7, $test, array('key'=>7));
    }

    public function testIfNotInRangeExpression()
    {
        $test = $this->getTestBuilder()
            ->ifNotInRange(4,6)
            ->then($this->returnClosure('not_in'))
        ->end();
        $this->assertFinalizedValueIs('not_in', $test, array('key'=>3));

        $test = $this->getTestBuilder()
            ->ifNotInRange(4,6)
            ->then($this->returnClosure('not_in'))
        ->end();
        $this->assertFinalizedValueIs('not_in', $test, array('key'=>8));

        $test = $this->getTestBuilder()
            ->ifNotInRange(4,6)
            ->then($this->returnClosure('not_in'))
        ->end();
        $this->assertFinalizedValueIs(5, $test, array('key'=>5));
    }

    public function testThenEmptyArrayExpression()
    {
        $test = $this->getTestBuilder()
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
        $test = $this->getTestBuilder()
            ->ifString()
            ->thenInvalid('Invalid value')
        ->end();
        $this->finalizeTestBuilder($test);
    }

    public function testThenUnsetExpression()
    {
        $test = $this->getTestBuilder()
            ->ifString()
            ->thenUnset()
        ->end();
        $this->assertEquals(array(), $this->finalizeTestBuilder($test));
    }

    /**
     * Create a test treebuilder with a variable node, and init the validation
     * @return TreeBuilder
     */
    protected function getTestBuilder()
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
     * @param TreeBuilder $testBuilder The tree builder to finalize
     * @param array $config The config you want to use for the finalization, if nothing provided
     *                       a simple array('key'=>'value') will be used
     * @return array The finalized config values
     */
    protected function finalizeTestBuilder($testBuilder, $config=null)
    {
        return $testBuilder
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
     * @param mixed $value      The value to test
     * @param TreeBuilder $test The tree builder to finalize
     * @param mixed $config     The config values that new to be finalized
     */
    protected function assertFinalizedValueIs($value, $treeBuilder, $config=null)
    {
        $this->assertEquals(array('key'=>$value), $this->finalizeTestBuilder($treeBuilder, $config));
    }
}
