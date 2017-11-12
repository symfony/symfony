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
    public function testGetDefault(): void
    {
        $resolver = new OptionsResolver();
        $resolver->setDefault($option = 'foo', 'bar');

        $debug = new OptionsResolverIntrospector($resolver);
        $this->assertSame('bar', $debug->getDefault($option));
    }

    public function testGetDefaultNull(): void
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
    public function testGetDefaultThrowsOnNoConfiguredValue(): void
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
    public function testGetDefaultThrowsOnNotDefinedOption(): void
    {
        $resolver = new OptionsResolver();

        $debug = new OptionsResolverIntrospector($resolver);
        $this->assertSame('bar', $debug->getDefault('foo'));
    }

    public function testGetLazyClosures(): void
    {
        $resolver = new OptionsResolver();
        $closures = array();
        $resolver->setDefault($option = 'foo', $closures[] = function (Options $options): void {});

        $debug = new OptionsResolverIntrospector($resolver);
        $this->assertSame($closures, $debug->getLazyClosures($option));
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\NoConfigurationException
     * @expectedExceptionMessage No lazy closures were set for the "foo" option.
     */
    public function testGetLazyClosuresThrowsOnNoConfiguredValue(): void
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
    public function testGetLazyClosuresThrowsOnNotDefinedOption(): void
    {
        $resolver = new OptionsResolver();

        $debug = new OptionsResolverIntrospector($resolver);
        $this->assertSame('bar', $debug->getLazyClosures('foo'));
    }

    public function testGetAllowedTypes(): void
    {
        $resolver = new OptionsResolver();
        $resolver->setDefined($option = 'foo');
        $resolver->setAllowedTypes($option = 'foo', $allowedTypes = array('string', 'bool'));

        $debug = new OptionsResolverIntrospector($resolver);
        $this->assertSame($allowedTypes, $debug->getAllowedTypes($option));
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\NoConfigurationException
     * @expectedExceptionMessage No allowed types were set for the "foo" option.
     */
    public function testGetAllowedTypesThrowsOnNoConfiguredValue(): void
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
    public function testGetAllowedTypesThrowsOnNotDefinedOption(): void
    {
        $resolver = new OptionsResolver();

        $debug = new OptionsResolverIntrospector($resolver);
        $this->assertSame('bar', $debug->getAllowedTypes('foo'));
    }

    public function testGetAllowedValues(): void
    {
        $resolver = new OptionsResolver();
        $resolver->setDefined($option = 'foo');
        $resolver->setAllowedValues($option = 'foo', $allowedValues = array('bar', 'baz'));

        $debug = new OptionsResolverIntrospector($resolver);
        $this->assertSame($allowedValues, $debug->getAllowedValues($option));
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\NoConfigurationException
     * @expectedExceptionMessage No allowed values were set for the "foo" option.
     */
    public function testGetAllowedValuesThrowsOnNoConfiguredValue(): void
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
    public function testGetAllowedValuesThrowsOnNotDefinedOption(): void
    {
        $resolver = new OptionsResolver();

        $debug = new OptionsResolverIntrospector($resolver);
        $this->assertSame('bar', $debug->getAllowedValues('foo'));
    }

    public function testGetNormalizer(): void
    {
        $resolver = new OptionsResolver();
        $resolver->setDefined($option = 'foo');
        $resolver->setNormalizer($option = 'foo', $normalizer = function (): void {});

        $debug = new OptionsResolverIntrospector($resolver);
        $this->assertSame($normalizer, $debug->getNormalizer($option));
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\NoConfigurationException
     * @expectedExceptionMessage No normalizer was set for the "foo" option.
     */
    public function testGetNormalizerThrowsOnNoConfiguredValue(): void
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
    public function testGetNormalizerThrowsOnNotDefinedOption(): void
    {
        $resolver = new OptionsResolver();

        $debug = new OptionsResolverIntrospector($resolver);
        $this->assertSame('bar', $debug->getNormalizer('foo'));
    }
}
