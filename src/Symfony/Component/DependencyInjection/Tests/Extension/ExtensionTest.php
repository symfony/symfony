<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Extension;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;

class ExtensionTest extends TestCase
{
    /**
     * @dataProvider getResolvedEnabledFixtures
     */
    public function testIsConfigEnabledReturnsTheResolvedValue($enabled)
    {
        $extension = new EnableableExtension();
        $this->assertSame($enabled, $extension->isConfigEnabled(new ContainerBuilder(), ['enabled' => $enabled]));
    }

    public function getResolvedEnabledFixtures()
    {
        return [
            [true],
            [false],
        ];
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\InvalidArgumentException
     * @expectedExceptionMessage The config array has no 'enabled' key.
     */
    public function testIsConfigEnabledOnNonEnableableConfig()
    {
        $extension = new EnableableExtension();

        $extension->isConfigEnabled(new ContainerBuilder(), []);
    }
}

class EnableableExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container)
    {
    }

    public function isConfigEnabled(ContainerBuilder $container, array $config)
    {
        return parent::isConfigEnabled($container, $config);
    }
}
