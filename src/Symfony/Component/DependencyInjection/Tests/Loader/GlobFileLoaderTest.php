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
use Symfony\Component\Config\Resource\GlobResource;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\GlobFileLoader;
use Symfony\Component\Config\FileLocator;

class GlobFileLoaderTest extends TestCase
{
    public function testSupports()
    {
        $loader = new GlobFileLoader(new ContainerBuilder(), new FileLocator());

        $this->assertTrue($loader->supports('any-path', 'glob'), '->supports() returns true if the resource has the glob type');
        $this->assertFalse($loader->supports('any-path'), '->supports() returns false if the resource is not of glob type');
    }

    public function testLoadAddsTheGlobResourceToTheContainer()
    {
        $loader = new GlobFileLoaderWithoutImport($container = new ContainerBuilder(), new FileLocator());
        $loader->load(__DIR__.'/../Fixtures/config/*');

        $this->assertEquals(new GlobResource(__DIR__.'/../Fixtures/config', '/*', false), $container->getResources()[1]);
    }
}

class GlobFileLoaderWithoutImport extends GlobFileLoader
{
    public function import($resource, $type = null, $ignoreErrors = false, $sourceResource = null)
    {
    }
}
