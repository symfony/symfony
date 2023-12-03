<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Mapping\Loader;

use Doctrine\Common\Annotations\AnnotationReader;
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Loader\AnnotationLoader;

/**
 * @group legacy
 */
class AnnotationLoaderWithHybridAnnotationsTest extends AttributeLoaderTest
{
    use ExpectDeprecationTrait;

    public function testLoadClassMetadataReturnsTrueIfSuccessful()
    {
        $this->expectDeprecation('Since symfony/validator 6.4: Class "Symfony\Component\Validator\Tests\Fixtures\Attribute\Entity" uses Doctrine Annotations to configure validation constraints, which is deprecated. Use PHP attributes instead.');
        $this->expectDeprecation('Since symfony/validator 6.4: Property "Symfony\Component\Validator\Tests\Fixtures\Attribute\Entity::$firstName" uses Doctrine Annotations to configure validation constraints, which is deprecated. Use PHP attributes instead.');

        parent::testLoadClassMetadataReturnsTrueIfSuccessful();
    }

    public function testLoadClassMetadata()
    {
        $this->expectDeprecation('Since symfony/validator 6.4: Class "Symfony\Component\Validator\Tests\Fixtures\Attribute\Entity" uses Doctrine Annotations to configure validation constraints, which is deprecated. Use PHP attributes instead.');
        $this->expectDeprecation('Since symfony/validator 6.4: Property "Symfony\Component\Validator\Tests\Fixtures\Attribute\Entity::$firstName" uses Doctrine Annotations to configure validation constraints, which is deprecated. Use PHP attributes instead.');

        parent::testLoadClassMetadata();
    }

    public function testLoadClassMetadataAndMerge()
    {
        $this->expectDeprecation('Since symfony/validator 6.4: Class "Symfony\Component\Validator\Tests\Fixtures\Attribute\Entity" uses Doctrine Annotations to configure validation constraints, which is deprecated. Use PHP attributes instead.');
        $this->expectDeprecation('Since symfony/validator 6.4: Property "Symfony\Component\Validator\Tests\Fixtures\Attribute\Entity::$firstName" uses Doctrine Annotations to configure validation constraints, which is deprecated. Use PHP attributes instead.');

        parent::testLoadClassMetadataAndMerge();
    }

    public function testLoadClassMetadataWithOtherAnnotations()
    {
        $loader = $this->createAnnotationLoader();
        $metadata = new ClassMetadata(EntityWithOtherAnnotations::class);

        $this->assertTrue($loader->loadClassMetadata($metadata));
    }

    protected function createAnnotationLoader(): AnnotationLoader
    {
        return new AnnotationLoader(new AnnotationReader());
    }

    protected function getFixtureNamespace(): string
    {
        return 'Symfony\Component\Validator\Tests\Fixtures\Attribute';
    }
}

/**
 * @Annotation
 * @Target({"PROPERTY"})
 */
class SomeAnnotation
{
}

class EntityWithOtherAnnotations
{
    /**
     * @SomeAnnotation
     */
    #[NotBlank]
    public ?string $name = null;
}
