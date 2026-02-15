<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Loader\Configurator;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

class ContainerConfiguratorTest extends TestCase
{
    public function testImportForwardsExcludeAndIgnoreErrors()
    {
        $container = new ContainerBuilder();

        $loader = $this->getMockBuilder(PhpFileLoader::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['setCurrentDir', 'import'])
            ->getMock();

        $path = '/path/file.php';
        $expectedDir = '/path';

        $resource = 'services/*.php';
        $type = null;
        $ignoreErrors = 'not_found';
        $exclude = ['services/excluded/*'];

        $loader->expects($this->once())
            ->method('setCurrentDir')
            ->with($this->equalTo($expectedDir));

        $loader->expects($this->once())
            ->method('import')
            ->with(
                $this->equalTo($resource),
                $this->equalTo($type),
                $this->equalTo($ignoreErrors),
                $this->equalTo($path),
                $this->equalTo($exclude)
            );

        $instanceof = [];
        $configurator = new ContainerConfigurator($container, $loader, $instanceof, $path, $path);
        $configurator->import($resource, $type, $ignoreErrors, $exclude);
    }
}
