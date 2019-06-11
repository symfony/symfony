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

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\Test\DoctrineTestHelper;
use Symfony\Bridge\Doctrine\Tests\Fixtures\BaseUser;
use Symfony\Bridge\Doctrine\Tests\Fixtures\DoctrineLoaderEmbed;
use Symfony\Bridge\Doctrine\Tests\Fixtures\DoctrineLoaderEntity;
use Symfony\Bridge\Doctrine\Tests\Fixtures\DoctrineLoaderNestedEmbed;
use Symfony\Bridge\Doctrine\Tests\Fixtures\DoctrineLoaderParentEntity;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Bridge\Doctrine\Validator\DoctrineLoader;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Mapping\CascadingStrategy;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\TraversalStrategy;
use Symfony\Component\Validator\Tests\Fixtures\Entity;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\ValidatorBuilder;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class DoctrineLoaderTest extends TestCase
{
    public function testLoadClassMetadata()
    {
        if (!method_exists(ValidatorBuilder::class, 'addLoader')) {
            $this->markTestSkipped('Auto-mapping requires symfony/validation 4.2+');
        }

        $validator = Validation::createValidatorBuilder()
            ->enableAnnotationMapping()
            ->addLoader(new DoctrineLoader(DoctrineTestHelper::createTestEntityManager(), '{^Symfony\\\\Bridge\\\\Doctrine\\\\Tests\\\\Fixtures\\\\DoctrineLoader}'))
            ->getValidator()
        ;

        $classMetadata = $validator->getMetadataFor(new DoctrineLoaderEntity());

        $classConstraints = $classMetadata->getConstraints();
        $this->assertCount(2, $classConstraints);
        $this->assertInstanceOf(UniqueEntity::class, $classConstraints[0]);
        $this->assertInstanceOf(UniqueEntity::class, $classConstraints[1]);
        $this->assertSame(['alreadyMappedUnique'], $classConstraints[0]->fields);
        $this->assertSame('unique', $classConstraints[1]->fields);

        $maxLengthMetadata = $classMetadata->getPropertyMetadata('maxLength');
        $this->assertCount(1, $maxLengthMetadata);
        $maxLengthConstraints = $maxLengthMetadata[0]->getConstraints();
        $this->assertCount(1, $maxLengthConstraints);
        $this->assertInstanceOf(Length::class, $maxLengthConstraints[0]);
        $this->assertSame(20, $maxLengthConstraints[0]->max);

        $mergedMaxLengthMetadata = $classMetadata->getPropertyMetadata('mergedMaxLength');
        $this->assertCount(1, $mergedMaxLengthMetadata);
        $mergedMaxLengthConstraints = $mergedMaxLengthMetadata[0]->getConstraints();
        $this->assertCount(1, $mergedMaxLengthConstraints);
        $this->assertInstanceOf(Length::class, $mergedMaxLengthConstraints[0]);
        $this->assertSame(20, $mergedMaxLengthConstraints[0]->max);
        $this->assertSame(5, $mergedMaxLengthConstraints[0]->min);

        $alreadyMappedMaxLengthMetadata = $classMetadata->getPropertyMetadata('alreadyMappedMaxLength');
        $this->assertCount(1, $alreadyMappedMaxLengthMetadata);
        $alreadyMappedMaxLengthConstraints = $alreadyMappedMaxLengthMetadata[0]->getConstraints();
        $this->assertCount(1, $alreadyMappedMaxLengthConstraints);
        $this->assertInstanceOf(Length::class, $alreadyMappedMaxLengthConstraints[0]);
        $this->assertSame(10, $alreadyMappedMaxLengthConstraints[0]->max);
        $this->assertSame(1, $alreadyMappedMaxLengthConstraints[0]->min);

        $publicParentMaxLengthMetadata = $classMetadata->getPropertyMetadata('publicParentMaxLength');
        $this->assertCount(1, $publicParentMaxLengthMetadata);
        $publicParentMaxLengthConstraints = $publicParentMaxLengthMetadata[0]->getConstraints();
        $this->assertCount(1, $publicParentMaxLengthConstraints);
        $this->assertInstanceOf(Length::class, $publicParentMaxLengthConstraints[0]);
        $this->assertSame(35, $publicParentMaxLengthConstraints[0]->max);

        $embeddedMetadata = $classMetadata->getPropertyMetadata('embedded');
        $this->assertCount(1, $embeddedMetadata);
        $this->assertSame(CascadingStrategy::CASCADE, $embeddedMetadata[0]->getCascadingStrategy());
        $this->assertSame(TraversalStrategy::IMPLICIT, $embeddedMetadata[0]->getTraversalStrategy());

        $parentClassMetadata = $validator->getMetadataFor(new DoctrineLoaderParentEntity());

        $publicParentMaxLengthMetadata = $parentClassMetadata->getPropertyMetadata('publicParentMaxLength');
        $this->assertCount(0, $publicParentMaxLengthMetadata);

        $privateParentMaxLengthMetadata = $parentClassMetadata->getPropertyMetadata('privateParentMaxLength');
        $this->assertCount(1, $privateParentMaxLengthMetadata);
        $privateParentMaxLengthConstraints = $privateParentMaxLengthMetadata[0]->getConstraints();
        $this->assertCount(1, $privateParentMaxLengthConstraints);
        $this->assertInstanceOf(Length::class, $privateParentMaxLengthConstraints[0]);
        $this->assertSame(30, $privateParentMaxLengthConstraints[0]->max);

        $embeddedClassMetadata = $validator->getMetadataFor(new DoctrineLoaderEmbed());

        $embeddedMaxLengthMetadata = $embeddedClassMetadata->getPropertyMetadata('embeddedMaxLength');
        $this->assertCount(1, $embeddedMaxLengthMetadata);
        $embeddedMaxLengthConstraints = $embeddedMaxLengthMetadata[0]->getConstraints();
        $this->assertCount(1, $embeddedMaxLengthConstraints);
        $this->assertInstanceOf(Length::class, $embeddedMaxLengthConstraints[0]);
        $this->assertSame(25, $embeddedMaxLengthConstraints[0]->max);

        $nestedEmbeddedMetadata = $embeddedClassMetadata->getPropertyMetadata('nestedEmbedded');
        $this->assertCount(1, $nestedEmbeddedMetadata);
        $this->assertSame(CascadingStrategy::CASCADE, $nestedEmbeddedMetadata[0]->getCascadingStrategy());
        $this->assertSame(TraversalStrategy::IMPLICIT, $nestedEmbeddedMetadata[0]->getTraversalStrategy());

        $nestedEmbeddedClassMetadata = $validator->getMetadataFor(new DoctrineLoaderNestedEmbed());

        $nestedEmbeddedMaxLengthMetadata = $nestedEmbeddedClassMetadata->getPropertyMetadata('nestedEmbeddedMaxLength');
        $this->assertCount(1, $nestedEmbeddedMaxLengthMetadata);
        $nestedEmbeddedMaxLengthConstraints = $nestedEmbeddedMaxLengthMetadata[0]->getConstraints();
        $this->assertCount(1, $nestedEmbeddedMaxLengthConstraints);
        $this->assertInstanceOf(Length::class, $nestedEmbeddedMaxLengthConstraints[0]);
        $this->assertSame(27, $nestedEmbeddedMaxLengthConstraints[0]->max);

        $this->assertCount(0, $classMetadata->getPropertyMetadata('guidField'));
        $this->assertCount(0, $classMetadata->getPropertyMetadata('simpleArrayField'));

        $textFieldMetadata = $classMetadata->getPropertyMetadata('textField');
        $this->assertCount(1, $textFieldMetadata);
        $textFieldConstraints = $textFieldMetadata[0]->getConstraints();
        $this->assertCount(1, $textFieldConstraints);
        $this->assertInstanceOf(Length::class, $textFieldConstraints[0]);
        $this->assertSame(1000, $textFieldConstraints[0]->max);
    }

    public function testFieldMappingsConfiguration()
    {
        if (!method_exists(ValidatorBuilder::class, 'addLoader')) {
            $this->markTestSkipped('Auto-mapping requires symfony/validation 4.2+');
        }

        $validator = Validation::createValidatorBuilder()
            ->enableAnnotationMapping()
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
        $this->assertCount(0, $constraints);
    }

    /**
     * @dataProvider regexpProvider
     */
    public function testClassValidator(bool $expected, string $classValidatorRegexp = null)
    {
        $doctrineLoader = new DoctrineLoader(DoctrineTestHelper::createTestEntityManager(), $classValidatorRegexp);

        $classMetadata = new ClassMetadata(DoctrineLoaderEntity::class);
        $this->assertSame($expected, $doctrineLoader->loadClassMetadata($classMetadata));
    }

    public function regexpProvider()
    {
        return [
            [false, null],
            [true, '{^'.preg_quote(DoctrineLoaderEntity::class).'$|^'.preg_quote(Entity::class).'$}'],
            [false, '{^'.preg_quote(Entity::class).'$}'],
        ];
    }
}
