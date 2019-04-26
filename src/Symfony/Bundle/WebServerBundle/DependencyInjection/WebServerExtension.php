<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\WebServerBundle\DependencyInjection;

use Symfony\Bridge\Monolog\Formatter\ConsoleFormatter;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

/**
 * @author Robin Chalas <robin.chalas@gmail.com>
 */
class WebServerExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container)
    {
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('webserver.xml');

        $publicDirectory = $this->getPublicDirectory($container);
        $container->getDefinition('web_server.command.server_run')->replaceArgument(0, $publicDirectory);
        $container->getDefinition('web_server.command.server_start')->replaceArgument(0, $publicDirectory);

        $pidFileDirectory = $this->getPidFileDirectory($container);
        $container->getDefinition('web_server.command.server_run')->replaceArgument(2, $pidFileDirectory);
        $container->getDefinition('web_server.command.server_start')->replaceArgument(2, $pidFileDirectory);
        $container->getDefinition('web_server.command.server_stop')->replaceArgument(0, $pidFileDirectory);
        $container->getDefinition('web_server.command.server_status')->replaceArgument(0, $pidFileDirectory);

        if (!class_exists(ConsoleFormatter::class)) {
            $container->removeDefinition('web_server.command.server_log');
        }
    }

    private function getPublicDirectory(ContainerBuilder $container)
    {
        $kernelProjectDir = $container->getParameter('kernel.project_dir');
        $publicDir = 'public';
        $composerFilePath = $kernelProjectDir.'/composer.json';

        if (!file_exists($composerFilePath)) {
            return $kernelProjectDir.'/'.$publicDir;
        }

        $composerConfig = json_decode(file_get_contents($composerFilePath), true);

        if (isset($composerConfig['extra']['public-dir'])) {
            $publicDir = $composerConfig['extra']['public-dir'];
        }

        return $kernelProjectDir.'/'.$publicDir;
    }

    private function getPidFileDirectory(ContainerBuilder $container): string
    {
        $kernelCacheDir = $container->getParameter('kernel.cache_dir');
        $environment = $container->getParameter('kernel.environment');

        if (basename($kernelCacheDir) !== $environment) {
            return $container->getParameter('kernel.project_dir');
        }

        return \dirname($container->getParameter('kernel.cache_dir'));
    }
}
