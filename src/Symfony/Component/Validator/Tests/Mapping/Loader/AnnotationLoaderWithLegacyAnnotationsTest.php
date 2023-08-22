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
use Symfony\Component\Validator\Mapping\Loader\AnnotationLoader;

/**
 * @group legacy
 */
class AnnotationLoaderWithLegacyAnnotationsTest extends AttributeLoaderTest
{
    use ExpectDeprecationTrait;

    public function testLoadClassMetadataReturnsTrueIfSuccessful()
    {
        $this->expectDeprecation('Since symfony/validator 6.4: Class "Symfony\Component\Validator\Tests\Fixtures\Annotation\Entity" uses Doctrine Annotations to configure validation constraints, which is deprecated. Use PHP attributes instead.');
        $this->expectDeprecation('Since symfony/validator 6.4: Property "Symfony\Component\Validator\Tests\Fixtures\Annotation\Entity::$firstName" uses Doctrine Annotations to configure validation constraints, which is deprecated. Use PHP attributes instead.');
        $this->expectDeprecation('Since symfony/validator 6.4: Property "Symfony\Component\Validator\Tests\Fixtures\Annotation\Entity::$childA" uses Doctrine Annotations to configure validation constraints, which is deprecated. Use PHP attributes instead.');
        $this->expectDeprecation('Since symfony/validator 6.4: Property "Symfony\Component\Validator\Tests\Fixtures\Annotation\Entity::$childB" uses Doctrine Annotations to configure validation constraints, which is deprecated. Use PHP attributes instead.');
        $this->expectDeprecation('Since symfony/validator 6.4: Method "Symfony\Component\Validator\Tests\Fixtures\Annotation\Entity::getLastName()" uses Doctrine Annotations to configure validation constraints, which is deprecated. Use PHP attributes instead.');
        $this->expectDeprecation('Since symfony/validator 6.4: Method "Symfony\Component\Validator\Tests\Fixtures\Annotation\Entity::isValid()" uses Doctrine Annotations to configure validation constraints, which is deprecated. Use PHP attributes instead.');
        $this->expectDeprecation('Since symfony/validator 6.4: Method "Symfony\Component\Validator\Tests\Fixtures\Annotation\Entity::hasPermissions()" uses Doctrine Annotations to configure validation constraints, which is deprecated. Use PHP attributes instead.');
        $this->expectDeprecation('Since symfony/validator 6.4: Method "Symfony\Component\Validator\Tests\Fixtures\Annotation\Entity::validateMe()" uses Doctrine Annotations to configure validation constraints, which is deprecated. Use PHP attributes instead.');
        $this->expectDeprecation('Since symfony/validator 6.4: Method "Symfony\Component\Validator\Tests\Fixtures\Annotation\Entity::validateMeStatic()" uses Doctrine Annotations to configure validation constraints, which is deprecated. Use PHP attributes instead.');

        parent::testLoadClassMetadataReturnsTrueIfSuccessful();
    }

    public function testLoadClassMetadata()
    {
        $this->expectDeprecation('Since symfony/validator 6.4: Class "Symfony\Component\Validator\Tests\Fixtures\Annotation\Entity" uses Doctrine Annotations to configure validation constraints, which is deprecated. Use PHP attributes instead.');
        $this->expectDeprecation('Since symfony/validator 6.4: Property "Symfony\Component\Validator\Tests\Fixtures\Annotation\Entity::$firstName" uses Doctrine Annotations to configure validation constraints, which is deprecated. Use PHP attributes instead.');
        $this->expectDeprecation('Since symfony/validator 6.4: Property "Symfony\Component\Validator\Tests\Fixtures\Annotation\Entity::$childA" uses Doctrine Annotations to configure validation constraints, which is deprecated. Use PHP attributes instead.');
        $this->expectDeprecation('Since symfony/validator 6.4: Property "Symfony\Component\Validator\Tests\Fixtures\Annotation\Entity::$childB" uses Doctrine Annotations to configure validation constraints, which is deprecated. Use PHP attributes instead.');
        $this->expectDeprecation('Since symfony/validator 6.4: Method "Symfony\Component\Validator\Tests\Fixtures\Annotation\Entity::getLastName()" uses Doctrine Annotations to configure validation constraints, which is deprecated. Use PHP attributes instead.');
        $this->expectDeprecation('Since symfony/validator 6.4: Method "Symfony\Component\Validator\Tests\Fixtures\Annotation\Entity::isValid()" uses Doctrine Annotations to configure validation constraints, which is deprecated. Use PHP attributes instead.');
        $this->expectDeprecation('Since symfony/validator 6.4: Method "Symfony\Component\Validator\Tests\Fixtures\Annotation\Entity::hasPermissions()" uses Doctrine Annotations to configure validation constraints, which is deprecated. Use PHP attributes instead.');
        $this->expectDeprecation('Since symfony/validator 6.4: Method "Symfony\Component\Validator\Tests\Fixtures\Annotation\Entity::validateMe()" uses Doctrine Annotations to configure validation constraints, which is deprecated. Use PHP attributes instead.');
        $this->expectDeprecation('Since symfony/validator 6.4: Method "Symfony\Component\Validator\Tests\Fixtures\Annotation\Entity::validateMeStatic()" uses Doctrine Annotations to configure validation constraints, which is deprecated. Use PHP attributes instead.');

        parent::testLoadClassMetadata();
    }

    public function testLoadParentClassMetadata()
    {
        $this->expectDeprecation('Since symfony/validator 6.4: Property "Symfony\Component\Validator\Tests\Fixtures\Annotation\EntityParent::$other" uses Doctrine Annotations to configure validation constraints, which is deprecated. Use PHP attributes instead.');

        parent::testLoadParentClassMetadata();
    }

    public function testLoadClassMetadataAndMerge()
    {
        $this->expectDeprecation('Since symfony/validator 6.4: Property "Symfony\Component\Validator\Tests\Fixtures\Annotation\EntityParent::$other" uses Doctrine Annotations to configure validation constraints, which is deprecated. Use PHP attributes instead.');
        $this->expectDeprecation('Since symfony/validator 6.4: Class "Symfony\Component\Validator\Tests\Fixtures\Annotation\Entity" uses Doctrine Annotations to configure validation constraints, which is deprecated. Use PHP attributes instead.');
        $this->expectDeprecation('Since symfony/validator 6.4: Property "Symfony\Component\Validator\Tests\Fixtures\Annotation\Entity::$firstName" uses Doctrine Annotations to configure validation constraints, which is deprecated. Use PHP attributes instead.');
        $this->expectDeprecation('Since symfony/validator 6.4: Property "Symfony\Component\Validator\Tests\Fixtures\Annotation\Entity::$childA" uses Doctrine Annotations to configure validation constraints, which is deprecated. Use PHP attributes instead.');
        $this->expectDeprecation('Since symfony/validator 6.4: Property "Symfony\Component\Validator\Tests\Fixtures\Annotation\Entity::$childB" uses Doctrine Annotations to configure validation constraints, which is deprecated. Use PHP attributes instead.');
        $this->expectDeprecation('Since symfony/validator 6.4: Method "Symfony\Component\Validator\Tests\Fixtures\Annotation\Entity::getLastName()" uses Doctrine Annotations to configure validation constraints, which is deprecated. Use PHP attributes instead.');
        $this->expectDeprecation('Since symfony/validator 6.4: Method "Symfony\Component\Validator\Tests\Fixtures\Annotation\Entity::isValid()" uses Doctrine Annotations to configure validation constraints, which is deprecated. Use PHP attributes instead.');
        $this->expectDeprecation('Since symfony/validator 6.4: Method "Symfony\Component\Validator\Tests\Fixtures\Annotation\Entity::hasPermissions()" uses Doctrine Annotations to configure validation constraints, which is deprecated. Use PHP attributes instead.');
        $this->expectDeprecation('Since symfony/validator 6.4: Method "Symfony\Component\Validator\Tests\Fixtures\Annotation\Entity::validateMe()" uses Doctrine Annotations to configure validation constraints, which is deprecated. Use PHP attributes instead.');
        $this->expectDeprecation('Since symfony/validator 6.4: Method "Symfony\Component\Validator\Tests\Fixtures\Annotation\Entity::validateMeStatic()" uses Doctrine Annotations to configure validation constraints, which is deprecated. Use PHP attributes instead.');

        parent::testLoadClassMetadataAndMerge();
    }

    public function testLoadGroupSequenceProviderAnnotation()
    {
        $this->expectDeprecation('Since symfony/validator 6.4: Class "Symfony\Component\Validator\Tests\Fixtures\Annotation\GroupSequenceProviderEntity" uses Doctrine Annotations to configure validation constraints, which is deprecated. Use PHP attributes instead.');

        parent::testLoadGroupSequenceProviderAnnotation();
    }

    protected function createAnnotationLoader(): AnnotationLoader
    {
        return new AnnotationLoader(new AnnotationReader());
    }

    protected function getFixtureNamespace(): string
    {
        return 'Symfony\Component\Validator\Tests\Fixtures\Annotation';
    }
}
