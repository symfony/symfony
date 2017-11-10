<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyAccess\Tests\Mapping\Factory;

use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyAccess\Mapping\ClassMetadata;
use Symfony\Component\PropertyAccess\Mapping\Factory\LazyLoadingMetadataFactory;
use Symfony\Component\PropertyAccess\Mapping\Loader\LoaderInterface;
use Symfony\Component\PropertyAccess\Mapping\PropertyMetadata;

class LazyLoadingMetadataFactoryTest extends TestCase
{
    const CLASSNAME = 'Symfony\Component\PropertyAccess\Tests\Fixtures\Dummy';
    const PARENTCLASS = 'Symfony\Component\PropertyAccess\Tests\Fixtures\DummyParent';

    public function testLoadClassMetadata()
    {
        $factory = new LazyLoadingMetadataFactory(new TestLoader());
        $metadata = $factory->getMetadataFor(self::PARENTCLASS);

        $properties = array(
            self::PARENTCLASS => new PropertyMetadata(self::PARENTCLASS),
        );

        $this->assertEquals($properties, $metadata->getPropertyMetadataCollection());
    }

    public function testMergeParentMetadata()
    {
        $factory = new LazyLoadingMetadataFactory(new TestLoader());
        $metadata = $factory->getMetadataFor(self::CLASSNAME);

        $properties = array(
            self::PARENTCLASS => new PropertyMetadata(self::PARENTCLASS),
            self::CLASSNAME => new PropertyMetadata(self::CLASSNAME),
        );

        $this->assertEquals($properties, $metadata->getPropertyMetadataCollection());
    }
}

class TestLoader implements LoaderInterface
{
    public function loadClassMetadata(ClassMetadata $metadata)
    {
        $metadata->addPropertyMetadata(new PropertyMetadata($metadata->getName()));
    }
}
