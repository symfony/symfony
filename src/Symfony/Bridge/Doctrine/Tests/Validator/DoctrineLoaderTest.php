<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Tests\Validator;

use Doctrine\ORM\Mapping\Column;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\Tests\DoctrineTestHelper;
use Symfony\Bridge\Doctrine\Tests\Fixtures\BaseUser;
use Symfony\Bridge\Doctrine\Tests\Fixtures\DoctrineLoaderEmbed;
use Symfony\Bridge\Doctrine\Tests\Fixtures\DoctrineLoaderEntity;
use Symfony\Bridge\Doctrine\Tests\Fixtures\DoctrineLoaderEnum;
use Symfony\Bridge\Doctrine\Tests\Fixtures\DoctrineLoaderNestedEmbed;
use Symfony\Bridge\Doctrine\Tests\Fixtures\DoctrineLoaderNoAutoMappingEntity;
use Symfony\Bridge\Doctrine\Tests\Fixtures\DoctrineLoaderParentEntity;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Bridge\Doctrine\Validator\DoctrineLoader;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Mapping\AutoMappingStrategy;
use Symfony\Component\Validator\Mapping\CascadingStrategy;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Loader\AutoMappingTrait;
use Symfony\Component\Validator\Mapping\PropertyMetadata;
use Symfony\Component\Validator\Mapping\TraversalStrategy;
use Symfony\Component\Validator\Tests\Fixtures\Entity;
use Symfony\Component\Validator\Validation;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class DoctrineLoaderTest extends TestCase
{
    protected function setUp(): void
    {
        if (!trait_exists(AutoMappingTrait::class)) {
            self::markTestSkipped('Auto-mapping requires symfony/validation 4.4+');
        }
    }

    public function testLoadClassMetadata()
    {
        $validator = Validation::createValidatorBuilder()
            ->enableAnnotationMapping(true)
            ->addDefaultDoctrineAnnotationReader()
            ->addLoader(new DoctrineLoader(DoctrineTestHelper::createTestEntityManager(), '{^Symfony\\\\Bridge\\\\Doctrine\\\\Tests\\\\Fixtures\\\\DoctrineLoader}'))
            ->getValidator()
        ;

        $classMetadata = $validator->getMetadataFor(new DoctrineLoaderEntity());

        $classConstraints = $classMetadata->getConstraints();
        self::assertCount(2, $classConstraints);
        self::assertInstanceOf(UniqueEntity::class, $classConstraints[0]);
        self::assertInstanceOf(UniqueEntity::class, $classConstraints[1]);
        self::assertSame(['alreadyMappedUnique'], $classConstraints[0]->fields);
        self::assertSame('unique', $classConstraints[1]->fields);

        $maxLengthMetadata = $classMetadata->getPropertyMetadata('maxLength');
        self::assertCount(1, $maxLengthMetadata);
        $maxLengthConstraints = $maxLengthMetadata[0]->getConstraints();
        self::assertCount(1, $maxLengthConstraints);
        self::assertInstanceOf(Length::class, $maxLengthConstraints[0]);
        self::assertSame(20, $maxLengthConstraints[0]->max);

        $mergedMaxLengthMetadata = $classMetadata->getPropertyMetadata('mergedMaxLength');
        self::assertCount(1, $mergedMaxLengthMetadata);
        $mergedMaxLengthConstraints = $mergedMaxLengthMetadata[0]->getConstraints();
        self::assertCount(1, $mergedMaxLengthConstraints);
        self::assertInstanceOf(Length::class, $mergedMaxLengthConstraints[0]);
        self::assertSame(20, $mergedMaxLengthConstraints[0]->max);
        self::assertSame(5, $mergedMaxLengthConstraints[0]->min);

        $alreadyMappedMaxLengthMetadata = $classMetadata->getPropertyMetadata('alreadyMappedMaxLength');
        self::assertCount(1, $alreadyMappedMaxLengthMetadata);
        $alreadyMappedMaxLengthConstraints = $alreadyMappedMaxLengthMetadata[0]->getConstraints();
        self::assertCount(1, $alreadyMappedMaxLengthConstraints);
        self::assertInstanceOf(Length::class, $alreadyMappedMaxLengthConstraints[0]);
        self::assertSame(10, $alreadyMappedMaxLengthConstraints[0]->max);
        self::assertSame(1, $alreadyMappedMaxLengthConstraints[0]->min);

        $publicParentMaxLengthMetadata = $classMetadata->getPropertyMetadata('publicParentMaxLength');
        self::assertCount(1, $publicParentMaxLengthMetadata);
        $publicParentMaxLengthConstraints = $publicParentMaxLengthMetadata[0]->getConstraints();
        self::assertCount(1, $publicParentMaxLengthConstraints);
        self::assertInstanceOf(Length::class, $publicParentMaxLengthConstraints[0]);
        self::assertSame(35, $publicParentMaxLengthConstraints[0]->max);

        $embeddedMetadata = $classMetadata->getPropertyMetadata('embedded');
        self::assertCount(1, $embeddedMetadata);
        self::assertSame(CascadingStrategy::CASCADE, $embeddedMetadata[0]->getCascadingStrategy());
        self::assertSame(TraversalStrategy::IMPLICIT, $embeddedMetadata[0]->getTraversalStrategy());

        $parentClassMetadata = $validator->getMetadataFor(new DoctrineLoaderParentEntity());

        $publicParentMaxLengthMetadata = $parentClassMetadata->getPropertyMetadata('publicParentMaxLength');
        self::assertCount(0, $publicParentMaxLengthMetadata);

        $privateParentMaxLengthMetadata = $parentClassMetadata->getPropertyMetadata('privateParentMaxLength');
        self::assertCount(1, $privateParentMaxLengthMetadata);
        $privateParentMaxLengthConstraints = $privateParentMaxLengthMetadata[0]->getConstraints();
        self::assertCount(1, $privateParentMaxLengthConstraints);
        self::assertInstanceOf(Length::class, $privateParentMaxLengthConstraints[0]);
        self::assertSame(30, $privateParentMaxLengthConstraints[0]->max);

        $embeddedClassMetadata = $validator->getMetadataFor(new DoctrineLoaderEmbed());

        $embeddedMaxLengthMetadata = $embeddedClassMetadata->getPropertyMetadata('embeddedMaxLength');
        self::assertCount(1, $embeddedMaxLengthMetadata);
        $embeddedMaxLengthConstraints = $embeddedMaxLengthMetadata[0]->getConstraints();
        self::assertCount(1, $embeddedMaxLengthConstraints);
        self::assertInstanceOf(Length::class, $embeddedMaxLengthConstraints[0]);
        self::assertSame(25, $embeddedMaxLengthConstraints[0]->max);

        $nestedEmbeddedMetadata = $embeddedClassMetadata->getPropertyMetadata('nestedEmbedded');
        self::assertCount(1, $nestedEmbeddedMetadata);
        self::assertSame(CascadingStrategy::CASCADE, $nestedEmbeddedMetadata[0]->getCascadingStrategy());
        self::assertSame(TraversalStrategy::IMPLICIT, $nestedEmbeddedMetadata[0]->getTraversalStrategy());

        $nestedEmbeddedClassMetadata = $validator->getMetadataFor(new DoctrineLoaderNestedEmbed());

        $nestedEmbeddedMaxLengthMetadata = $nestedEmbeddedClassMetadata->getPropertyMetadata('nestedEmbeddedMaxLength');
        self::assertCount(1, $nestedEmbeddedMaxLengthMetadata);
        $nestedEmbeddedMaxLengthConstraints = $nestedEmbeddedMaxLengthMetadata[0]->getConstraints();
        self::assertCount(1, $nestedEmbeddedMaxLengthConstraints);
        self::assertInstanceOf(Length::class, $nestedEmbeddedMaxLengthConstraints[0]);
        self::assertSame(27, $nestedEmbeddedMaxLengthConstraints[0]->max);

        self::assertCount(0, $classMetadata->getPropertyMetadata('guidField'));
        self::assertCount(0, $classMetadata->getPropertyMetadata('simpleArrayField'));

        $textFieldMetadata = $classMetadata->getPropertyMetadata('textField');
        self::assertCount(1, $textFieldMetadata);
        $textFieldConstraints = $textFieldMetadata[0]->getConstraints();
        self::assertCount(1, $textFieldConstraints);
        self::assertInstanceOf(Length::class, $textFieldConstraints[0]);
        self::assertSame(1000, $textFieldConstraints[0]->max);

        /** @var PropertyMetadata[] $noAutoMappingMetadata */
        $noAutoMappingMetadata = $classMetadata->getPropertyMetadata('noAutoMapping');
        self::assertCount(1, $noAutoMappingMetadata);
        $noAutoMappingConstraints = $noAutoMappingMetadata[0]->getConstraints();
        self::assertCount(0, $noAutoMappingConstraints);
        self::assertSame(AutoMappingStrategy::DISABLED, $noAutoMappingMetadata[0]->getAutoMappingStrategy());
    }

    /**
     * @requires PHP 8.1
     */
    public function testExtractEnum()
    {
        if (!property_exists(Column::class, 'enumType')) {
            self::markTestSkipped('The "enumType" requires doctrine/orm 2.11.');
        }

        $validator = Validation::createValidatorBuilder()
            ->addMethodMapping('loadValidatorMetadata')
            ->enableAnnotationMapping(true)
            ->addDefaultDoctrineAnnotationReader()
            ->addLoader(new DoctrineLoader(DoctrineTestHelper::createTestEntityManager(), '{^Symfony\\\\Bridge\\\\Doctrine\\\\Tests\\\\Fixtures\\\\DoctrineLoader}'))
            ->getValidator()
        ;

        $classMetadata = $validator->getMetadataFor(new DoctrineLoaderEnum());

        $enumStringMetadata = $classMetadata->getPropertyMetadata('enumString');
        self::assertCount(0, $enumStringMetadata); // asserts the length constraint is not added to an enum

        $enumStringMetadata = $classMetadata->getPropertyMetadata('enumInt');
        self::assertCount(0, $enumStringMetadata); // asserts the length constraint is not added to an enum
    }

    public function testFieldMappingsConfiguration()
    {
        $validator = Validation::createValidatorBuilder()
            ->enableAnnotationMapping(true)
            ->addDefaultDoctrineAnnotationReader()
            ->addXmlMappings([__DIR__.'/../Resources/validator/BaseUser.xml'])
            ->addLoader(
                new DoctrineLoader(
                    DoctrineTestHelper::createTestEntityManager(
                        DoctrineTestHelper::createTestConfigurationWithXmlLoader()
                    ), '{}'
                )
            )
            ->getValidator();

        $classMetadata = $validator->getMetadataFor(new BaseUser(1, 'DemoUser'));

        $constraints = $classMetadata->getConstraints();
        self::assertCount(0, $constraints);
    }

    /**
     * @dataProvider regexpProvider
     */
    public function testClassValidator(bool $expected, string $classValidatorRegexp = null)
    {
        $doctrineLoader = new DoctrineLoader(DoctrineTestHelper::createTestEntityManager(), $classValidatorRegexp, false);

        $classMetadata = new ClassMetadata(DoctrineLoaderEntity::class);
        self::assertSame($expected, $doctrineLoader->loadClassMetadata($classMetadata));
    }

    public function regexpProvider()
    {
        return [
            [false, null],
            [true, '{.*}'],
            [true, '{^'.preg_quote(DoctrineLoaderEntity::class).'$|^'.preg_quote(Entity::class).'$}'],
            [false, '{^'.preg_quote(Entity::class).'$}'],
        ];
    }

    public function testClassNoAutoMapping()
    {
        $validator = Validation::createValidatorBuilder()
            ->enableAnnotationMapping(true)
            ->addDefaultDoctrineAnnotationReader()
            ->addLoader(new DoctrineLoader(DoctrineTestHelper::createTestEntityManager(), '{.*}'))
            ->getValidator();

        $classMetadata = $validator->getMetadataFor(new DoctrineLoaderNoAutoMappingEntity());

        $classConstraints = $classMetadata->getConstraints();
        self::assertCount(0, $classConstraints);
        self::assertSame(AutoMappingStrategy::DISABLED, $classMetadata->getAutoMappingStrategy());

        $maxLengthMetadata = $classMetadata->getPropertyMetadata('maxLength');
        self::assertEmpty($maxLengthMetadata);

        /** @var PropertyMetadata[] $autoMappingExplicitlyEnabledMetadata */
        $autoMappingExplicitlyEnabledMetadata = $classMetadata->getPropertyMetadata('autoMappingExplicitlyEnabled');
        self::assertCount(1, $autoMappingExplicitlyEnabledMetadata[0]->constraints);
        self::assertSame(AutoMappingStrategy::ENABLED, $autoMappingExplicitlyEnabledMetadata[0]->getAutoMappingStrategy());
    }
}
