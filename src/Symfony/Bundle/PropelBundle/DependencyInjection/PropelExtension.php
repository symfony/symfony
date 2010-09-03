<?php

namespace Symfony\Bundle\PropelBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class PropelExtension extends Extension
{
    protected $resources = array(
        'propel' => 'propel.xml',
    );

    /**
     * Loads the Propel configuration.
     *
     * @param array            $config    An array of configuration settings
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    public function configLoad($config, ContainerBuilder $container)
    {
        if (!$container->hasDefinition('propel')) {
            $loader = new XmlFileLoader($container, __DIR__.'/../Resources/config');
            $loader->load($this->resources['propel']);
        }

        if (!$container->hasParameter('propel.path')) {
            if (!isset($config['path'])) {
                throw new \InvalidArgumentException('The "path" parameter is mandatory.');
            }

            $container->setParameter('propel.path', $config['path']);
        }

        if (isset($config['path'])) {
            $container->setParameter('propel.path', $config['path']);
        }

        if (isset($config['phing_path'])) {
            $container->setParameter('propel.phing_path', $config['phing_path']);
        }
    }

    /**
     * Loads the DBAL configuration.
     *
     * @param array            $config    An array of configuration settings
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    public function dbalLoad($config, ContainerBuilder $container)
    {
        if (!$container->hasDefinition('propel')) {
            $loader = new XmlFileLoader($container, __DIR__.'/../Resources/config');
            $loader->load($this->resources['propel']);
        }

        $defaultConnection = array(
            'driver'              => 'mysql',
            'user'                => 'root',
            'password'            => null,
            'dsn'                 => null,
// FIXME: should be automatically changed based on %kernel.debug%
            'classname'           => 'DebugPDO', //'PropelPDO',
            'options'             => array(),
            'attributes'          => array(),
// FIXME: Mysql wants UTF8, not UTF-8 (%kernel.charset%)
            'settings'            => array('charset' => array('value' => 'UTF8')),
        );

        $defaultConnectionName = isset($config['default_connection']) ? $config['default_connection'] : $container->getParameter('propel.dbal.default_connection');
        $container->setParameter('propel.dbal.default_connection', $defaultConnectionName);

        $connections = array();
        if (isset($config['connections'])) {
            foreach ($config['connections'] as $name => $connection) {
                $connections[isset($connection['id']) ? $connection['id'] : $name] = $connection;
            }
        } else {
            $connections = array($defaultConnectionName => $config);
        }

        $arguments = $container->getDefinition('propel.configuration')->getArguments();
        if (count($arguments)) {
            $c = $arguments[0];
        } else {
            $c = array(
// FIXME: should be the same value as %zend.logger.priority%
                'log'         => array('level' => 7),
                'datasources' => array(),
            );
        }

        foreach ($connections as $name => $connection) {
            if (isset($c['datasources'][$name])) {
            } else {
                $connection = array_replace($defaultConnection, $connection);

                $c['datasources'][$name] = array(
                  'connection' => array(),
                );
            }

            if (isset($connection['driver'])) {
                $c['datasources'][$name]['adapter'] = $connection['driver'];
            }
            foreach (array('dsn', 'user', 'password', 'classname', 'options', 'attributes', 'settings') as $att) {
                if (isset($connection[$att])) {
                    $c['datasources'][$name]['connection'][$att] = $connection[$att];
                }
            }
        }

        $container->getDefinition('propel.configuration')->setArguments(array($c));
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

    /**
     * Returns the namespace to be used for this extension (XML namespace).
     *
     * @return string The XML namespace
     */
    public function getNamespace()
    {
        return 'http://www.symfony-project.org/schema/dic/propel';
    }

    /**
     * Returns the recommended alias to use in XML.
     *
     * This alias is also the mandatory prefix to use when using YAML.
     *
     * @return string The alias
     */
    public function getAlias()
    {
        return 'propel';
    }
}
