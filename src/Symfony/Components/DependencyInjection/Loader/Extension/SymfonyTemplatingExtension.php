<?php

namespace Symfony\Components\DependencyInjection\Loader\Extension;

use Symfony\Components\DependencyInjection\Loader\LoaderExtension;
use Symfony\Components\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Components\DependencyInjection\BuilderConfiguration;
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
 * SymfonyTemplatingExtension is an extension for the Symfony Templating Component.
 *
 * @package    symfony
 * @subpackage dependency_injection
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class SymfonyTemplatingExtension extends LoaderExtension
{
  /**
   * Loads the template configuration.
   *
   * @param array $config A configuration array
   *
   * @return BuilderConfiguration A BuilderConfiguration instance
   */
  public function templatingLoad($config)
  {
    $configuration = new BuilderConfiguration();

    $loader = new XmlFileLoader(__DIR__.'/xml/symfony');
    $configuration->merge($loader->load('templating-1.0.xml'));

    // path for the filesystem loader
    if (isset($config['path']))
    {
      $configuration->setParameter('symfony.templating.loader.filesystem.path', $config['path']);
    }

    // loaders
    if (isset($config['loader']))
    {
      $loaders = array();
      $ids = is_array($config['loader']) ? $config['loader'] : array($config['loader']);
      foreach ($ids as $id)
      {
        $loaders[] = new Reference($id);
      }
    }
    else
    {
      $loaders = array(
        new Reference('symfony.templating.loader.filesystem'),
      );
    }

    $configuration->setParameter('symfony.templating.loader.chain.loaders', $loaders);
    $configuration->setAlias('symfony.templating.loader', 'symfony.templating.loader.chain');

    // helpers
    if (isset($config['helper']))
    {
      $helpers = array();
      $ids = is_array($config['helper']) ? $config['helper'] : array($config['helper']);
      foreach ($ids as $id)
      {
        $helpers[] = new Reference($id);
      }
    }
    else
    {
      $helpers = array(
        new Reference('symfony.templating.helper.javascripts'),
        new Reference('symfony.templating.helper.stylesheets'),
      );
    }

    $configuration->setParameter('symfony.templating.helpers', $helpers);

    // cache?
    if (isset($config['cache']))
    {
      // wrap the loader with some cache
      $configuration->setAlias('symfony.templating.loader.wrapped', $configuration->getAlias('symfony.templating.loader'));
      $configuration->setAlias('symfony.templating.loader', 'symfony.templating.loader.cache');
      $configuration->setParameter('symfony.templating.loader.cache.path', $config['cache']);
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
    return 'http://www.symfony-project.org/schema/symfony';
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
    return 'symfony';
  }
}
