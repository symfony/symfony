<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\OptionsResolver\Tests;

use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\OptionsResolver\Options;

/**
 * @group legacy
 */
class LegacyOptionsResolverTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var OptionsResolver
     */
    private $resolver;

    protected function setUp()
    {
        $this->iniSet('error_reporting', -1 & ~E_USER_DEPRECATED);

        $this->resolver = new OptionsResolver();
    }

    public function testResolve()
    {
        $this->resolver->setDefaults(array(
            'one' => '1',
            'two' => '2',
        ));

        $options = array(
            'two' => '20',
        );

        $this->assertEquals(array(
            'one' => '1',
            'two' => '20',
        ), $this->resolver->resolve($options));
    }

    public function testResolveNumericOptions()
    {
        $this->resolver->setDefaults(array(
            '1' => '1',
            '2' => '2',
        ));

        $options = array(
            '2' => '20',
        );

        $this->assertEquals(array(
            '1' => '1',
            '2' => '20',
        ), $this->resolver->resolve($options));
    }

    public function testResolveLazy()
    {
        $this->resolver->setDefaults(array(
            'one' => '1',
            'two' => function (Options $options) {
                return '20';
            },
        ));

        $this->assertEquals(array(
            'one' => '1',
            'two' => '20',
        ), $this->resolver->resolve(array()));
    }

    public function testResolveLazyDependencyOnOptional()
    {
        $this->resolver->setDefaults(array(
            'one' => '1',
            'two' => function (Options $options) {
                return $options['one'].'2';
            },
        ));

        $options = array(
            'one' => '10',
        );

        $this->assertEquals(array(
            'one' => '10',
            'two' => '102',
        ), $this->resolver->resolve($options));
    }

    public function testResolveLazyDependencyOnMissingOptionalWithoutDefault()
    {
        $test = $this;

        $this->resolver->setOptional(array(
            'one',
        ));

        $this->resolver->setDefaults(array(
            'two' => function (Options $options) use ($test) {
                /* @var \PHPUnit_Framework_TestCase $test */
                $test->assertFalse(isset($options['one']));

                return '2';
            },
        ));

        $options = array();

        $this->assertEquals(array(
            'two' => '2',
        ), $this->resolver->resolve($options));
    }

    public function testResolveLazyDependencyOnOptionalWithoutDefault()
    {
        $test = $this;

        $this->resolver->setOptional(array(
            'one',
        ));

        $this->resolver->setDefaults(array(
            'two' => function (Options $options) use ($test) {
                /* @var \PHPUnit_Framework_TestCase $test */
                $test->assertTrue(isset($options['one']));

                return $options['one'].'2';
            },
        ));

        $options = array(
            'one' => '10',
        );

        $this->assertEquals(array(
            'one' => '10',
            'two' => '102',
        ), $this->resolver->resolve($options));
    }

    public function testResolveLazyDependencyOnRequired()
    {
        $this->resolver->setRequired(array(
            'one',
        ));
        $this->resolver->setDefaults(array(
            'two' => function (Options $options) {
                return $options['one'].'2';
            },
        ));

        $options = array(
            'one' => '10',
        );

        $this->assertEquals(array(
            'one' => '10',
            'two' => '102',
        ), $this->resolver->resolve($options));
    }

    public function testResolveLazyReplaceDefaults()
    {
        $test = $this;

        $this->resolver->setDefaults(array(
            'one' => function (Options $options) use ($test) {
                /* @var \PHPUnit_Framework_TestCase $test */
                $test->fail('Previous closure should not be executed');
            },
        ));

        $this->resolver->replaceDefaults(array(
            'one' => function (Options $options, $previousValue) {
                return '1';
            },
        ));

        $this->assertEquals(array(
            'one' => '1',
        ), $this->resolver->resolve(array()));
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\UndefinedOptionsException
     */
    public function testResolveFailsIfNonExistingOption()
    {
        $this->resolver->setDefaults(array(
            'one' => '1',
        ));

        $this->resolver->setRequired(array(
            'two',
        ));

        $this->resolver->setOptional(array(
            'three',
        ));

        $this->resolver->resolve(array(
            'foo' => 'bar',
        ));
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\MissingOptionsException
     */
    public function testResolveFailsIfMissingRequiredOption()
    {
        $this->resolver->setRequired(array(
            'one',
        ));

        $this->resolver->setDefaults(array(
            'two' => '2',
        ));

        $this->resolver->resolve(array(
            'two' => '20',
        ));
    }

    public function testResolveSucceedsIfOptionValueAllowed()
    {
        $this->resolver->setDefaults(array(
            'one' => '1',
        ));

        $this->resolver->setAllowedValues(array(
            'one' => array('1', 'one'),
        ));

        $options = array(
            'one' => 'one',
        );

        $this->assertEquals(array(
            'one' => 'one',
        ), $this->resolver->resolve($options));
    }

    public function testResolveSucceedsIfOptionValueAllowed2()
    {
        $this->resolver->setDefaults(array(
            'one' => '1',
            'two' => '2',
        ));

        $this->resolver->setAllowedValues(array(
            'one' => '1',
            'two' => '2',
        ));
        $this->resolver->addAllowedValues(array(
            'one' => 'one',
            'two' => 'two',
        ));

        $options = array(
            'one' => '1',
            'two' => 'two',
        );

        $this->assertEquals(array(
            'one' => '1',
            'two' => 'two',
        ), $this->resolver->resolve($options));
    }

    public function testResolveSucceedsIfOptionalWithAllowedValuesNotSet()
    {
        $this->resolver->setRequired(array(
            'one',
        ));

        $this->resolver->setOptional(array(
            'two',
        ));

        $this->resolver->setAllowedValues(array(
            'one' => array('1', 'one'),
            'two' => array('2', 'two'),
        ));

        $options = array(
            'one' => '1',
        );

        $this->assertEquals(array(
            'one' => '1',
        ), $this->resolver->resolve($options));
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     */
    public function testResolveFailsIfOptionValueNotAllowed()
    {
        $this->resolver->setDefaults(array(
            'one' => '1',
        ));

        $this->resolver->setAllowedValues(array(
            'one' => array('1', 'one'),
        ));

        $this->resolver->resolve(array(
            'one' => '2',
        ));
    }

    public function testResolveSucceedsIfOptionTypeAllowed()
    {
        $this->resolver->setDefaults(array(
            'one' => '1',
        ));

        $this->resolver->setAllowedTypes(array(
            'one' => 'string',
        ));

        $options = array(
            'one' => 'one',
        );

        $this->assertEquals(array(
            'one' => 'one',
        ), $this->resolver->resolve($options));
    }

    public function testResolveSucceedsIfOptionTypeAllowedPassArray()
    {
        $this->resolver->setDefaults(array(
            'one' => '1',
        ));

        $this->resolver->setAllowedTypes(array(
            'one' => array('string', 'bool'),
        ));

        $options = array(
            'one' => true,
        );

        $this->assertEquals(array(
            'one' => true,
        ), $this->resolver->resolve($options));
    }

    public function testResolveSucceedsIfOptionTypeAllowedPassObject()
    {
        $this->resolver->setDefaults(array(
            'one' => '1',
        ));

        $this->resolver->setAllowedTypes(array(
            'one' => 'object',
        ));

        $object = new \stdClass();
        $options = array(
            'one' => $object,
        );

        $this->assertEquals(array(
            'one' => $object,
        ), $this->resolver->resolve($options));
    }

    public function testResolveSucceedsIfOptionTypeAllowedPassClass()
    {
        $this->resolver->setDefaults(array(
            'one' => '1',
        ));

        $this->resolver->setAllowedTypes(array(
            'one' => '\stdClass',
        ));

        $object = new \stdClass();
        $options = array(
            'one' => $object,
        );

        $this->assertEquals(array(
            'one' => $object,
        ), $this->resolver->resolve($options));
    }

    public function testResolveSucceedsIfOptionTypeAllowedAddTypes()
    {
        $this->resolver->setDefaults(array(
            'one' => '1',
            'two' => '2',
        ));

        $this->resolver->setAllowedTypes(array(
            'one' => 'string',
            'two' => 'bool',
        ));
        $this->resolver->addAllowedTypes(array(
            'one' => 'float',
            'two' => 'integer',
        ));

        $options = array(
            'one' => 1.23,
            'two' => false,
        );

        $this->assertEquals(array(
            'one' => 1.23,
            'two' => false,
        ), $this->resolver->resolve($options));
    }

    public function testResolveSucceedsIfOptionalWithTypeAndWithoutValue()
    {
        $this->resolver->setOptional(array(
            'one',
            'two',
        ));

        $this->resolver->setAllowedTypes(array(
            'one' => 'string',
            'two' => 'int',
        ));

        $options = array(
            'two' => 1,
        );

        $this->assertEquals(array(
            'two' => 1,
        ), $this->resolver->resolve($options));
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     */
    public function testResolveFailsIfOptionTypeNotAllowed()
    {
        $this->resolver->setDefaults(array(
            'one' => '1',
        ));

        $this->resolver->setAllowedTypes(array(
            'one' => array('string', 'bool'),
        ));

        $this->resolver->resolve(array(
            'one' => 1.23,
        ));
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     */
    public function testResolveFailsIfOptionTypeNotAllowedMultipleOptions()
    {
        $this->resolver->setDefaults(array(
            'one' => '1',
            'two' => '2',
        ));

        $this->resolver->setAllowedTypes(array(
            'one' => 'string',
            'two' => 'bool',
        ));

        $this->resolver->resolve(array(
            'one' => 'foo',
            'two' => 1.23,
        ));
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     */
    public function testResolveFailsIfOptionTypeNotAllowedAddTypes()
    {
        $this->resolver->setDefaults(array(
            'one' => '1',
        ));

        $this->resolver->setAllowedTypes(array(
            'one' => 'string',
        ));
        $this->resolver->addAllowedTypes(array(
            'one' => 'bool',
        ));

        $this->resolver->resolve(array(
            'one' => 1.23,
        ));
    }

    public function testFluidInterface()
    {
        $this->resolver->setDefaults(array('one' => '1'))
            ->replaceDefaults(array('one' => '2'))
            ->setAllowedValues(array('one' => array('1', '2')))
            ->addAllowedValues(array('one' => array('3')))
            ->setRequired(array('two'))
            ->setOptional(array('three'));

        $options = array(
            'two' => '2',
        );

        $this->assertEquals(array(
            'one' => '2',
            'two' => '2',
        ), $this->resolver->resolve($options));
    }

    public function testKnownIfDefaultWasSet()
    {
        $this->assertFalse($this->resolver->isKnown('foo'));

        $this->resolver->setDefaults(array(
            'foo' => 'bar',
        ));

        $this->assertTrue($this->resolver->isKnown('foo'));
    }

    public function testKnownIfRequired()
    {
        $this->assertFalse($this->resolver->isKnown('foo'));

        $this->resolver->setRequired(array(
            'foo',
        ));

        $this->assertTrue($this->resolver->isKnown('foo'));
    }

    public function testKnownIfOptional()
    {
        $this->assertFalse($this->resolver->isKnown('foo'));

        $this->resolver->setOptional(array(
            'foo',
        ));

        $this->assertTrue($this->resolver->isKnown('foo'));
    }

    public function testRequiredIfRequired()
    {
        $this->assertFalse($this->resolver->isRequired('foo'));

        $this->resolver->setRequired(array(
            'foo',
        ));

        $this->assertTrue($this->resolver->isRequired('foo'));
    }

    public function testNormalizersTransformFinalOptions()
    {
        $this->resolver->setDefaults(array(
            'foo' => 'bar',
            'bam' => 'baz',
        ));
        $this->resolver->setNormalizers(array(
            'foo' => function (Options $options, $value) {
                return $options['bam'].'['.$value.']';
            },
        ));

        $expected = array(
            'foo' => 'baz[bar]',
            'bam' => 'baz',
        );

        $this->assertEquals($expected, $this->resolver->resolve(array()));

        $expected = array(
            'foo' => 'boo[custom]',
            'bam' => 'boo',
        );

        $this->assertEquals($expected, $this->resolver->resolve(array(
            'foo' => 'custom',
            'bam' => 'boo',
        )));
    }

    public function testResolveWithoutOptionSucceedsIfRequiredAndDefaultValue()
    {
        $this->resolver->setRequired(array(
            'foo',
        ));
        $this->resolver->setDefaults(array(
            'foo' => 'bar',
        ));

        $this->assertEquals(array(
            'foo' => 'bar',
        ), $this->resolver->resolve(array()));
    }

    public function testResolveWithoutOptionSucceedsIfDefaultValueAndRequired()
    {
        $this->resolver->setDefaults(array(
            'foo' => 'bar',
        ));
        $this->resolver->setRequired(array(
            'foo',
        ));

        $this->assertEquals(array(
            'foo' => 'bar',
        ), $this->resolver->resolve(array()));
    }

    public function testResolveSucceedsIfOptionRequiredAndValueAllowed()
    {
        $this->resolver->setRequired(array(
            'one', 'two',
        ));
        $this->resolver->setAllowedValues(array(
            'two' => array('2'),
        ));

        $options = array(
            'one' => '1',
            'two' => '2',
        );

        $this->assertEquals($options, $this->resolver->resolve($options));
    }

    public function testResolveSucceedsIfValueAllowedCallbackReturnsTrue()
    {
        $this->resolver->setRequired(array(
            'test',
        ));
        $this->resolver->setAllowedValues(array(
            'test' => function ($value) {
                return true;
            },
        ));

        $options = array(
            'test' => true,
        );

        $this->assertEquals($options, $this->resolver->resolve($options));
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     */
    public function testResolveFailsIfValueAllowedCallbackReturnsFalse()
    {
        $this->resolver->setRequired(array(
            'test',
        ));
        $this->resolver->setAllowedValues(array(
            'test' => function ($value) {
                return false;
            },
        ));

        $options = array(
            'test' => true,
        );

        $this->assertEquals($options, $this->resolver->resolve($options));
    }

    public function testClone()
    {
        $this->resolver->setDefaults(array('one' => '1'));

        $clone = clone $this->resolver;

        // Changes after cloning don't affect each other
        $this->resolver->setDefaults(array('two' => '2'));
        $clone->setDefaults(array('three' => '3'));

        $this->assertEquals(array(
            'one' => '1',
            'two' => '2',
        ), $this->resolver->resolve());

        $this->assertEquals(array(
            'one' => '1',
            'three' => '3',
        ), $clone->resolve());
    }

    public function testOverloadReturnsThis()
    {
        $this->assertSame($this->resolver, $this->resolver->overload('foo', 'bar'));
    }

    public function testOverloadCallsSet()
    {
        $this->resolver->overload('foo', 'bar');

        $this->assertSame(array('foo' => 'bar'), $this->resolver->resolve());
    }
}
