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

    public function testGetDefaultThrowsOnNoConfiguredValue()
    {
        $this->expectException('Symfony\Component\OptionsResolver\Exception\NoConfigurationException');
        $this->expectExceptionMessage('No default value was set for the "foo" option.');
        $resolver = new OptionsResolver();
        $resolver->setDefined($option = 'foo');

        $debug = new OptionsResolverIntrospector($resolver);
        $this->assertSame('bar', $debug->getDefault($option));
    }

    public function testGetDefaultThrowsOnNotDefinedOption()
    {
        $this->expectException('Symfony\Component\OptionsResolver\Exception\UndefinedOptionsException');
        $this->expectExceptionMessage('The option "foo" does not exist.');
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

    public function testGetLazyClosuresThrowsOnNoConfiguredValue()
    {
        $this->expectException('Symfony\Component\OptionsResolver\Exception\NoConfigurationException');
        $this->expectExceptionMessage('No lazy closures were set for the "foo" option.');
        $resolver = new OptionsResolver();
        $resolver->setDefined($option = 'foo');

        $debug = new OptionsResolverIntrospector($resolver);
        $this->assertSame('bar', $debug->getLazyClosures($option));
    }

    public function testGetLazyClosuresThrowsOnNotDefinedOption()
    {
        $this->expectException('Symfony\Component\OptionsResolver\Exception\UndefinedOptionsException');
        $this->expectExceptionMessage('The option "foo" does not exist.');
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

    public function testGetAllowedTypesThrowsOnNoConfiguredValue()
    {
        $this->expectException('Symfony\Component\OptionsResolver\Exception\NoConfigurationException');
        $this->expectExceptionMessage('No allowed types were set for the "foo" option.');
        $resolver = new OptionsResolver();
        $resolver->setDefined($option = 'foo');

        $debug = new OptionsResolverIntrospector($resolver);
        $this->assertSame('bar', $debug->getAllowedTypes($option));
    }

    public function testGetAllowedTypesThrowsOnNotDefinedOption()
    {
        $this->expectException('Symfony\Component\OptionsResolver\Exception\UndefinedOptionsException');
        $this->expectExceptionMessage('The option "foo" does not exist.');
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

    public function testGetAllowedValuesThrowsOnNoConfiguredValue()
    {
        $this->expectException('Symfony\Component\OptionsResolver\Exception\NoConfigurationException');
        $this->expectExceptionMessage('No allowed values were set for the "foo" option.');
        $resolver = new OptionsResolver();
        $resolver->setDefined($option = 'foo');

        $debug = new OptionsResolverIntrospector($resolver);
        $this->assertSame('bar', $debug->getAllowedValues($option));
    }

    public function testGetAllowedValuesThrowsOnNotDefinedOption()
    {
        $this->expectException('Symfony\Component\OptionsResolver\Exception\UndefinedOptionsException');
        $this->expectExceptionMessage('The option "foo" does not exist.');
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

    public function testGetNormalizerThrowsOnNoConfiguredValue()
    {
        $this->expectException('Symfony\Component\OptionsResolver\Exception\NoConfigurationException');
        $this->expectExceptionMessage('No normalizer was set for the "foo" option.');
        $resolver = new OptionsResolver();
        $resolver->setDefined($option = 'foo');

        $debug = new OptionsResolverIntrospector($resolver);
        $this->assertSame('bar', $debug->getNormalizer($option));
    }

    public function testGetNormalizerThrowsOnNotDefinedOption()
    {
        $this->expectException('Symfony\Component\OptionsResolver\Exception\UndefinedOptionsException');
        $this->expectExceptionMessage('The option "foo" does not exist.');
        $resolver = new OptionsResolver();

        $debug = new OptionsResolverIntrospector($resolver);
        $this->assertSame('bar', $debug->getNormalizer('foo'));
    }
}
