<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Routing\Loader;

use Symfony\Component\Routing\Loader\AnnotationFileLoader;
use Symfony\Component\Config\FileLocator;

require_once __DIR__.'/AbstractAnnotationLoaderTest.php';

class AnnotationFileLoaderTest extends AbstractAnnotationLoaderTest
{
    protected $loader;
    protected $reader;

    public function setUp()
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

    /**
     * @covers Symfony\Component\Routing\Loader\AnnotationFileLoader::supports
     */
    public function testSupports()
    {
        $fixture = __DIR__.'/../Fixtures/annotated.php';

        $this->assertTrue($this->loader->supports($fixture), '->supports() returns true if the resource is loadable');
        $this->assertFalse($this->loader->supports('foo.foo'), '->supports() returns true if the resource is loadable');

        $this->assertTrue($this->loader->supports($fixture, 'annotation'), '->supports() checks the resource type if specified');
        $this->assertFalse($this->loader->supports($fixture, 'foo'), '->supports() checks the resource type if specified');
    }
}
