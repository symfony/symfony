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
  protected $defaultHelpers = array();
  protected $alias;

  public function setAlias($alias)
  {
    $this->alias = $alias;
  }

  public function setDefaultHelpers(array $defaultHelpers)
  {
    $this->defaultHelpers = $defaultHelpers;
  }

  /**
   * Loads the templating configuration.
   *
   * Usage example:
   *
   *      <symfony:templating path="/path/to/templates" cache="/path/to/cache">
   *        <symfony:loader>symfony.templating.loader.filesystem</symfony:loader>
   *        <symfony:helpers>
   *          symfony.templating.helper.javascripts
   *          symfony.templating.helper.stylesheets
   *        </symfony:helpers>
   *      </symfony:templating>
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

    if (1 === count($loaders))
    {
      $configuration->setAlias('symfony.templating.loader', (string) $loaders[0]);
    }
    else
    {
      $configuration->getDefinition('symfony.templating.loader.chain')->addArgument($loaders);
      $configuration->setAlias('symfony.templating.loader', 'symfony.templating.loader.chain');
    }

    // helpers
    if (array_key_exists('helpers', $config))
    {
      $helpers = array();
      foreach (explode("\n", $config['helpers']) as $helper)
      {
        $helpers[] = new Reference(trim($helper));
      }
    }
    else
    {
      $helpers = $this->defaultHelpers;
    }

    $configuration->getDefinition('symfony.templating.helperset')->addArgument($helpers);

    // cache?
    if (isset($config['cache']))
    {
      // wrap the loader with some cache
      $configuration->setDefinition('symfony.templating.loader.wrapped', $configuration->findDefinition('symfony.templating.loader'));
      $configuration->setDefinition('symfony.templating.loader', $configuration->getDefinition('symfony.templating.loader.cache'));
      $configuration->setParameter('symfony.templating.loader.cache.path', $config['cache']);
    }

    $configuration->setAlias('templating', null !== $this->alias ? $this->alias : 'symfony.templating.engine');

    return $configuration;
  }

  /**
   * Returns the namespace to be used for this extension (XML namespace).
   *
   * @return string The XML namespace
   */
  public function getNamespace()
  {
    return 'http://www.symfony-project.org/schema/dic/symfony';
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
