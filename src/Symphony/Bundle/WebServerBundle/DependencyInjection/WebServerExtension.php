<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bundle\WebServerBundle\DependencyInjection;

use Symphony\Bridge\Monolog\Formatter\ConsoleFormatter;
use Symphony\Component\DependencyInjection\Extension\Extension;
use Symphony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symphony\Component\DependencyInjection\ContainerBuilder;
use Symphony\Component\Config\FileLocator;

/**
 * @author Robin Chalas <robin.chalas@gmail.com>
 */
class WebServerExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container)
    {
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('webserver.xml');

        if (!class_exists(ConsoleFormatter::class)) {
            $container->removeDefinition('web_server.command.server_log');
        }
    }
}
