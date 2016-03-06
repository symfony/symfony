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

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Symfony\Component\PropertyAccess\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\PropertyAccess\Mapping\Loader\AnnotationLoader;
use Symfony\Component\PropertyAccess\Mapping\Loader\LoaderChain;
use Symfony\Component\PropertyAccess\Tests\Mapping\TestClassMetadataFactory;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class ClassMetadataFactoryTest extends \PHPUnit_Framework_TestCase
{
    public function testInterface()
    {
        $classMetadata = new ClassMetadataFactory(new LoaderChain(array()));
        $this->assertInstanceOf('Symfony\Component\PropertyAccess\Mapping\Factory\ClassMetadataFactoryInterface', $classMetadata);
    }

    public function testGetMetadataFor()
    {
        AnnotationRegistry::registerAutoloadNamespace('Symfony\Component\PropertyAccess\Annotation', __DIR__.'/../../../../../..');
        $factory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));
        $classMetadata = $factory->getMetadataFor('Symfony\Component\PropertyAccess\Tests\Fixtures\Dummy');

        $this->assertEquals(TestClassMetadataFactory::createClassMetadata(), $classMetadata);
    }

    public function testHasMetadataFor()
    {
        $factory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));
        $this->assertTrue($factory->hasMetadataFor('Symfony\Component\PropertyAccess\Tests\Fixtures\Dummy'));
        $this->assertFalse($factory->hasMetadataFor('Dunglas\Entity'));
    }

    /**
     * @group legacy
     */
    public function testCacheExists()
    {
        $cache = $this->getMock('Doctrine\Common\Cache\Cache');
        $cache
            ->expects($this->once())
            ->method('fetch')
            ->will($this->returnValue('foo'))
        ;
        AnnotationRegistry::registerAutoloadNamespace('Symfony\Component\PropertyAccess\Annotation', __DIR__.'/../../../../../..');
        $factory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()), $cache);
        $this->assertEquals('foo', $factory->getMetadataFor('Symfony\Component\PropertyAccess\Tests\Fixtures\Dummy'));
    }

    /**
     * @group legacy
     */
    public function testCacheNotExists()
    {
        $cache = $this->getMock('Doctrine\Common\Cache\Cache');
        $cache->method('fetch')->will($this->returnValue(false));
        $cache->method('save');

        AnnotationRegistry::registerAutoloadNamespace('Symfony\Component\PropertyAccess\Annotation', __DIR__.'/../../../../../..');
        $factory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()), $cache);
        $metadata = $factory->getMetadataFor('Symfony\Component\PropertyAccess\Tests\Fixtures\Dummy');

        $this->assertEquals(TestClassMetadataFactory::createClassMetadata(), $metadata);
    }
}
