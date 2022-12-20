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
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Tests\Fixtures\Extension\InvalidConfig\InvalidConfigExtension;
use Symfony\Component\DependencyInjection\Tests\Fixtures\Extension\SemiValidConfig\SemiValidConfigExtension;
use Symfony\Component\DependencyInjection\Tests\Fixtures\Extension\ValidConfig\Configuration;
use Symfony\Component\DependencyInjection\Tests\Fixtures\Extension\ValidConfig\ValidConfigExtension;

class ExtensionTest extends TestCase
{
    /**
     * @dataProvider getResolvedEnabledFixtures
     */
    public function testIsConfigEnabledReturnsTheResolvedValue($enabled)
    {
        $extension = new EnableableExtension();
        self::assertSame($enabled, $extension->isConfigEnabled(new ContainerBuilder(), ['enabled' => $enabled]));
    }

    public function getResolvedEnabledFixtures()
    {
        return [
            [true],
            [false],
        ];
    }

    public function testIsConfigEnabledOnNonEnableableConfig()
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('The config array has no \'enabled\' key.');
        $extension = new EnableableExtension();

        $extension->isConfigEnabled(new ContainerBuilder(), []);
    }

    public function testNoConfiguration()
    {
        $extension = new EnableableExtension();

        self::assertNull($extension->getConfiguration([], new ContainerBuilder()));
    }

    public function testValidConfiguration()
    {
        $extension = new ValidConfigExtension();

        self::assertInstanceOf(Configuration::class, $extension->getConfiguration([], new ContainerBuilder()));
    }

    public function testSemiValidConfiguration()
    {
        $extension = new SemiValidConfigExtension();

        self::assertNull($extension->getConfiguration([], new ContainerBuilder()));
    }

    public function testInvalidConfiguration()
    {
        self::expectException(LogicException::class);
        self::expectExceptionMessage('The extension configuration class "Symfony\\Component\\DependencyInjection\\Tests\\Fixtures\\Extension\\InvalidConfig\\Configuration" must implement "Symfony\\Component\\Config\\Definition\\ConfigurationInterface".');

        $extension = new InvalidConfigExtension();

        $extension->getConfiguration([], new ContainerBuilder());
    }
}

class EnableableExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container)
    {
    }

    public function isConfigEnabled(ContainerBuilder $container, array $config): bool
    {
        return parent::isConfigEnabled($container, $config);
    }
}
