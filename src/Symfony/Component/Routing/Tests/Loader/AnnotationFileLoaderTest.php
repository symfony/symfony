<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Tests\Loader;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Loader\AnnotationFileLoader;

class AnnotationFileLoaderTest extends AbstractAnnotationLoaderTest
{
    protected $loader;
    protected $reader;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reader = $this->getReader();
        $this->loader = new AnnotationFileLoader(new FileLocator(), $this->getClassLoader($this->reader));
    }

    public function testLoad()
    {
        $this->reader->expects($this->once())->method('getClassAnnotation');

        $this->loader->load(__DIR__.'/../Fixtures/AnnotatedClasses/FooClass.php');
    }

    public function testLoadTraitWithClassConstant()
    {
        $this->reader->expects($this->never())->method('getClassAnnotation');

        $this->loader->load(__DIR__.'/../Fixtures/AnnotatedClasses/FooTrait.php');
    }

    public function testLoadFileWithoutStartTag()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage('Did you forgot to add the "<?php" start tag at the beginning of the file?');
        $this->loader->load(__DIR__.'/../Fixtures/OtherAnnotatedClasses/NoStartTagClass.php');
    }

    public function testLoadVariadic()
    {
        $route = new Route(['path' => '/path/to/{id}']);
        $this->reader->expects($this->once())->method('getClassAnnotation');
        $this->reader->expects($this->once())->method('getMethodAnnotations')
            ->willReturn([$route]);

        $this->loader->load(__DIR__.'/../Fixtures/OtherAnnotatedClasses/VariadicClass.php');
    }

    /**
     * @requires PHP 7.0
     */
    public function testLoadAnonymousClass()
    {
        $this->reader->expects($this->never())->method('getClassAnnotation');
        $this->reader->expects($this->never())->method('getMethodAnnotations');

        $this->loader->load(__DIR__.'/../Fixtures/OtherAnnotatedClasses/AnonymousClassInTrait.php');
    }

    public function testLoadAbstractClass()
    {
        $this->reader->expects($this->never())->method('getClassAnnotation');
        $this->reader->expects($this->never())->method('getMethodAnnotations');

        $this->loader->load(__DIR__.'/../Fixtures/AnnotatedClasses/AbstractClass.php');
    }

    public function testSupports()
    {
        $fixture = __DIR__.'/../Fixtures/annotated.php';

        $this->assertTrue($this->loader->supports($fixture), '->supports() returns true if the resource is loadable');
        $this->assertFalse($this->loader->supports('foo.foo'), '->supports() returns true if the resource is loadable');

        $this->assertTrue($this->loader->supports($fixture, 'annotation'), '->supports() checks the resource type if specified');
        $this->assertFalse($this->loader->supports($fixture, 'foo'), '->supports() checks the resource type if specified');
    }
}
