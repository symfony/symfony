<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Serializer\Tests\Mapping\Factory;

use Doctrine\Common\Annotations\AnnotationReader;
use PHPUnit\Framework\TestCase;
use Symphony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symphony\Component\Serializer\Mapping\Loader\AnnotationLoader;
use Symphony\Component\Serializer\Mapping\Loader\LoaderChain;
use Symphony\Component\Serializer\Tests\Mapping\TestClassMetadataFactory;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class ClassMetadataFactoryTest extends TestCase
{
    public function testInterface()
    {
        $classMetadata = new ClassMetadataFactory(new LoaderChain(array()));
        $this->assertInstanceOf('Symphony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface', $classMetadata);
    }

    public function testGetMetadataFor()
    {
        $factory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));
        $classMetadata = $factory->getMetadataFor('Symphony\Component\Serializer\Tests\Fixtures\GroupDummy');

        $this->assertEquals(TestClassMetadataFactory::createClassMetadata(true, true), $classMetadata);
    }

    public function testHasMetadataFor()
    {
        $factory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));
        $this->assertTrue($factory->hasMetadataFor('Symphony\Component\Serializer\Tests\Fixtures\GroupDummy'));
        $this->assertTrue($factory->hasMetadataFor('Symphony\Component\Serializer\Tests\Fixtures\GroupDummyParent'));
        $this->assertTrue($factory->hasMetadataFor('Symphony\Component\Serializer\Tests\Fixtures\GroupDummyInterface'));
        $this->assertFalse($factory->hasMetadataFor('Dunglas\Entity'));
    }
}
