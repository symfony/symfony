<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com
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
 * @author Christophe Coevoet <stof@notk.org>
 */
class MonologExtension extends Extension
{
    private $nestedHandlers = array();

    /**
     * Loads the Monolog configuration.
     *
     * @param array            $config    An array of configuration settings
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $processor = new Processor();
        $config = $processor->processConfiguration($configuration, $configs);

        if (isset($config['handlers'])) {
            $loader = new XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
            $loader->load('monolog.xml');
            $container->setAlias('logger', 'monolog.logger');

            $logger = $container->getDefinition('monolog.logger_prototype');

            if (!empty ($config['processors'])) {
                $this->addProcessors($logger, $config['processors']);
            }

            $handlers = array();
            foreach ($config['handlers'] as $name => $handler) {
                $handlers[] = array('id' => $this->buildHandler($container, $name, $handler), 'priority' => $handler['priority'] );
            }

            $handlers = array_reverse($handlers);
            uasort($handlers, function($a, $b) {
                if ($a['priority'] == $b['priority']) {
                    return 0;
                }
                return $a['priority'] < $b['priority'] ? -1 : 1;
            });
            foreach ($handlers as $handler) {
                if (!in_array($handler['id'], $this->nestedHandlers)) {
                    $logger->addMethodCall('pushHandler', array(new Reference($handler['id'])));
                }
            }
        }

        $this->addClassesToCompile(array(
            'Monolog\\Formatter\\FormatterInterface',
            'Monolog\\Formatter\\LineFormatter',
            'Monolog\\Handler\\HandlerInterface',
            'Monolog\\Handler\\AbstractHandler',
            'Monolog\\Handler\\StreamHandler',
            'Monolog\\Handler\\FingersCrossedHandler',
            'Monolog\\Handler\\TestHandler',
            'Monolog\\Logger',
            'Symfony\\Bundle\\MonologBundle\\Logger\\Logger',
            'Symfony\\Bundle\\MonologBundle\\Logger\\DebugHandler',
        ));
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
        return 'http://symfony.com/schema/dic/monolog';
    }

    private function buildHandler(ContainerBuilder $container, $name, array $handler)
    {
        $handlerId = $this->getHandlerId($name);
        $definition = new Definition(sprintf('%%monolog.handler.%s.class%%', $handler['type']));
        $handler['level'] = is_int($handler['level']) ? $handler['level'] : constant('Monolog\Logger::'.strtoupper($handler['level']));

        switch ($handler['type']) {
        case 'service':
            $container->setAlias($handlerId, $handler['id']);
            return $handlerId;

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

        case 'rotating_file':
            if (!isset($handler['path'])) {
                $handler['path'] = '%kernel.logs_dir%/%kernel.environment%.log';
            }

            $definition->setArguments(array(
                $handler['path'],
                isset($handler['max_files']) ? $handler['max_files'] : 0,
                $handler['level'],
                $handler['bubble'],
            ));
            break;

        case 'fingers_crossed':
            if (!isset($handler['action_level'])) {
                $handler['action_level'] = 'WARNING';
            }
            $handler['action_level'] = is_int($handler['action_level']) ? $handler['action_level'] : constant('Monolog\Logger::'.strtoupper($handler['action_level']));
            $nestedHandlerId = $this->getHandlerId($handler['handler']);
            array_push($this->nestedHandlers, $nestedHandlerId);

            $definition->setArguments(array(
                new Reference($nestedHandlerId),
                $handler['action_level'],
                isset($handler['buffer_size']) ? $handler['buffer_size'] : 0,
                $handler['bubble'],
            ));
            break;

        case 'buffer':
            $nestedHandlerId = $this->getHandlerId($handler['handler']);
            array_push($this->nestedHandlers, $nestedHandlerId);

            $definition->setArguments(array(
                new Reference($nestedHandlerId),
                isset($handler['buffer_size']) ? $handler['buffer_size'] : 0,
                $handler['level'],
                $handler['bubble'],
            ));
            break;

        case 'syslog':
            if (!isset($handler['ident'])) {
                $handler['ident'] = false;
            }
            if (!isset($handler['facility'])) {
                $handler['facility'] = 'user';
            }

            $definition->setArguments(array(
                $handler['ident'],
                $handler['facility'],
                $handler['level'],
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

        if (!empty ($handler['formatter'])) {
            $definition->addMethodCall('setFormatter', array(new Reference($handler['formatter'])));
        }
        if (!empty ($handler['processors'])) {
            $this->addProcessors($definition, $handler['processors']);
        }
        $container->setDefinition($handlerId, $definition);

        return $handlerId;
    }

    private function getHandlerId($name)
    {
        return sprintf('monolog.handler.%s', $name);
    }

    private function addProcessors(Definition $definition, array $processors)
    {
        foreach (array_reverse($processors) as $processor) {
            if (0 === strpos($processor, '@')) {
                $processor = new Reference(substr($processor, 1));
            }
            $definition->addMethodCall('pushProcessor', array($processor));
        }
    }
}
