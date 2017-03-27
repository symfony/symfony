<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyAccess\Tests\Mapping\Loader;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\AnnotationRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyAccess\Mapping\ClassMetadata;
use Symfony\Component\PropertyAccess\Mapping\Loader\AnnotationLoader;
use Symfony\Component\PropertyAccess\Tests\Mapping\TestClassMetadataFactory;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 * @author Luis Ramón López <lrlopez@gmail.com>
 */
class AnnotationLoaderTest extends TestCase
{
    /**
     * @var AnnotationLoader
     */
    private $loader;

    protected function setUp()
    {
        $this->loader = new AnnotationLoader(new AnnotationReader());
    }

    public function testInterface()
    {
        $this->assertInstanceOf('Symfony\Component\PropertyAccess\Mapping\Loader\LoaderInterface', $this->loader);
    }

    public function testLoadClassMetadataReturnsTrueIfSuccessful()
    {
        $classMetadata = new ClassMetadata('Symfony\Component\PropertyAccess\Tests\Fixtures\Dummy');
        AnnotationRegistry::registerAutoloadNamespace('Symfony\Component\PropertyAccess\Annotation', __DIR__.'/../../../../../..');
        $this->assertTrue($this->loader->loadClassMetadata($classMetadata));
    }

    public function testLoadMetadata()
    {
        $classMetadata = new ClassMetadata('Symfony\Component\PropertyAccess\Tests\Fixtures\Dummy');
        AnnotationRegistry::registerAutoloadNamespace('Symfony\Component\PropertyAccess\Annotation', __DIR__.'/../../../../../..');
        $this->loader->loadClassMetadata($classMetadata);

        $this->assertEquals(TestClassMetadataFactory::createClassMetadata(), $classMetadata);
    }

}
