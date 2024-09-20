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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\Serializer\Mapping\Loader\LoaderChain;
use Symfony\Component\Serializer\Tests\Fixtures\Attributes\GroupDummy;
use Symfony\Component\Serializer\Tests\Fixtures\Attributes\GroupDummyInterface;
use Symfony\Component\Serializer\Tests\Fixtures\Attributes\GroupDummyParent;
use Symfony\Component\Serializer\Tests\Mapping\TestClassMetadataFactory;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class ClassMetadataFactoryTest extends TestCase
{
    public function testInterface()
    {
        $classMetadata = new ClassMetadataFactory(new LoaderChain([]));
        $this->assertInstanceOf(ClassMetadataFactoryInterface::class, $classMetadata);
    }

    public function testGetMetadataFor()
    {
        $factory = new ClassMetadataFactory(new AttributeLoader());
        $classMetadata = $factory->getMetadataFor(GroupDummy::class);

        $this->assertEquals(TestClassMetadataFactory::createClassMetadata('Symfony\Component\Serializer\Tests\Fixtures\Attributes', true, true), $classMetadata);
    }

    public function testHasMetadataFor()
    {
        $factory = new ClassMetadataFactory(new AttributeLoader());
        $this->assertTrue($factory->hasMetadataFor(GroupDummy::class));
        $this->assertTrue($factory->hasMetadataFor(GroupDummyParent::class));
        $this->assertTrue($factory->hasMetadataFor(GroupDummyInterface::class));
        $this->assertFalse($factory->hasMetadataFor('Dunglas\Entity'));
    }
}
