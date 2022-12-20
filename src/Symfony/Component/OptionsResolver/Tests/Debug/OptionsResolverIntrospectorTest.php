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
use Symfony\Component\OptionsResolver\Exception\NoConfigurationException;
use Symfony\Component\OptionsResolver\Exception\UndefinedOptionsException;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class OptionsResolverIntrospectorTest extends TestCase
{
    public function testGetDefault()
    {
        $resolver = new OptionsResolver();
        $resolver->setDefault($option = 'foo', 'bar');

        $debug = new OptionsResolverIntrospector($resolver);
        self::assertSame('bar', $debug->getDefault($option));
    }

    public function testGetDefaultNull()
    {
        $resolver = new OptionsResolver();
        $resolver->setDefault($option = 'foo', null);

        $debug = new OptionsResolverIntrospector($resolver);
        self::assertNull($debug->getDefault($option));
    }

    public function testGetDefaultThrowsOnNoConfiguredValue()
    {
        self::expectException(NoConfigurationException::class);
        self::expectExceptionMessage('No default value was set for the "foo" option.');
        $resolver = new OptionsResolver();
        $resolver->setDefined($option = 'foo');

        $debug = new OptionsResolverIntrospector($resolver);
        $debug->getDefault($option);
    }

    public function testGetDefaultThrowsOnNotDefinedOption()
    {
        self::expectException(UndefinedOptionsException::class);
        self::expectExceptionMessage('The option "foo" does not exist.');
        $resolver = new OptionsResolver();

        $debug = new OptionsResolverIntrospector($resolver);
        $debug->getDefault('foo');
    }

    public function testGetLazyClosures()
    {
        $resolver = new OptionsResolver();
        $closures = [];
        $resolver->setDefault($option = 'foo', $closures[] = function (Options $options) {});

        $debug = new OptionsResolverIntrospector($resolver);
        self::assertSame($closures, $debug->getLazyClosures($option));
    }

    public function testGetLazyClosuresThrowsOnNoConfiguredValue()
    {
        self::expectException(NoConfigurationException::class);
        self::expectExceptionMessage('No lazy closures were set for the "foo" option.');
        $resolver = new OptionsResolver();
        $resolver->setDefined($option = 'foo');

        $debug = new OptionsResolverIntrospector($resolver);
        $debug->getLazyClosures($option);
    }

    public function testGetLazyClosuresThrowsOnNotDefinedOption()
    {
        self::expectException(UndefinedOptionsException::class);
        self::expectExceptionMessage('The option "foo" does not exist.');
        $resolver = new OptionsResolver();

        $debug = new OptionsResolverIntrospector($resolver);
        $debug->getLazyClosures('foo');
    }

    public function testGetAllowedTypes()
    {
        $resolver = new OptionsResolver();
        $resolver->setDefined($option = 'foo');
        $resolver->setAllowedTypes($option = 'foo', $allowedTypes = ['string', 'bool']);

        $debug = new OptionsResolverIntrospector($resolver);
        self::assertSame($allowedTypes, $debug->getAllowedTypes($option));
    }

    public function testGetAllowedTypesThrowsOnNoConfiguredValue()
    {
        self::expectException(NoConfigurationException::class);
        self::expectExceptionMessage('No allowed types were set for the "foo" option.');
        $resolver = new OptionsResolver();
        $resolver->setDefined($option = 'foo');

        $debug = new OptionsResolverIntrospector($resolver);
        self::assertSame('bar', $debug->getAllowedTypes($option));
    }

    public function testGetAllowedTypesThrowsOnNotDefinedOption()
    {
        self::expectException(UndefinedOptionsException::class);
        self::expectExceptionMessage('The option "foo" does not exist.');
        $resolver = new OptionsResolver();

        $debug = new OptionsResolverIntrospector($resolver);
        self::assertSame('bar', $debug->getAllowedTypes('foo'));
    }

    public function testGetAllowedValues()
    {
        $resolver = new OptionsResolver();
        $resolver->setDefined($option = 'foo');
        $resolver->setAllowedValues($option = 'foo', $allowedValues = ['bar', 'baz']);

        $debug = new OptionsResolverIntrospector($resolver);
        self::assertSame($allowedValues, $debug->getAllowedValues($option));
    }

    public function testGetAllowedValuesThrowsOnNoConfiguredValue()
    {
        self::expectException(NoConfigurationException::class);
        self::expectExceptionMessage('No allowed values were set for the "foo" option.');
        $resolver = new OptionsResolver();
        $resolver->setDefined($option = 'foo');

        $debug = new OptionsResolverIntrospector($resolver);
        self::assertSame('bar', $debug->getAllowedValues($option));
    }

    public function testGetAllowedValuesThrowsOnNotDefinedOption()
    {
        self::expectException(UndefinedOptionsException::class);
        self::expectExceptionMessage('The option "foo" does not exist.');
        $resolver = new OptionsResolver();

        $debug = new OptionsResolverIntrospector($resolver);
        self::assertSame('bar', $debug->getAllowedValues('foo'));
    }

    public function testGetNormalizer()
    {
        $resolver = new OptionsResolver();
        $resolver->setDefined($option = 'foo');
        $resolver->setNormalizer($option = 'foo', $normalizer = function () {});

        $debug = new OptionsResolverIntrospector($resolver);
        self::assertSame($normalizer, $debug->getNormalizer($option));
    }

    public function testGetNormalizerThrowsOnNoConfiguredValue()
    {
        self::expectException(NoConfigurationException::class);
        self::expectExceptionMessage('No normalizer was set for the "foo" option.');
        $resolver = new OptionsResolver();
        $resolver->setDefined($option = 'foo');

        $debug = new OptionsResolverIntrospector($resolver);
        self::assertSame('bar', $debug->getNormalizer($option));
    }

    public function testGetNormalizerThrowsOnNotDefinedOption()
    {
        self::expectException(UndefinedOptionsException::class);
        self::expectExceptionMessage('The option "foo" does not exist.');
        $resolver = new OptionsResolver();

        $debug = new OptionsResolverIntrospector($resolver);
        self::assertSame('bar', $debug->getNormalizer('foo'));
    }

    public function testGetNormalizers()
    {
        $resolver = new OptionsResolver();
        $resolver->setDefined('foo');
        $resolver->addNormalizer('foo', $normalizer1 = function () {});
        $resolver->addNormalizer('foo', $normalizer2 = function () {});

        $debug = new OptionsResolverIntrospector($resolver);
        self::assertSame([$normalizer1, $normalizer2], $debug->getNormalizers('foo'));
    }

    public function testGetNormalizersThrowsOnNoConfiguredValue()
    {
        self::expectException(NoConfigurationException::class);
        self::expectExceptionMessage('No normalizer was set for the "foo" option.');
        $resolver = new OptionsResolver();
        $resolver->setDefined('foo');

        $debug = new OptionsResolverIntrospector($resolver);
        $debug->getNormalizers('foo');
    }

    public function testGetNormalizersThrowsOnNotDefinedOption()
    {
        self::expectException(UndefinedOptionsException::class);
        self::expectExceptionMessage('The option "foo" does not exist.');
        $resolver = new OptionsResolver();

        $debug = new OptionsResolverIntrospector($resolver);
        $debug->getNormalizers('foo');
    }

    /**
     * @group legacy
     */
    public function testGetDeprecationMessage()
    {
        $resolver = new OptionsResolver();
        $resolver->setDefined('foo');
        $resolver->setDeprecated('foo', 'The option "foo" is deprecated.');

        $debug = new OptionsResolverIntrospector($resolver);
        self::assertSame('The option "foo" is deprecated.', $debug->getDeprecationMessage('foo'));
    }

    /**
     * @group legacy
     */
    public function testGetClosureDeprecationMessage()
    {
        $resolver = new OptionsResolver();
        $resolver->setDefined('foo');
        $resolver->setDeprecated('foo', $closure = function (Options $options, $value) {});

        $debug = new OptionsResolverIntrospector($resolver);
        self::assertSame($closure, $debug->getDeprecationMessage('foo'));
    }

    public function testGetDeprecation()
    {
        $resolver = new OptionsResolver();
        $resolver->setDefined('foo');
        $resolver->setDeprecated('foo', 'vendor/package', '1.1', 'The option "foo" is deprecated.');

        $debug = new OptionsResolverIntrospector($resolver);
        self::assertSame([
            'package' => 'vendor/package',
            'version' => '1.1',
            'message' => 'The option "foo" is deprecated.',
        ], $debug->getDeprecation('foo'));
    }

    public function testGetClosureDeprecation()
    {
        $resolver = new OptionsResolver();
        $resolver->setDefined('foo');
        $resolver->setDeprecated('foo', 'vendor/package', '1.1', $closure = function (Options $options, $value) {});

        $debug = new OptionsResolverIntrospector($resolver);
        self::assertSame([
            'package' => 'vendor/package',
            'version' => '1.1',
            'message' => $closure,
        ], $debug->getDeprecation('foo'));
    }

    public function testGetDeprecationMessageThrowsOnNoConfiguredValue()
    {
        self::expectException(NoConfigurationException::class);
        self::expectExceptionMessage('No deprecation was set for the "foo" option.');
        $resolver = new OptionsResolver();
        $resolver->setDefined('foo');

        $debug = new OptionsResolverIntrospector($resolver);
        $debug->getDeprecation('foo');
    }

    public function testGetDeprecationMessageThrowsOnNotDefinedOption()
    {
        self::expectException(UndefinedOptionsException::class);
        self::expectExceptionMessage('The option "foo" does not exist.');
        $resolver = new OptionsResolver();

        $debug = new OptionsResolverIntrospector($resolver);
        $debug->getDeprecation('foo');
    }
}
