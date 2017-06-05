<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests;

use Symfony\Component\Form\Test\FormIntegrationTestCase;

/**
 * Form orderer test.
 *
 * @author GeLo <geloen.eric@gmail.com>
 */
class FormOrdererTest extends FormIntegrationTestCase
{
    public function getValidPositions()
    {
        return array(
            // No position
            array(
                array('foo', 'bar', 'baz', 'bat'),
                array('foo', 'bar', 'baz', 'bat'),
            ),

            // First position
            array(
                array('foo' => 'first', 'bar', 'baz', 'bat'),
                array('foo', 'bar', 'baz', 'bat'),
            ),
            array(
                array('bar', 'baz', 'foo' => 'first', 'bat'),
                array('foo', 'bar', 'baz', 'bat'),
            ),
            array(
                array('bar', 'baz', 'bat', 'foo' => 'first'),
                array('foo', 'bar', 'baz', 'bat'),
            ),
            array(
                array('baz', 'foo' => 'first', 'bat', 'bar' => 'first'),
                array('foo', 'bar', 'baz', 'bat'),
            ),

            // Last position
            array(
                array('foo', 'bar', 'baz', 'bat' => 'last'),
                array('foo', 'bar', 'baz', 'bat'),
            ),
            array(
                array('foo', 'bar', 'bat' => 'last', 'baz'),
                array('foo', 'bar', 'baz', 'bat'),
            ),
            array(
                array('bat' => 'last', 'foo', 'bar', 'baz'),
                array('foo', 'bar', 'baz', 'bat'),
            ),
            array(
                array('baz' => 'last', 'foo', 'bat' => 'last', 'bar'),
                array('foo', 'bar', 'baz', 'bat'),
            ),

            // Before position
            array(
                array('foo' => array('before' => 'bar'), 'bar', 'baz', 'bat'),
                array('foo', 'bar', 'baz', 'bat'),
            ),
            array(
                array('bar', 'foo' => array('before' => 'bar'), 'baz', 'bat'),
                array('foo', 'bar', 'baz', 'bat'),
            ),
            array(
                array('bar', 'baz', 'bat', 'foo' => array('before' => 'bar')),
                array('foo', 'bar', 'baz', 'bat'),
            ),
            array(
                array(
                    'bar' => array('before' => 'baz'),
                    'foo' => array('before' => 'bar'),
                    'bat',
                    'baz' => array('before' => 'bat'),
                ),
                array('foo', 'bar', 'baz', 'bat'),
            ),
            array(
                array(
                    'bar' => array('before' => 'bat'),
                    'foo' => array('before' => 'bar'),
                    'bat',
                    'baz' => array('before' => 'bat'),
                ),
                array('foo', 'bar', 'baz', 'bat'),
            ),

            // After position
            array(
                array('foo', 'bar' => array('after' => 'foo'), 'baz', 'bat'),
                array('foo', 'bar', 'baz', 'bat'),
            ),
            array(
                array('bar' => array('after' => 'foo'), 'foo', 'baz', 'bat'),
                array('foo', 'bar', 'baz', 'bat'),
            ),
            array(
                array('foo', 'baz', 'bat', 'bar' => array('after' => 'foo')),
                array('foo', 'bar', 'baz', 'bat'),
            ),
            array(
                array(
                    'foo',
                    'baz' => array('after' => 'bar'),
                    'bat' => array('after' => 'baz'),
                    'bar' => array('after' => 'foo'),
                ),
                array('foo', 'bar', 'baz', 'bat'),
            ),
            array(
                array(
                    'foo',
                    'baz' => array('after' => 'bar'),
                    'bat' => array('after' => 'bar'),
                    'bar' => array('after' => 'foo'),
                ),
                array('foo', 'bar', 'baz', 'bat'),
            ),

            // First & last position
            array(
                array('foo' => 'first', 'bar', 'baz', 'bat' => 'last'),
                array('foo', 'bar', 'baz', 'bat'),
            ),
            array(
                array('bar', 'bat' => 'last', 'foo' => 'first', 'baz'),
                array('foo', 'bar', 'baz', 'bat'),
            ),
            array(
                array('baz' => 'last', 'foo' => 'first', 'bar' => 'first', 'bat' => 'last'),
                array('foo', 'bar', 'baz', 'bat'),
            ),

            // Before & after position
            array(
                array('foo', 'bar' => array('after' => 'foo', 'before' => 'baz'), 'baz', 'bat'),
                array('foo', 'bar', 'baz', 'bat'),
            ),
            array(
                array('foo', 'bar' => array('before' => 'baz', 'after' => 'foo'), 'baz', 'bat'),
                array('foo', 'bar', 'baz', 'bat'),
            ),
            array(
                array('bar' => array('after' => 'foo', 'before' => 'baz'), 'foo', 'baz', 'bat'),
                array('foo', 'bar', 'baz', 'bat'),
            ),
            array(
                array('bar' => array('before' => 'baz', 'after' => 'foo'), 'foo', 'baz', 'bat'),
                array('foo', 'bar', 'baz', 'bat'),
            ),
            array(
                array('foo', 'baz', 'bat', 'bar' => array('after' => 'foo', 'before' => 'baz')),
                array('foo', 'bar', 'baz', 'bat'),
            ),
            array(
                array('foo', 'baz', 'bat', 'bar' => array('before' => 'baz', 'after' => 'foo')),
                array('foo', 'bar', 'baz', 'bat'),
            ),
            array(
                array('foo' => array('before' => 'bar'), 'bar', 'baz' => array('after' => 'bar'), 'bat'),
                array('foo', 'bar', 'baz', 'bat'),
            ),
            array(
                array('bar', 'foo' => array('before' => 'bar'), 'bat', 'baz' => array('after' => 'bar')),
                array('foo', 'bar', 'baz', 'bat'),
            ),
            array(
                array('bar' => array('after' => 'foo'), 'foo', 'bat', 'baz' => array('before' => 'bat')),
                array('foo', 'bar', 'baz', 'bat'),
            ),
            array(
                array(
                    'bar' => array('after' => 'foo', 'before' => 'baz'),
                    'foo',
                    'bat',
                    'baz' => array('before' => 'bat', 'after' => 'bar'),
                ),
                array('foo', 'bar', 'baz', 'bat'),
            ),

            // First, last, before & after position
            array(
                array(
                    'bar' => array('after' => 'foo', 'before' => 'baz'),
                    'foo' => 'first',
                    'bat' => 'last',
                    'baz' => array('before' => 'bat', 'after' => 'bar'),
                ),
                array('foo', 'bar', 'baz', 'bat'),
            ),
            array(
                array(
                    'bar' => array('after' => 'foo', 'before' => 'baz'),
                    'foo' => 'first',
                    'bat',
                    'baz' => array('before' => 'bat'),
                    'nan' => 'last',
                    'pop' => array('after' => 'ban'),
                    'ban',
                    'biz' => array('before' => 'nan'),
                    'boz' => array('before' => 'biz', array('after' => 'pop')),
                ),
                array('foo', 'bar', 'baz', 'bat', 'ban', 'pop', 'boz', 'biz', 'nan'),
            ),
        );
    }

    public function getInvalidPositions()
    {
        return array(
            // Invalid before/after
            array(
                array('foo' => array('before' => 'bar')),
                'The "foo" form is configured to be placed just before the form "bar" but the form "bar" does not exist.',
            ),
            array(
                array('foo' => array('after' => 'bar')),
                'The "foo" form is configured to be placed just after the form "bar" but the form "bar" does not exist.',
            ),

            // Circular before
            array(
                array('foo' => array('before' => 'foo')),
                'The form ordering cannot be resolved due to conflict in before positions (foo => foo)',
            ),
            array(
                array('foo' => array('before' => 'bar'), 'bar' => array('before' => 'foo')),
                'The form ordering cannot be resolved due to conflict in before positions (bar => foo => bar).',
            ),
            array(
                array(
                    'foo' => array('before' => 'bar'),
                    'bar' => array('before' => 'baz'),
                    'baz' => array('before' => 'foo'),
                ),
                'The form ordering cannot be resolved due to conflict in before positions (baz => bar => foo => baz).',
            ),

            // Circular after
            array(
                array('foo' => array('after' => 'foo')),
                'The form ordering cannot be resolved due to conflict in after positions (foo => foo).',
            ),
            array(
                array('foo' => array('after' => 'bar'), 'bar' => array('after' => 'foo')),
                'The form ordering cannot be resolved due to conflict in after positions (bar => foo => bar).',
            ),
            array(
                array(
                    'foo' => array('after' => 'bar'),
                    'bar' => array('after' => 'baz'),
                    'baz' => array('after' => 'foo'),
                ),
                'The form ordering cannot be resolved due to conflict in after positions (baz => bar => foo => baz).',
            ),

            // Symetric before/after
            array(
                array('foo' => array('before' => 'bar'), 'bar' => array('after' => 'foo')),
                'The form ordering does not support symmetrical before/after option (bar <=> foo).',
            ),
            array(
                array(
                    'bat' => array('before' => 'baz'),
                    'baz' => array('after' => 'bar'),
                    'foo' => array('before' => 'bar'),
                    'bar' => array('after' => 'foo'),
                ),
                'The form ordering does not support symmetrical before/after option (bar <=> foo).',
            ),
        );
    }

    /**
     * @dataProvider getValidPositions
     */
    public function testValidPosition(array $config, array $expected)
    {
        $view = $this->createForm($config)->createView();
        $children = array_values($view->children);

        foreach ($expected as $index => $value) {
            $this->assertArrayHasKey($index, $children);
            $this->assertArrayHasKey($value, $view->children);

            $this->assertSame($children[$index], $view->children[$value]);
        }
    }

    /**
     * @dataProvider getInvalidPositions
     */
    public function testInvalidPosition(array $config, $exceptionMessage = null)
    {
        $this->setExpectedException('Symfony\Component\Form\Exception\InvalidConfigurationException', $exceptionMessage);
        $this->createForm($config)->createView();
    }

    private function createForm(array $config)
    {
        $builder = $this->factory->createBuilder();

        foreach ($config as $key => $value) {
            if (is_string($key) && (is_string($value) || is_array($value))) {
                $builder->add($key, null, array('position' => $value));
            } else {
                $builder->add($value);
            }
        }

        return $builder->getForm();
    }
}
