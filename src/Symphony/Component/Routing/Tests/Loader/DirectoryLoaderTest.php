<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Routing\Tests\Loader;

use Symphony\Component\Routing\Loader\DirectoryLoader;
use Symphony\Component\Routing\Loader\YamlFileLoader;
use Symphony\Component\Routing\Loader\AnnotationFileLoader;
use Symphony\Component\Config\Loader\LoaderResolver;
use Symphony\Component\Config\FileLocator;
use Symphony\Component\Routing\RouteCollection;

class DirectoryLoaderTest extends AbstractAnnotationLoaderTest
{
    private $loader;
    private $reader;

    protected function setUp()
    {
        parent::setUp();

        $locator = new FileLocator();
        $this->reader = $this->getReader();
        $this->loader = new DirectoryLoader($locator);
        $resolver = new LoaderResolver(array(
            new YamlFileLoader($locator),
            new AnnotationFileLoader($locator, $this->getClassLoader($this->reader)),
            $this->loader,
        ));
        $this->loader->setResolver($resolver);
    }

    public function testLoadDirectory()
    {
        $collection = $this->loader->load(__DIR__.'/../Fixtures/directory', 'directory');
        $this->verifyCollection($collection);
    }

    public function testImportDirectory()
    {
        $collection = $this->loader->load(__DIR__.'/../Fixtures/directory_import', 'directory');
        $this->verifyCollection($collection);
    }

    private function verifyCollection(RouteCollection $collection)
    {
        $routes = $collection->all();

        $this->assertCount(3, $routes, 'Three routes are loaded');
        $this->assertContainsOnly('Symphony\Component\Routing\Route', $routes);

        for ($i = 1; $i <= 3; ++$i) {
            $this->assertSame('/route/'.$i, $routes['route'.$i]->getPath());
        }
    }

    public function testSupports()
    {
        $fixturesDir = __DIR__.'/../Fixtures';

        $this->assertFalse($this->loader->supports($fixturesDir), '->supports(*) returns false');

        $this->assertTrue($this->loader->supports($fixturesDir, 'directory'), '->supports(*, "directory") returns true');
        $this->assertFalse($this->loader->supports($fixturesDir, 'foo'), '->supports(*, "foo") returns false');
    }
}
