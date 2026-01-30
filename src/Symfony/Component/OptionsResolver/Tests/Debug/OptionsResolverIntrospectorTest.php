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

    public function testGetDefaultThrowsOnNoConfiguredValue(): void
    {
        $this->expectException(NoConfigurationException::class);
        $this->expectExceptionMessage('No default value was set for the "foo" option.');
        $resolver = new OptionsResolver();
        $resolver->setDefined($option = 'foo');

        $debug = new OptionsResolverIntrospector($resolver);
        $debug->getDefault($option);
    }

    public function testGetDefaultThrowsOnNotDefinedOption(): void
    {
        $this->expectException(UndefinedOptionsException::class);
        $this->expectExceptionMessage('The option "foo" does not exist.');
        $resolver = new OptionsResolver();

        $debug = new OptionsResolverIntrospector($resolver);
        $debug->getDefault('foo');
    }

    public function testGetLazyClosures(): void
    {
        $resolver = new OptionsResolver();
        $closures = [];
        $resolver->setDefault($option = 'foo', $closures[] = static function (Options $options): void {});

        $debug = new OptionsResolverIntrospector($resolver);
        $this->assertSame($closures, $debug->getLazyClosures($option));
    }

    public function testGetLazyClosuresThrowsOnNoConfiguredValue(): void
    {
        $this->expectException(NoConfigurationException::class);
        $this->expectExceptionMessage('No lazy closures were set for the "foo" option.');
        $resolver = new OptionsResolver();
        $resolver->setDefined($option = 'foo');

        $debug = new OptionsResolverIntrospector($resolver);
        $debug->getLazyClosures($option);
    }

    public function testGetLazyClosuresThrowsOnNotDefinedOption(): void
    {
        $this->expectException(UndefinedOptionsException::class);
        $this->expectExceptionMessage('The option "foo" does not exist.');
        $resolver = new OptionsResolver();

        $debug = new OptionsResolverIntrospector($resolver);
        $debug->getLazyClosures('foo');
    }

    public function testGetAllowedTypes(): void
    {
        $resolver = new OptionsResolver();
        $resolver->setDefined($option = 'foo');
        $resolver->setAllowedTypes($option = 'foo', $allowedTypes = ['string', 'bool']);

        $debug = new OptionsResolverIntrospector($resolver);
        $this->assertSame($allowedTypes, $debug->getAllowedTypes($option));
    }

    public function testGetAllowedTypesThrowsOnNoConfiguredValue(): void
    {
        $this->expectException(NoConfigurationException::class);
        $this->expectExceptionMessage('No allowed types were set for the "foo" option.');
        $resolver = new OptionsResolver();
        $resolver->setDefined($option = 'foo');

        $debug = new OptionsResolverIntrospector($resolver);
        $this->assertSame('bar', $debug->getAllowedTypes($option));
    }

    public function testGetAllowedTypesThrowsOnNotDefinedOption(): void
    {
        $this->expectException(UndefinedOptionsException::class);
        $this->expectExceptionMessage('The option "foo" does not exist.');
        $resolver = new OptionsResolver();

        $debug = new OptionsResolverIntrospector($resolver);
        $this->assertSame('bar', $debug->getAllowedTypes('foo'));
    }

    public function testGetAllowedValues(): void
    {
        $resolver = new OptionsResolver();
        $resolver->setDefined($option = 'foo');
        $resolver->setAllowedValues($option = 'foo', $allowedValues = ['bar', 'baz']);

        $debug = new OptionsResolverIntrospector($resolver);
        $this->assertSame($allowedValues, $debug->getAllowedValues($option));
    }

    public function testGetAllowedValuesThrowsOnNoConfiguredValue(): void
    {
        $this->expectException(NoConfigurationException::class);
        $this->expectExceptionMessage('No allowed values were set for the "foo" option.');
        $resolver = new OptionsResolver();
        $resolver->setDefined($option = 'foo');

        $debug = new OptionsResolverIntrospector($resolver);
        $this->assertSame('bar', $debug->getAllowedValues($option));
    }

    public function testGetAllowedValuesThrowsOnNotDefinedOption(): void
    {
        $this->expectException(UndefinedOptionsException::class);
        $this->expectExceptionMessage('The option "foo" does not exist.');
        $resolver = new OptionsResolver();

        $debug = new OptionsResolverIntrospector($resolver);
        $this->assertSame('bar', $debug->getAllowedValues('foo'));
    }

    public function testGetNormalizer(): void
    {
        $resolver = new OptionsResolver();
        $resolver->setDefined($option = 'foo');
        $resolver->setNormalizer($option = 'foo', $normalizer = static function (): void {});

        $debug = new OptionsResolverIntrospector($resolver);
        $this->assertSame($normalizer, $debug->getNormalizer($option));
    }

    public function testGetNormalizerThrowsOnNoConfiguredValue(): void
    {
        $this->expectException(NoConfigurationException::class);
        $this->expectExceptionMessage('No normalizer was set for the "foo" option.');
        $resolver = new OptionsResolver();
        $resolver->setDefined($option = 'foo');

        $debug = new OptionsResolverIntrospector($resolver);
        $this->assertSame('bar', $debug->getNormalizer($option));
    }

    public function testGetNormalizerThrowsOnNotDefinedOption(): void
    {
        $this->expectException(UndefinedOptionsException::class);
        $this->expectExceptionMessage('The option "foo" does not exist.');
        $resolver = new OptionsResolver();

        $debug = new OptionsResolverIntrospector($resolver);
        $this->assertSame('bar', $debug->getNormalizer('foo'));
    }

    public function testGetNormalizers(): void
    {
        $resolver = new OptionsResolver();
        $resolver->setDefined('foo');
        $resolver->addNormalizer('foo', $normalizer1 = static function (): void {});
        $resolver->addNormalizer('foo', $normalizer2 = static function (): void {});

        $debug = new OptionsResolverIntrospector($resolver);
        $this->assertSame([$normalizer1, $normalizer2], $debug->getNormalizers('foo'));
    }

    public function testGetNormalizersThrowsOnNoConfiguredValue(): void
    {
        $this->expectException(NoConfigurationException::class);
        $this->expectExceptionMessage('No normalizer was set for the "foo" option.');
        $resolver = new OptionsResolver();
        $resolver->setDefined('foo');

        $debug = new OptionsResolverIntrospector($resolver);
        $debug->getNormalizers('foo');
    }

    public function testGetNormalizersThrowsOnNotDefinedOption(): void
    {
        $this->expectException(UndefinedOptionsException::class);
        $this->expectExceptionMessage('The option "foo" does not exist.');
        $resolver = new OptionsResolver();

        $debug = new OptionsResolverIntrospector($resolver);
        $debug->getNormalizers('foo');
    }

    public function testGetDeprecation(): void
    {
        $resolver = new OptionsResolver();
        $resolver->setDefined('foo');
        $resolver->setDeprecated('foo', 'vendor/package', '1.1', 'The option "foo" is deprecated.');

        $debug = new OptionsResolverIntrospector($resolver);
        $this->assertSame([
            'package' => 'vendor/package',
            'version' => '1.1',
            'message' => 'The option "foo" is deprecated.',
        ], $debug->getDeprecation('foo'));
    }

    public function testGetClosureDeprecation(): void
    {
        $resolver = new OptionsResolver();
        $resolver->setDefined('foo');
        $resolver->setDeprecated('foo', 'vendor/package', '1.1', $closure = static function (Options $options, $value): void {});

        $debug = new OptionsResolverIntrospector($resolver);
        $this->assertSame([
            'package' => 'vendor/package',
            'version' => '1.1',
            'message' => $closure,
        ], $debug->getDeprecation('foo'));
    }

    public function testGetDeprecationMessageThrowsOnNoConfiguredValue(): void
    {
        $this->expectException(NoConfigurationException::class);
        $this->expectExceptionMessage('No deprecation was set for the "foo" option.');
        $resolver = new OptionsResolver();
        $resolver->setDefined('foo');

        $debug = new OptionsResolverIntrospector($resolver);
        $debug->getDeprecation('foo');
    }

    public function testGetDeprecationMessageThrowsOnNotDefinedOption(): void
    {
        $this->expectException(UndefinedOptionsException::class);
        $this->expectExceptionMessage('The option "foo" does not exist.');
        $resolver = new OptionsResolver();

        $debug = new OptionsResolverIntrospector($resolver);
        $debug->getDeprecation('foo');
    }

    public function testGetClosureNested(): void
    {
        $resolver = new OptionsResolver();
        $resolver->setOptions('foo', $closure = static function (OptionsResolver $resolver): void {});

        $debug = new OptionsResolverIntrospector($resolver);
        $this->assertSame([$closure], $debug->getNestedOptions('foo'));
    }
}
