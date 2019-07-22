<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\OptionsResolver\Tests\Debug;

use PHPUnit\Framework\TestCase;
use Symfony\Component\OptionsResolver\Debug\OptionsResolverIntrospector;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class OptionsResolverIntrospectorTest extends TestCase
{
    public function testGetDefault()
    {
        $resolver = new OptionsResolver();
        $resolver->setDefault($option = 'foo', 'bar');

        $debug = new OptionsResolverIntrospector($resolver);
        $this->assertSame('bar', $debug->getDefault($option));
    }

    public function testGetDefaultNull()
    {
        $resolver = new OptionsResolver();
        $resolver->setDefault($option = 'foo', null);

        $debug = new OptionsResolverIntrospector($resolver);
        $this->assertNull($debug->getDefault($option));
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\NoConfigurationException
     * @expectedExceptionMessage No default value was set for the "foo" option.
     */
    public function testGetDefaultThrowsOnNoConfiguredValue()
    {
        $resolver = new OptionsResolver();
        $resolver->setDefined($option = 'foo');

        $debug = new OptionsResolverIntrospector($resolver);
        $this->assertSame('bar', $debug->getDefault($option));
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\UndefinedOptionsException
     * @expectedExceptionMessage The option "foo" does not exist.
     */
    public function testGetDefaultThrowsOnNotDefinedOption()
    {
        $resolver = new OptionsResolver();

        $debug = new OptionsResolverIntrospector($resolver);
        $this->assertSame('bar', $debug->getDefault('foo'));
    }

    public function testGetLazyClosures()
    {
        $resolver = new OptionsResolver();
        $closures = [];
        $resolver->setDefault($option = 'foo', $closures[] = function (Options $options) {});

        $debug = new OptionsResolverIntrospector($resolver);
        $this->assertSame($closures, $debug->getLazyClosures($option));
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\NoConfigurationException
     * @expectedExceptionMessage No lazy closures were set for the "foo" option.
     */
    public function testGetLazyClosuresThrowsOnNoConfiguredValue()
    {
        $resolver = new OptionsResolver();
        $resolver->setDefined($option = 'foo');

        $debug = new OptionsResolverIntrospector($resolver);
        $this->assertSame('bar', $debug->getLazyClosures($option));
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\UndefinedOptionsException
     * @expectedExceptionMessage The option "foo" does not exist.
     */
    public function testGetLazyClosuresThrowsOnNotDefinedOption()
    {
        $resolver = new OptionsResolver();

        $debug = new OptionsResolverIntrospector($resolver);
        $this->assertSame('bar', $debug->getLazyClosures('foo'));
    }

    public function testGetAllowedTypes()
    {
        $resolver = new OptionsResolver();
        $resolver->setDefined($option = 'foo');
        $resolver->setAllowedTypes($option = 'foo', $allowedTypes = ['string', 'bool']);

        $debug = new OptionsResolverIntrospector($resolver);
        $this->assertSame($allowedTypes, $debug->getAllowedTypes($option));
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\NoConfigurationException
     * @expectedExceptionMessage No allowed types were set for the "foo" option.
     */
    public function testGetAllowedTypesThrowsOnNoConfiguredValue()
    {
        $resolver = new OptionsResolver();
        $resolver->setDefined($option = 'foo');

        $debug = new OptionsResolverIntrospector($resolver);
        $this->assertSame('bar', $debug->getAllowedTypes($option));
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\UndefinedOptionsException
     * @expectedExceptionMessage The option "foo" does not exist.
     */
    public function testGetAllowedTypesThrowsOnNotDefinedOption()
    {
        $resolver = new OptionsResolver();

        $debug = new OptionsResolverIntrospector($resolver);
        $this->assertSame('bar', $debug->getAllowedTypes('foo'));
    }

    public function testGetAllowedValues()
    {
        $resolver = new OptionsResolver();
        $resolver->setDefined($option = 'foo');
        $resolver->setAllowedValues($option = 'foo', $allowedValues = ['bar', 'baz']);

        $debug = new OptionsResolverIntrospector($resolver);
        $this->assertSame($allowedValues, $debug->getAllowedValues($option));
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\NoConfigurationException
     * @expectedExceptionMessage No allowed values were set for the "foo" option.
     */
    public function testGetAllowedValuesThrowsOnNoConfiguredValue()
    {
        $resolver = new OptionsResolver();
        $resolver->setDefined($option = 'foo');

        $debug = new OptionsResolverIntrospector($resolver);
        $this->assertSame('bar', $debug->getAllowedValues($option));
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\UndefinedOptionsException
     * @expectedExceptionMessage The option "foo" does not exist.
     */
    public function testGetAllowedValuesThrowsOnNotDefinedOption()
    {
        $resolver = new OptionsResolver();

        $debug = new OptionsResolverIntrospector($resolver);
        $this->assertSame('bar', $debug->getAllowedValues('foo'));
    }

    public function testGetNormalizer()
    {
        $resolver = new OptionsResolver();
        $resolver->setDefined($option = 'foo');
        $resolver->setNormalizer($option = 'foo', $normalizer = function () {});

        $debug = new OptionsResolverIntrospector($resolver);
        $this->assertSame($normalizer, $debug->getNormalizer($option));
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\NoConfigurationException
     * @expectedExceptionMessage No normalizer was set for the "foo" option.
     */
    public function testGetNormalizerThrowsOnNoConfiguredValue()
    {
        $resolver = new OptionsResolver();
        $resolver->setDefined($option = 'foo');

        $debug = new OptionsResolverIntrospector($resolver);
        $this->assertSame('bar', $debug->getNormalizer($option));
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\UndefinedOptionsException
     * @expectedExceptionMessage The option "foo" does not exist.
     */
    public function testGetNormalizerThrowsOnNotDefinedOption()
    {
        $resolver = new OptionsResolver();

        $debug = new OptionsResolverIntrospector($resolver);
        $this->assertSame('bar', $debug->getNormalizer('foo'));
    }

    public function testGetNormalizers()
    {
        $resolver = new OptionsResolver();
        $resolver->setDefined('foo');
        $resolver->addNormalizer('foo', $normalizer1 = function () {});
        $resolver->addNormalizer('foo', $normalizer2 = function () {});

        $debug = new OptionsResolverIntrospector($resolver);
        $this->assertSame([$normalizer1, $normalizer2], $debug->getNormalizers('foo'));
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\NoConfigurationException
     * @expectedExceptionMessage No normalizer was set for the "foo" option.
     */
    public function testGetNormalizersThrowsOnNoConfiguredValue()
    {
        $resolver = new OptionsResolver();
        $resolver->setDefined('foo');

        $debug = new OptionsResolverIntrospector($resolver);
        $debug->getNormalizers('foo');
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\UndefinedOptionsException
     * @expectedExceptionMessage The option "foo" does not exist.
     */
    public function testGetNormalizersThrowsOnNotDefinedOption()
    {
        $resolver = new OptionsResolver();

        $debug = new OptionsResolverIntrospector($resolver);
        $debug->getNormalizers('foo');
    }

    public function testGetDeprecationMessage()
    {
        $resolver = new OptionsResolver();
        $resolver->setDefined('foo');
        $resolver->setDeprecated('foo', 'The option "foo" is deprecated.');

        $debug = new OptionsResolverIntrospector($resolver);
        $this->assertSame('The option "foo" is deprecated.', $debug->getDeprecationMessage('foo'));
    }

    public function testGetClosureDeprecationMessage()
    {
        $resolver = new OptionsResolver();
        $resolver->setDefined('foo');
        $resolver->setDeprecated('foo', $closure = function (Options $options, $value) {});

        $debug = new OptionsResolverIntrospector($resolver);
        $this->assertSame($closure, $debug->getDeprecationMessage('foo'));
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\NoConfigurationException
     * @expectedExceptionMessage No deprecation was set for the "foo" option.
     */
    public function testGetDeprecationMessageThrowsOnNoConfiguredValue()
    {
        $resolver = new OptionsResolver();
        $resolver->setDefined('foo');

        $debug = new OptionsResolverIntrospector($resolver);
        $this->assertSame('bar', $debug->getDeprecationMessage('foo'));
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\UndefinedOptionsException
     * @expectedExceptionMessage The option "foo" does not exist.
     */
    public function testGetDeprecationMessageThrowsOnNotDefinedOption()
    {
        $resolver = new OptionsResolver();

        $debug = new OptionsResolverIntrospector($resolver);
        $this->assertSame('bar', $debug->getDeprecationMessage('foo'));
    }
}
