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
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

final class ContainerConfiguratorTest extends TestCase
{
    public function testContainerConfigurationExtensionWithServiceReferences()
    {
        $extension = $this->createStub(ExtensionInterface::class);
        $extension->method('getAlias')->willReturn('twig');

        $containerBuilder = new ContainerBuilder();
        $containerBuilder->registerExtension($extension);
        $phpFileLoader = new PhpFileLoader($containerBuilder, new FileLocator(__DIR__.'/../Resources/config'));

        $instanceof = [];
        $containerConfigurator = new ContainerConfigurator($containerBuilder, $phpFileLoader, $instanceof, '', '');

        $containerConfigurator->services()->set('bar', \stdClass::class);

        $containerConfigurator->extension('twig', [
            'debug' => param('kernel.debug'),
            'strict_variables' => true,
            'globals' => [
                'foo' => service('bar'),
            ],
        ]);

        $twigExtensionConfig = $containerBuilder->getExtensionConfig('twig');
        $this->assertEquals(
            [
                [
                    'debug' => '%kernel.debug%',
                    'strict_variables' => true,
                    'globals' => [
                        'foo' => new Reference('bar'),
                    ],
                ],
            ],
            $twigExtensionConfig,
        );
    }
}
