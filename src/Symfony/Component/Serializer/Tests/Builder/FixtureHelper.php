<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Builder;

use Symfony\Component\PropertyInfo\Extractor\ConstructorArgumentTypeExtractorAggregate;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\Serializer\Builder\DefinitionExtractor;
use Symfony\Component\Serializer\Tests\Fixtures\CustomNormalizer\FullTypeHints;
use Symfony\Component\Serializer\Tests\Fixtures\CustomNormalizer\NoTypeHints;

class FixtureHelper
{
    public static function getDefinitionExtractor(): DefinitionExtractor
    {
        $reflectionExtractor = new ReflectionExtractor();
        $constructorArgumentExtractor = new ConstructorArgumentTypeExtractorAggregate([
            $reflectionExtractor,
            new PhpDocExtractor(),
        ]);

        return new DefinitionExtractor(
            propertyInfo: self::getPropertyInfoExtractor(),
            propertyReadInfoExtractor: $reflectionExtractor,
            propertyWriteInfoExtractor: $reflectionExtractor,
            constructorArgumentTypeExtractor: $constructorArgumentExtractor,
        );
    }

    public static function getFixturesAndResultFiles(): iterable
    {
        $rootDir = \dirname(__DIR__).'/Fixtures/CustomNormalizer';
        $data = [
            NoTypeHints\PublicProperties::class => $rootDir.'/NoTypeHints/ExpectedNormalizer/PublicProperties.php',
            NoTypeHints\ConstructorInjection::class => $rootDir.'/NoTypeHints/ExpectedNormalizer/ConstructorInjection.php',
            NoTypeHints\SetterInjection::class => $rootDir.'/NoTypeHints/ExpectedNormalizer/SetterInjection.php',
            NoTypeHints\ConstructorAndSetterInjection::class => $rootDir.'/NoTypeHints/ExpectedNormalizer/ConstructorAndSetterInjection.php',
            NoTypeHints\InheritanceChild::class => $rootDir.'/NoTypeHints/ExpectedNormalizer/InheritanceChild.php',

            FullTypeHints\PublicProperties::class => $rootDir.'/FullTypeHints/ExpectedNormalizer/PublicProperties.php',
            FullTypeHints\ConstructorInjection::class => $rootDir.'/FullTypeHints/ExpectedNormalizer/ConstructorInjection.php',
            FullTypeHints\SetterInjection::class => $rootDir.'/FullTypeHints/ExpectedNormalizer/SetterInjection.php',
            FullTypeHints\InheritanceChild::class => $rootDir.'/FullTypeHints/ExpectedNormalizer/InheritanceChild.php',
            FullTypeHints\PrivateConstructor::class => $rootDir.'/FullTypeHints/ExpectedNormalizer/PrivateConstructor.php',
            FullTypeHints\ConstructorWithDefaultValue::class => $rootDir.'/FullTypeHints/ExpectedNormalizer/ConstructorWithDefaultValue.php',
            FullTypeHints\ComplexTypesConstructor::class => $rootDir.'/FullTypeHints/ExpectedNormalizer/ComplexTypesConstructor.php',
            FullTypeHints\ComplexTypesPublicProperties::class => $rootDir.'/FullTypeHints/ExpectedNormalizer/ComplexTypesPublicProperties.php',
            FullTypeHints\ComplexTypesSetter::class => $rootDir.'/FullTypeHints/ExpectedNormalizer/ComplexTypesSetter.php',
            FullTypeHints\ExtraSetter::class => $rootDir.'/FullTypeHints/ExpectedNormalizer/ExtraSetter.php',
            FullTypeHints\NonReadableProperty::class => $rootDir.'/FullTypeHints/ExpectedNormalizer/NonReadableProperty.php',
        ];

        foreach ($data as $class => $normalizerFile) {
            yield $class => [$class, $normalizerFile];
        }
    }

    private static function getPropertyInfoExtractor(): PropertyInfoExtractor
    {
        // a full list of extractors is shown further below
        $phpDocExtractor = new PhpDocExtractor();
        $reflectionExtractor = new ReflectionExtractor();

        // list of PropertyListExtractorInterface (any iterable)
        $listExtractors = [$reflectionExtractor];

        // list of PropertyTypeExtractorInterface (any iterable)
        $typeExtractors = [$phpDocExtractor, $reflectionExtractor];

        // list of PropertyDescriptionExtractorInterface (any iterable)
        $descriptionExtractors = [$phpDocExtractor];

        // list of PropertyAccessExtractorInterface (any iterable)
        $accessExtractors = [$reflectionExtractor];

        // list of PropertyInitializableExtractorInterface (any iterable)
        $propertyInitializableExtractors = [$reflectionExtractor];

        return new PropertyInfoExtractor(
            $listExtractors,
            $typeExtractors,
            $descriptionExtractors,
            $accessExtractors,
            $propertyInitializableExtractors
        );
    }
}
