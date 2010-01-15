<?php

namespace Symfony\Components\DependencyInjection\Loader\Extension;

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

    $loader = new XmlFileLoader(__DIR__.'/xml/doctrine');
    $configuration->merge($loader->load('dbal-1.0.xml'));

    foreach (array('dbname', 'driverClass', 'host', 'username', 'password') as $key)
    {
      if (isset($config[$key]))
      {
        $configuration->setParameter('doctrine.dbal.'.$key, $config[$key]);
      }
    }

    return $configuration;
  }

  /**
   * Returns the namespace to be used for this extension (XML namespace).
   *
   * @return string The XML namespace
   */
  public function getNamespace()
  {
    return 'http://www.symfony-project.org/schema/doctrine';
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
