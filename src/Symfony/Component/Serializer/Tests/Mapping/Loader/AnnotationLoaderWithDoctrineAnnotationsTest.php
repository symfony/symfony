<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Mapping\Loader;

use Doctrine\Common\Annotations\AnnotationReader;
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Component\Serializer\Mapping\Loader\AnnotationLoader;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;

/**
 * @group legacy
 */
class AnnotationLoaderWithDoctrineAnnotationsTest extends AttributeLoaderTestCase
{
    use ExpectDeprecationTrait;

    protected function setUp(): void
    {
        $this->expectDeprecation('Since symfony/serializer 6.4: Passing a "Doctrine\Common\Annotations\AnnotationReader" instance as argument 1 to "Symfony\Component\Serializer\Mapping\Loader\AttributeLoader::__construct()" is deprecated, pass null or omit the parameter instead.');

        parent::setUp();
    }

    public function testLoadClassMetadataReturnsTrueIfSuccessful()
    {
        $this->expectDeprecation('Since symfony/serializer 6.4: Property "Symfony\Component\Serializer\Tests\Fixtures\Annotations\GroupDummy::$foo" uses Doctrine Annotations to configure serialization, which is deprecated. Use PHP attributes instead.');
        $this->expectDeprecation('Since symfony/serializer 6.4: Property "Symfony\Component\Serializer\Tests\Fixtures\Annotations\GroupDummy::$bar" uses Doctrine Annotations to configure serialization, which is deprecated. Use PHP attributes instead.');
        $this->expectDeprecation('Since symfony/serializer 6.4: Property "Symfony\Component\Serializer\Tests\Fixtures\Annotations\GroupDummy::$quux" uses Doctrine Annotations to configure serialization, which is deprecated. Use PHP attributes instead.');
        $this->expectDeprecation('Since symfony/serializer 6.4: Method "Symfony\Component\Serializer\Tests\Fixtures\Annotations\GroupDummy::setBar()" uses Doctrine Annotations to configure serialization, which is deprecated. Use PHP attributes instead.');
        $this->expectDeprecation('Since symfony/serializer 6.4: Method "Symfony\Component\Serializer\Tests\Fixtures\Annotations\GroupDummy::getBar()" uses Doctrine Annotations to configure serialization, which is deprecated. Use PHP attributes instead.');
        $this->expectDeprecation('Since symfony/serializer 6.4: Method "Symfony\Component\Serializer\Tests\Fixtures\Annotations\GroupDummy::isFooBar()" uses Doctrine Annotations to configure serialization, which is deprecated. Use PHP attributes instead.');

        parent::testLoadClassMetadataReturnsTrueIfSuccessful();
    }

    public function testLoadGroups()
    {
        $this->expectDeprecation('Since symfony/serializer 6.4: Property "Symfony\Component\Serializer\Tests\Fixtures\Annotations\GroupDummy::$foo" uses Doctrine Annotations to configure serialization, which is deprecated. Use PHP attributes instead.');
        $this->expectDeprecation('Since symfony/serializer 6.4: Property "Symfony\Component\Serializer\Tests\Fixtures\Annotations\GroupDummy::$bar" uses Doctrine Annotations to configure serialization, which is deprecated. Use PHP attributes instead.');
        $this->expectDeprecation('Since symfony/serializer 6.4: Property "Symfony\Component\Serializer\Tests\Fixtures\Annotations\GroupDummy::$quux" uses Doctrine Annotations to configure serialization, which is deprecated. Use PHP attributes instead.');
        $this->expectDeprecation('Since symfony/serializer 6.4: Method "Symfony\Component\Serializer\Tests\Fixtures\Annotations\GroupDummy::setBar()" uses Doctrine Annotations to configure serialization, which is deprecated. Use PHP attributes instead.');
        $this->expectDeprecation('Since symfony/serializer 6.4: Method "Symfony\Component\Serializer\Tests\Fixtures\Annotations\GroupDummy::getBar()" uses Doctrine Annotations to configure serialization, which is deprecated. Use PHP attributes instead.');
        $this->expectDeprecation('Since symfony/serializer 6.4: Method "Symfony\Component\Serializer\Tests\Fixtures\Annotations\GroupDummy::isFooBar()" uses Doctrine Annotations to configure serialization, which is deprecated. Use PHP attributes instead.');

        parent::testLoadGroups();
    }

    public function testLoadDiscriminatorMap()
    {
        $this->expectDeprecation('Since symfony/serializer 6.4: Class "Symfony\Component\Serializer\Tests\Fixtures\Annotations\AbstractDummy" uses Doctrine Annotations to configure serialization, which is deprecated. Use PHP attributes instead.');

        parent::testLoadDiscriminatorMap();
    }

    public function testLoadMaxDepth()
    {
        $this->expectDeprecation('Since symfony/serializer 6.4: Property "Symfony\Component\Serializer\Tests\Fixtures\Annotations\MaxDepthDummy::$foo" uses Doctrine Annotations to configure serialization, which is deprecated. Use PHP attributes instead.');
        $this->expectDeprecation('Since symfony/serializer 6.4: Method "Symfony\Component\Serializer\Tests\Fixtures\Annotations\MaxDepthDummy::getBar()" uses Doctrine Annotations to configure serialization, which is deprecated. Use PHP attributes instead.');

        parent::testLoadMaxDepth();
    }

    public function testLoadSerializedName()
    {
        $this->expectDeprecation('Since symfony/serializer 6.4: Property "Symfony\Component\Serializer\Tests\Fixtures\Annotations\SerializedNameDummy::$foo" uses Doctrine Annotations to configure serialization, which is deprecated. Use PHP attributes instead.');
        $this->expectDeprecation('Since symfony/serializer 6.4: Method "Symfony\Component\Serializer\Tests\Fixtures\Annotations\SerializedNameDummy::getBar()" uses Doctrine Annotations to configure serialization, which is deprecated. Use PHP attributes instead.');

        parent::testLoadSerializedName();
    }

    public function testLoadSerializedPath()
    {
        $this->expectDeprecation('Since symfony/serializer 6.4: Property "Symfony\Component\Serializer\Tests\Fixtures\Annotations\SerializedPathDummy::$three" uses Doctrine Annotations to configure serialization, which is deprecated. Use PHP attributes instead.');
        $this->expectDeprecation('Since symfony/serializer 6.4: Method "Symfony\Component\Serializer\Tests\Fixtures\Annotations\SerializedPathDummy::getSeven()" uses Doctrine Annotations to configure serialization, which is deprecated. Use PHP attributes instead.');

        parent::testLoadSerializedPath();
    }

    public function testLoadSerializedPathInConstructor()
    {
        $this->expectDeprecation('Since symfony/serializer 6.4: Property "Symfony\Component\Serializer\Tests\Fixtures\Annotations\SerializedPathInConstructorDummy::$three" uses Doctrine Annotations to configure serialization, which is deprecated. Use PHP attributes instead.');

        parent::testLoadSerializedPathInConstructor();
    }

    public function testLoadClassMetadataAndMerge()
    {
        $this->expectDeprecation('Since symfony/serializer 6.4: Property "Symfony\Component\Serializer\Tests\Fixtures\Annotations\GroupDummyParent::$kevin" uses Doctrine Annotations to configure serialization, which is deprecated. Use PHP attributes instead.');
        $this->expectDeprecation('Since symfony/serializer 6.4: Method "Symfony\Component\Serializer\Tests\Fixtures\Annotations\GroupDummyParent::getCoopTilleuls()" uses Doctrine Annotations to configure serialization, which is deprecated. Use PHP attributes instead.');
        $this->expectDeprecation('Since symfony/serializer 6.4: Property "Symfony\Component\Serializer\Tests\Fixtures\Annotations\GroupDummy::$foo" uses Doctrine Annotations to configure serialization, which is deprecated. Use PHP attributes instead.');
        $this->expectDeprecation('Since symfony/serializer 6.4: Property "Symfony\Component\Serializer\Tests\Fixtures\Annotations\GroupDummy::$bar" uses Doctrine Annotations to configure serialization, which is deprecated. Use PHP attributes instead.');
        $this->expectDeprecation('Since symfony/serializer 6.4: Property "Symfony\Component\Serializer\Tests\Fixtures\Annotations\GroupDummy::$quux" uses Doctrine Annotations to configure serialization, which is deprecated. Use PHP attributes instead.');
        $this->expectDeprecation('Since symfony/serializer 6.4: Method "Symfony\Component\Serializer\Tests\Fixtures\Annotations\GroupDummy::setBar()" uses Doctrine Annotations to configure serialization, which is deprecated. Use PHP attributes instead.');
        $this->expectDeprecation('Since symfony/serializer 6.4: Method "Symfony\Component\Serializer\Tests\Fixtures\Annotations\GroupDummy::getBar()" uses Doctrine Annotations to configure serialization, which is deprecated. Use PHP attributes instead.');
        $this->expectDeprecation('Since symfony/serializer 6.4: Method "Symfony\Component\Serializer\Tests\Fixtures\Annotations\GroupDummy::isFooBar()" uses Doctrine Annotations to configure serialization, which is deprecated. Use PHP attributes instead.');

        parent::testLoadClassMetadataAndMerge();
    }

    public function testLoadIgnore()
    {
        $this->expectDeprecation('Since symfony/serializer 6.4: Property "Symfony\Component\Serializer\Tests\Fixtures\Annotations\IgnoreDummy::$ignored1" uses Doctrine Annotations to configure serialization, which is deprecated. Use PHP attributes instead.');
        $this->expectDeprecation('Since symfony/serializer 6.4: Method "Symfony\Component\Serializer\Tests\Fixtures\Annotations\IgnoreDummy::getIgnored2()" uses Doctrine Annotations to configure serialization, which is deprecated. Use PHP attributes instead.');

        parent::testLoadIgnore();
    }

    public function testLoadContexts()
    {
        $this->expectDeprecation('Since symfony/serializer 6.4: Property "Symfony\Component\Serializer\Tests\Fixtures\Annotations\ContextDummyParent::$parentProperty" uses Doctrine Annotations to configure serialization, which is deprecated. Use PHP attributes instead.');
        $this->expectDeprecation('Since symfony/serializer 6.4: Property "Symfony\Component\Serializer\Tests\Fixtures\Annotations\ContextDummyParent::$overriddenParentProperty" uses Doctrine Annotations to configure serialization, which is deprecated. Use PHP attributes instead.');
        $this->expectDeprecation('Since symfony/serializer 6.4: Property "Symfony\Component\Serializer\Tests\Fixtures\Annotations\ContextDummy::$foo" uses Doctrine Annotations to configure serialization, which is deprecated. Use PHP attributes instead.');
        $this->expectDeprecation('Since symfony/serializer 6.4: Property "Symfony\Component\Serializer\Tests\Fixtures\Annotations\ContextDummy::$bar" uses Doctrine Annotations to configure serialization, which is deprecated. Use PHP attributes instead.');
        $this->expectDeprecation('Since symfony/serializer 6.4: Property "Symfony\Component\Serializer\Tests\Fixtures\Annotations\ContextDummy::$overriddenParentProperty" uses Doctrine Annotations to configure serialization, which is deprecated. Use PHP attributes instead.');
        $this->expectDeprecation('Since symfony/serializer 6.4: Method "Symfony\Component\Serializer\Tests\Fixtures\Annotations\ContextDummy::getMethodWithContext()" uses Doctrine Annotations to configure serialization, which is deprecated. Use PHP attributes instead.');

        parent::testLoadContexts();
    }

    public function testLoadContextsPropertiesPromoted()
    {
        $this->expectDeprecation('Since symfony/serializer 6.4: Property "Symfony\Component\Serializer\Tests\Fixtures\Annotations\ContextDummyParent::$parentProperty" uses Doctrine Annotations to configure serialization, which is deprecated. Use PHP attributes instead.');
        $this->expectDeprecation('Since symfony/serializer 6.4: Property "Symfony\Component\Serializer\Tests\Fixtures\Annotations\ContextDummyParent::$overriddenParentProperty" uses Doctrine Annotations to configure serialization, which is deprecated. Use PHP attributes instead.');
        $this->expectDeprecation('Since symfony/serializer 6.4: Property "Symfony\Component\Serializer\Tests\Fixtures\Annotations\ContextDummyPromotedProperties::$foo" uses Doctrine Annotations to configure serialization, which is deprecated. Use PHP attributes instead.');
        $this->expectDeprecation('Since symfony/serializer 6.4: Property "Symfony\Component\Serializer\Tests\Fixtures\Annotations\ContextDummyPromotedProperties::$bar" uses Doctrine Annotations to configure serialization, which is deprecated. Use PHP attributes instead.');
        $this->expectDeprecation('Since symfony/serializer 6.4: Property "Symfony\Component\Serializer\Tests\Fixtures\Annotations\ContextDummyPromotedProperties::$overriddenParentProperty" uses Doctrine Annotations to configure serialization, which is deprecated. Use PHP attributes instead.');
        $this->expectDeprecation('Since symfony/serializer 6.4: Method "Symfony\Component\Serializer\Tests\Fixtures\Annotations\ContextDummyPromotedProperties::getMethodWithContext()" uses Doctrine Annotations to configure serialization, which is deprecated. Use PHP attributes instead.');

        parent::testLoadContextsPropertiesPromoted();
    }

    public function testThrowsOnContextOnInvalidMethod()
    {
        $this->expectDeprecation('Since symfony/serializer 6.4: Method "Symfony\Component\Serializer\Tests\Fixtures\Annotations\BadMethodContextDummy::badMethod()" uses Doctrine Annotations to configure serialization, which is deprecated. Use PHP attributes instead.');

        parent::testThrowsOnContextOnInvalidMethod();
    }

    public function testCanHandleUnrelatedIgnoredMethods()
    {
        $this->expectDeprecation('Since symfony/serializer 6.4: Method "Symfony\Component\Serializer\Tests\Fixtures\Annotations\Entity45016::badIgnore()" uses Doctrine Annotations to configure serialization, which is deprecated. Use PHP attributes instead.');

        parent::testCanHandleUnrelatedIgnoredMethods();
    }

    public function testIgnoreGetterWithRequiredParameterIfIgnoreAnnotationIsUsed()
    {
        $this->expectDeprecation('Since symfony/serializer 6.4: Method "Symfony\Component\Serializer\Tests\Fixtures\Annotations\IgnoreDummyAdditionalGetter::getMyValue()" uses Doctrine Annotations to configure serialization, which is deprecated. Use PHP attributes instead.');

        parent::testIgnoreGetterWithRequiredParameterIfIgnoreAnnotationIsUsed();
    }

    protected function createLoader(): AttributeLoader
    {
        return new AnnotationLoader(new AnnotationReader());
    }

    protected function getNamespace(): string
    {
        return 'Symfony\Component\Serializer\Tests\Fixtures\Annotations';
    }
}
