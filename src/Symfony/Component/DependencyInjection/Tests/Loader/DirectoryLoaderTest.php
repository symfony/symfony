<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Loader;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\DirectoryLoader;
use Symfony\Component\DependencyInjection\Loader\IniFileLoader;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class DirectoryLoaderTest extends TestCase
{
    private static $fixturesPath;

    private $container;
    private $loader;

    public static function setUpBeforeClass(): void
    {
        self::$fixturesPath = realpath(__DIR__.'/../Fixtures/');
    }

    protected function setUp(): void
    {
        $locator = new FileLocator(self::$fixturesPath);
        $this->container = new ContainerBuilder();
        $this->loader = new DirectoryLoader($this->container, $locator);
        $resolver = new LoaderResolver([
            new PhpFileLoader($this->container, $locator),
            new IniFileLoader($this->container, $locator),
            new YamlFileLoader($this->container, $locator),
            $this->loader,
        ]);
        $this->loader->setResolver($resolver);
    }

    public function testDirectoryCanBeLoadedRecursively()
    {
        $this->loader->load('directory/');
        $this->assertEquals(['ini' => 'ini', 'yaml' => 'yaml', 'php' => 'php'], $this->container->getParameterBag()->all(), '->load() takes a single directory');
    }

    public function testImports()
    {
        $this->loader->resolve('directory/import/import.yml')->load('directory/import/import.yml');
        $this->assertEquals(['ini' => 'ini', 'yaml' => 'yaml'], $this->container->getParameterBag()->all(), '->load() takes a single file that imports a directory');
    }

    public function testExceptionIsRaisedWhenDirectoryDoesNotExist()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The file "foo" does not exist (in:');
        $this->loader->load('foo/');
    }

    public function testSupports()
    {
        $loader = new DirectoryLoader(new ContainerBuilder(), new FileLocator());

        $this->assertTrue($loader->supports('directory/'), '->supports("directory/") returns true');
        $this->assertTrue($loader->supports('directory/', 'directory'), '->supports("directory/", "directory") returns true');
        $this->assertFalse($loader->supports('directory'), '->supports("directory") returns false');
        $this->assertTrue($loader->supports('directory', 'directory'), '->supports("directory", "directory") returns true');
        $this->assertFalse($loader->supports('directory', 'foo'), '->supports("directory", "foo") returns false');
    }
}
