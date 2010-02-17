<?php

namespace Symfony\Framework\DoctrineBundle\DependencyInjection;

use Symfony\Components\DependencyInjection\Loader\LoaderExtension;
use Symfony\Components\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Components\DependencyInjection\BuilderConfiguration;

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
   *      <doctrine:dbal dbname="sfweb" username="root" />
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

    foreach (array('dbname', 'host', 'username', 'password', 'path', 'port') as $key)
    {
      if (isset($config[$key]))
      {
        $configuration->setParameter('doctrine.dbal.'.$key, $config[$key]);
      }
    }

    if (isset($config['options']))
    {
      $configuration->setParameter('doctrine.dbal.driver.options', $config['options']);
    }

    if (isset($config['driver']))
    {
      $class = $config['driver'];
      if (in_array($class, array('OCI8', 'PDOMsSql', 'PDOMySql', 'PDOOracle', 'PDOPgSql', 'PDOSqlite')))
      {
        $class = 'Doctrine\\DBAL\\Driver\\'.$class.'\\Driver';
      }

      $configuration->setParameter('doctrine.dbal.driver.class', $class);
    }

    $configuration->setAlias('database_connection', null !== $this->alias ? $this->alias : 'doctrine.dbal.connection');

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
