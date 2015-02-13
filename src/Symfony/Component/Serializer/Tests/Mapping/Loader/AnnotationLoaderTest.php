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
use Symfony\Component\Serializer\Mapping\ClassMetadata;
use Symfony\Component\Serializer\Mapping\Loader\AnnotationLoader;
use Symfony\Component\Serializer\Tests\Mapping\TestClassMetadataFactory;

require_once __DIR__.'/../../../Annotation/Groups.php';

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class AnnotationLoaderTest extends \PHPUnit_Framework_TestCase
{
    public function testLoadClassMetadataReturnsTrueIfSuccessful()
    {
        $loader = new AnnotationLoader(new AnnotationReader());
        $metadata = new ClassMetadata('Symfony\Component\Serializer\Tests\Fixtures\GroupDummy');

        $this->assertTrue($loader->loadClassMetadata($metadata));
    }

    public function testLoadClassMetadata()
    {
        $loader = new AnnotationLoader(new AnnotationReader());
        $metadata = new ClassMetadata('Symfony\Component\Serializer\Tests\Fixtures\GroupDummy');

        $loader->loadClassMetadata($metadata);

        $this->assertEquals(TestClassMetadataFactory::createClassMetadata(), $metadata);
    }

    public function testLoadClassMetadataAndMerge()
    {
        $loader = new AnnotationLoader(new AnnotationReader());
        $metadata = new ClassMetadata('Symfony\Component\Serializer\Tests\Fixtures\GroupDummy');
        $parentMetadata = new ClassMetadata('Symfony\Component\Serializer\Tests\Fixtures\GroupDummyParent');

        $loader->loadClassMetadata($parentMetadata);
        $metadata->mergeAttributesGroups($parentMetadata);

        $loader->loadClassMetadata($metadata);

        $this->assertEquals(TestClassMetadataFactory::createClassMetadata(true), $metadata);
    }
}
