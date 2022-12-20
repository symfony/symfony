<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Mapping\Factory;

use Doctrine\Common\Annotations\AnnotationReader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;
use Symfony\Component\Serializer\Mapping\Loader\AnnotationLoader;
use Symfony\Component\Serializer\Mapping\Loader\LoaderChain;
use Symfony\Component\Serializer\Tests\Mapping\TestClassMetadataFactory;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class ClassMetadataFactoryTest extends TestCase
{
    public function testInterface()
    {
        $classMetadata = new ClassMetadataFactory(new LoaderChain([]));
        self::assertInstanceOf(ClassMetadataFactoryInterface::class, $classMetadata);
    }

    public function testGetMetadataFor()
    {
        $factory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));
        $classMetadata = $factory->getMetadataFor('Symfony\Component\Serializer\Tests\Fixtures\Annotations\GroupDummy');

        self::assertEquals(TestClassMetadataFactory::createClassMetadata('Symfony\Component\Serializer\Tests\Fixtures\Annotations', true, true), $classMetadata);
    }

    public function testHasMetadataFor()
    {
        $factory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));
        self::assertTrue($factory->hasMetadataFor('Symfony\Component\Serializer\Tests\Fixtures\Annotations\GroupDummy'));
        self::assertTrue($factory->hasMetadataFor('Symfony\Component\Serializer\Tests\Fixtures\Annotations\GroupDummyParent'));
        self::assertTrue($factory->hasMetadataFor('Symfony\Component\Serializer\Tests\Fixtures\GroupDummyInterface'));
        self::assertFalse($factory->hasMetadataFor('Dunglas\Entity'));
    }
}
