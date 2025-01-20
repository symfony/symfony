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
use Symfony\Component\Routing\Loader\AttributeDirectoryLoader;
use Symfony\Component\Routing\Tests\Fixtures\AttributedClasses\BarClass;
use Symfony\Component\Routing\Tests\Fixtures\AttributedClasses\BazClass;
use Symfony\Component\Routing\Tests\Fixtures\AttributedClasses\EncodingClass;
use Symfony\Component\Routing\Tests\Fixtures\AttributedClasses\FooClass;
use Symfony\Component\Routing\Tests\Fixtures\TraceableAttributeClassLoader;

class AttributeDirectoryLoaderTest extends TestCase
{
    private AttributeDirectoryLoader $loader;
    private TraceableAttributeClassLoader $classLoader;

    protected function setUp(): void
    {
        $this->classLoader = new TraceableAttributeClassLoader();
        $this->loader = new AttributeDirectoryLoader(new FileLocator(), $this->classLoader);
    }

    public function testLoad()
    {
        $this->loader->load(__DIR__.'/../Fixtures/AttributedClasses');

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

        $this->assertTrue($this->loader->supports($fixturesDir, 'attribute'), '->supports() checks the resource type if specified');
        $this->assertFalse($this->loader->supports($fixturesDir, 'foo'), '->supports() checks the resource type if specified');
    }

    public function testItSupportsAnyAttribute()
    {
        $this->assertTrue($this->loader->supports(__DIR__.'/../Fixtures/even-with-not-existing-folder', 'attribute'));
    }

    public function testLoadFileIfLocatedResourceIsFile()
    {
        $this->loader->load(__DIR__.'/../Fixtures/AttributedClasses/FooClass.php');
        self::assertSame([FooClass::class], $this->classLoader->foundClasses);
    }

    public function testLoadAbstractClass()
    {
        self::assertNull($this->loader->load(__DIR__.'/../Fixtures/AttributedClasses/AbstractClass.php'));
        self::assertSame([], $this->classLoader->foundClasses);
    }
}
