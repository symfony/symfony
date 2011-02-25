<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\MonologBundle\DependencyInjection;

use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Parameter;

/**
 * MonologExtension is an extension for the Monolog library.
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
class MonologExtension extends Extension
{
    /**
     * Loads the Monolog configuration.
     *
     * Usage example:
     *
     *      monolog:
     *          handlers:
     *              myhandler:
     *                  level: info
     *                  path: path/to/some.log
     *
     * @param array            $config    An array of configuration settings
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $processor = new Processor();
        $config = $processor->process($configuration->getConfigTree(), $configs);

        if (isset($config['handlers'])) {
            $loader = new XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
            $loader->load('monolog.xml');
            $container->setAlias('logger', 'monolog.logger');

            $logger = $container->getDefinition('monolog.logger.prototype');

            $handlers = array();
            foreach ($config['handlers'] as $name => $handler) {
                $handlers[] = $this->buildHandler($container, $name, $handler);
            }

            // TODO somehow the DebugLogger should be pushed on the stack as well, or that concept needs to be changed
            // didn't have to investigate yet what it is exactly
            $handlers = array_reverse($handlers);
            foreach ($handlers as $handler) {
                $logger->addMethodCall('pushHandler', array(new Reference($handler)));
            }
        }
    }

    public function buildHandler(ContainerBuilder $container, $name, array $handler)
    {
        $handlerId = sprintf('monolog.handler.%s', $name);
        $definition = new Definition(sprintf('%monolog.handler.%s.class%', $handler['type']));
        $handler['level'] = is_int($handler['level']) ? $handler['level'] : constant('Monolog\Logger::'.strtoupper($handler['level']));

        switch ($handler['type']) {
        case 'stream':
            if (!isset($handler['path'])) {
                $handler['path'] = '%kernel.logs_dir%/%kernel.environment%.log';
            }

            $definition->setArguments(array(
                $handler['path'],
                $handler['level'],
                $handler['bubble'],
            ));
            break;

        case 'fingerscrossed':
            if (!isset($handler['action_level'])) {
                $handler['action_level'] = 'WARNING';
            }
            $handler['action_level'] = is_int($handler['action_level']) ? $handler['action_level'] : constant('Monolog\Logger::'.strtoupper($handler['action_level']));

            $definition->setArguments(array(
                $this->buildHandler($container, $handler['handler']),
                $handler['action_level'],
                isset($handler['buffer_size']) ? $handler['buffer_size'] : 0,
                $handler['bubble'],
            ));
            break;
        default:
            // Handler using the constructor of AbstractHandler without adding their own arguments
            $definition->setArguments(array(
                $handler['level'],
                $handler['bubble'],
            ));
            break;
        }
        $container->setDefinition($handlerId, $definition);

        return $handlerId;
    }

    /**
     * Returns the base path for the XSD files.
     *
     * @return string The XSD base path
     */
    public function getXsdValidationBasePath()
    {
        return __DIR__.'/../Resources/config/schema';
    }

    public function getNamespace()
    {
        return 'http://www.symfony-project.org/schema/dic/monolog';
    }
}
