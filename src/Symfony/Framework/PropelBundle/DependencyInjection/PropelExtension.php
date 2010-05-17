<?php

namespace Symfony\Framework\PropelBundle\DependencyInjection;

use Symfony\Components\DependencyInjection\Loader\LoaderExtension;
use Symfony\Components\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Components\DependencyInjection\BuilderConfiguration;
use Symfony\Components\DependencyInjection\Definition;
use Symfony\Components\DependencyInjection\Reference;

class PropelExtension extends LoaderExtension
{
    protected $resources = array(
        'propel' => 'propel.xml',
    );

    /**
     * Loads the DBAL configuration.
     *
     * @param array $config A configuration array
     *
     * @return BuilderConfiguration A BuilderConfiguration instance
     */
    public function generatorLoad($config)
    {
        if (!isset($config['path'])) {
            throw new \InvalidArgumentException('The "path" parameter is mandatory.');
        }

        $configuration = new BuilderConfiguration();
        $configuration->setParameter('propel.generator.path', $config['path']);

        return $configuration;
    }

    /**
     * Loads the DBAL configuration.
     *
     * @param array $config A configuration array
     *
     * @return BuilderConfiguration A BuilderConfiguration instance
     */
    public function dbalLoad($config)
    {
        $configuration = new BuilderConfiguration();

        $loader = new XmlFileLoader(__DIR__.'/../Resources/config');
        $configuration->merge($loader->load($this->resources['propel']));

        $defaultConnection = array(
            'driver'              => 'mysql',
            'user'                => 'root',
            'password'            => null,
            'dsn'                 => null,
            'classname'           => 'PropelPDO',
            'options'             => array(),
            'attributes'          => array(),
// FIXME: Mysql wants UTF8, not UTF-8 (%kernel.charset%)
            'settings'            => array('charset' => array('value' => 'UTF8')),
        );

        $config['default_connection'] = isset($config['default_connection']) ? $config['default_connection'] : 'default';

        $connections = array();
        if (isset($config['connections'])) {
            foreach ($config['connections'] as $name => $connection) {
                $connections[isset($connection['id']) ? $connection['id'] : $name] = $connection;
            }
        } else {
            $connections = array($config['default_connection'] => $config);
        }

        $c = array('datasources' => array());
        foreach ($connections as $name => $connection) {
            $connection = array_replace($defaultConnection, $connection);

            $c['datasources'][$name] = array(
              'adapter'    => $connection['driver'],
              'connection' => array(
                'dsn'        => $connection['dsn'],
                'user'       => $connection['user'],
                'password'   => $connection['password'],
                'classname'  => $connection['classname'],
                'options'    => $connection['options'],
                'attributes' => $connection['attributes'],
                'settings'   => $connection['settings'],
              ),
            );
        }
//// FIXME

        // $c['classmap'] = //...;

        $configuration->getDefinition('propel.configuration')->setArguments(array($c));

        return $configuration;
    }

    /**
     * Returns the base path for the XSD files.
     *
     * @return string The XSD base path
     */
    public function getXsdValidationBasePath()
    {
        return __DIR__.'/../Resources/config/';
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
