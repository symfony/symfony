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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Routing\Loader\AnnotationDirectoryLoader;
use Symfony\Component\Routing\Tests\Fixtures\AnnotatedClasses\BarClass;
use Symfony\Component\Routing\Tests\Fixtures\AnnotatedClasses\BazClass;
use Symfony\Component\Routing\Tests\Fixtures\AnnotatedClasses\EncodingClass;
use Symfony\Component\Routing\Tests\Fixtures\AnnotatedClasses\FooClass;
use Symfony\Component\Routing\Tests\Fixtures\TraceableAnnotationClassLoader;

class AnnotationDirectoryLoaderTest extends TestCase
{
    private AnnotationDirectoryLoader $loader;
    private TraceableAnnotationClassLoader $classLoader;

    protected function setUp(): void
    {
        parent::setUp();

        $this->classLoader = new TraceableAnnotationClassLoader();
        $this->loader = new AnnotationDirectoryLoader(new FileLocator(), $this->classLoader);
    }

    public function testLoad()
    {
        $this->loader->load(__DIR__.'/../Fixtures/AnnotatedClasses');

        self::assertSame([
            BarClass::class,
            BazClass::class,
            EncodingClass::class,
            FooClass::class,
        ], $this->classLoader->foundClasses);
    }

    public function testSupports()
    {
        $fixturesDir = __DIR__.'/../Fixtures';

        $this->assertTrue($this->loader->supports($fixturesDir), '->supports() returns true if the resource is loadable');
        $this->assertFalse($this->loader->supports('foo.foo'), '->supports() returns true if the resource is loadable');

        $this->assertTrue($this->loader->supports($fixturesDir, 'annotation'), '->supports() checks the resource type if specified');
        $this->assertTrue($this->loader->supports($fixturesDir, 'attribute'), '->supports() checks the resource type if specified');
        $this->assertFalse($this->loader->supports($fixturesDir, 'foo'), '->supports() checks the resource type if specified');
    }

    public function testItSupportsAnyAnnotation()
    {
        $this->assertTrue($this->loader->supports(__DIR__.'/../Fixtures/even-with-not-existing-folder', 'annotation'));
    }

    public function testLoadFileIfLocatedResourceIsFile()
    {
        $this->loader->load(__DIR__.'/../Fixtures/AnnotatedClasses/FooClass.php');
        self::assertSame([FooClass::class], $this->classLoader->foundClasses);
    }

    public function testLoadAbstractClass()
    {
        self::assertNull($this->loader->load(__DIR__.'/../Fixtures/AnnotatedClasses/AbstractClass.php'));
        self::assertSame([], $this->classLoader->foundClasses);
    }
}
