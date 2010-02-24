<?php

namespace Symfony\Framework\DoctrineBundle\DependencyInjection;

use Symfony\Components\DependencyInjection\Loader\LoaderExtension;
use Symfony\Components\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Components\DependencyInjection\BuilderConfiguration;
use Symfony\Components\DependencyInjection\Definition;
use Symfony\Components\DependencyInjection\Reference;

/*
 * This file is part of the symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * DoctrineExtension is an extension for the Doctrine DBAL and ORM library.
 *
 * @package    symfony
 * @subpackage dependency_injection
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class DoctrineExtension extends LoaderExtension
{
  protected $resources = array(
    'dbal' => 'dbal.xml',
    'orm'  => 'orm.xml',
  );

  protected $alias;

  public function setAlias($alias)
  {
    $this->alias = $alias;
  }

  /**
   * Loads the DBAL configuration.
   *
   * Usage example:
   *
   *      <doctrine:dbal dbname="sfweb" user="root" />
   *
   * @param array $config A configuration array
   *
   * @return BuilderConfiguration A BuilderConfiguration instance
   */
  public function dbalLoad($config)
  {
    $configuration = new BuilderConfiguration();

    $loader = new XmlFileLoader(__DIR__.'/../Resources/config');
    $configuration->merge($loader->load($this->resources['dbal']));

    $defaultConnection = array(
      'driver'              => 'PDOSqlite',
      'dbname'              => 'symfony',
      'user'                => 'root',
      'password'            => null,
      'host'                => 'localhost',
      'port'                => null,
      'path'                => '%kernel.root_dir%/symfony.sqlite',
      'event_manager_class' => 'Doctrine\Common\EventManager',
      'configuration_class' => 'Doctrine\DBAL\Configuration',
      'wrapper_class'       => null,
      'options'             => array()
    );

    $config['default_connection'] = isset($config['default_connection']) ?
      $config['default_connection'] : 'default';

    $config['connections'] = isset($config['connections']) ?
      $config['connections'] : array($config['default_connection'] => $defaultConnection
    );
    foreach ($config['connections'] as $name => $connection)
    {
      $connection = array_merge($defaultConnection, $connection);
      $configurationClass = isset($connection['configuration_class']) ?
        $connection['configuration_class'] : 'Doctrine\DBAL\Configuration';

      $configurationDef = new Definition($configurationClass);
      $configurationDef->addMethodCall('setSqlLogger', array(
        new Reference('doctrine.dbal.logger')
      ));
      $configuration->setDefinition(
        sprintf('doctrine.dbal.%s_connection.configuration', $name),
        $configurationDef
      );

      $eventManagerDef = new Definition($connection['event_manager_class']);
      $configuration->setDefinition(
        sprintf('doctrine.dbal.%s_connection.event_manager', $name),
        $eventManagerDef
      );

      $driverOptions = array();
      if (isset($connection['driver']))
      {
        $driverOptions['driverClass'] = sprintf(
          'Doctrine\\DBAL\\Driver\\%s\\Driver',
          $connection['driver']
        );
      }
      if (isset($connection['wrapper_class']))
      {
        $driverOptions['wrapperClass'] = $connection['wrapper_class'];
      }
      if (isset($connection['options']))
      {
        $driverOptions['driverOptions'] = $connection['options'];
      }
      foreach (array('dbname', 'host', 'user', 'password', 'path', 'port') as $key)
      {
        if (isset($connection[$key]))
        {
          $driverOptions[$key] = $connection[$key];
        }
      }
      $driverArgs = array(
        $driverOptions,
        new Reference(sprintf('doctrine.dbal.%s_connection.configuration', $name)),
        new Reference(sprintf('doctrine.dbal.%s_connection.event_manager', $name))
      );
      $driverDef = new Definition('Doctrine\DBAL\DriverManager', $driverArgs);
      $driverDef->setConstructor('getConnection');
      $configuration->setDefinition(sprintf('doctrine.dbal.%s_connection', $name), $driverDef);
    }

    $configuration->setAlias('database_connection', 
      null !== $this->alias ? $this->alias : sprintf(
        'doctrine.dbal.%s_connection', $config['default_connection']
      )
    );

    return $configuration;
  }

  /**
   * Loads the Doctrine ORM configuration.
   *
   * @param array $config A configuration array
   *
   * @return BuilderConfiguration A BuilderConfiguration instance
   */
  public function ormLoad($config)
  {
    $configuration = new BuilderConfiguration();

    $loader = new XmlFileLoader(__DIR__.'/../Resources/config');
    $configuration->merge($loader->load($this->resources['orm']));

    $config['default_entity_manager'] = isset($config['default_entity_manager']) ?
      $config['default_entity_manager'] : 
      'default'
    ;
    foreach (array('metadata_driver', 'cache_driver') as $key)
    {
      if (isset($config[$key]))
      {
        $configuration->setParameter('doctrine.orm.'.$key, $config[$key]);
      }
    }

    $config['entity_managers'] = isset($config['entity_managers']) ?
      $config['entity_managers'] : array($config['default_entity_manager'] => array())
    ;
    foreach ($config['entity_managers'] as $name => $connection)
    {
      $ormConfigDef = new Definition('Doctrine\ORM\Configuration');
      $configuration->setDefinition(
        sprintf('doctrine.orm.%s_configuration', $name), $ormConfigDef
      );

      $methods = array(
        'setMetadataCacheImpl' => new Reference('doctrine.orm.cache'),
        'setQueryCacheImpl' => new Reference('doctrine.orm.cache'),
        'setResultCacheImpl' => new Reference('doctrine.orm.cache'),
        'setMetadataDriverImpl' => new Reference('doctrine.orm.metadata_driver'),
        'setProxyDir' => '%kernel.cache_dir%/doctrine/Proxies',
        'setProxyNamespace' => 'Proxies',
        'setAutoGenerateProxyClasses' => true
      );

      foreach ($methods as $method => $arg)
      {
        $ormConfigDef->addMethodCall($method, array($arg));
      }

      $ormEmArgs = array(
        new Reference(
          sprintf('doctrine.dbal.%s_connection',
          isset($connection['connection']) ? $connection['connection'] : $name)
        ),
        new Reference(sprintf('doctrine.orm.%s_configuration', $name))
      );
      $ormEmDef = new Definition('Doctrine\ORM\EntityManager', $ormEmArgs);
      $ormEmDef->setConstructor('create');

      $configuration->setDefinition(
        sprintf('doctrine.orm.%s_entity_manager', $name),
        $ormEmDef
      );

      if ($name == $config['default_entity_manager'])
      {
        $configuration->setAlias(
          'doctrine.orm.entity_manager',
          sprintf('doctrine.orm.%s_entity_manager', $name)
        );
      }
    }

    $configuration->setAlias(
      'doctrine.orm.metadata_driver',
      sprintf(
        'doctrine.orm.metadata_driver.%s',
        $configuration->getParameter('doctrine.orm.metadata_driver')
      )
    );

    $configuration->setAlias(
      'doctrine.orm.cache',
      sprintf(
        'doctrine.orm.cache.%s',
        $configuration->getParameter('doctrine.orm.cache_driver')
      )
    );

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
    return 'http://www.symfony-project.org/schema/dic/doctrine';
  }

  /**
   * Returns the recommanded alias to use in XML.
   *
   * This alias is also the mandatory prefix to use when using YAML.
   *
   * @return string The alias
   */
  public function getAlias()
  {
    return 'doctrine';
  }
}
