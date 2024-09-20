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
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\Routing\Loader\AttributeFileLoader;
use Symfony\Component\Routing\Loader\DirectoryLoader;
use Symfony\Component\Routing\Loader\YamlFileLoader;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Tests\Fixtures\TraceableAttributeClassLoader;

class DirectoryLoaderTest extends TestCase
{
    private DirectoryLoader $loader;

    protected function setUp(): void
    {
        parent::setUp();

        $locator = new FileLocator();
        $this->loader = new DirectoryLoader($locator);
        $resolver = new LoaderResolver([
            new YamlFileLoader($locator),
            new AttributeFileLoader($locator, new TraceableAttributeClassLoader()),
            $this->loader,
        ]);
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
        $this->assertContainsOnly('Symfony\Component\Routing\Route', $routes);

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
