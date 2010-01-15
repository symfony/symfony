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
 * SwiftMailerExtension is an extension for the Swift Mailer library.
 *
 * @package    symfony
 * @subpackage dependency_injection
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class SwiftMailerExtension extends LoaderExtension
{
  /**
   * Loads the Swift Mailer configuration.
   *
   * Usage example:
   *
   *      <swift:mailer transport="gmail" delivery_strategy="spool">
   *        <swift:username>fabien</swift:username>
   *        <swift:password>xxxxx</swift:password>
   *        <swift:spool path="/path/to/spool/" />
   *      </swift:mailer>
   *
   * @param array $config A configuration array
   *
   * @return BuilderConfiguration A BuilderConfiguration instance
   */
  public function mailerLoad($config)
  {
    $configuration = new BuilderConfiguration();

    $loader = new XmlFileLoader(__DIR__.'/xml/swiftmailer');
    $configuration->merge($loader->load('swiftmailer-1.0.xml'));

    if (null === $config['transport'])
    {
      $config['transport'] = 'null';
    }
    elseif (!isset($config['transport']))
    {
      $config['transport'] = 'smtp';
    }
    elseif ('gmail' === $config['transport'])
    {
      $config['encryption'] = 'ssl';
      $config['auth_mode'] = 'login';
      $config['host'] = 'smtp.gmail.com';
      $config['transport'] = 'smtp';
    }

    $configuration->setAlias('swiftmailer.transport', 'swiftmailer.transport.'.$config['transport']);

    if (isset($config['encryption']) && 'ssl' === $config['encryption'] && !isset($config['port']))
    {
      $config['port'] = 465;
    }

    foreach (array('encryption', 'port', 'host', 'username', 'password', 'auth_mode') as $key)
    {
      if (isset($config[$key]))
      {
        $configuration->setParameter('swiftmailer.transport.'.$config['transport'].'.'.$key, $config[$key]);
      }
    }

    // spool?
    if (isset($config['spool']))
    {
      $type = isset($config['type']) ? $config['type'] : 'file';

      $configuration->setAlias('swiftmailer.transport.real', 'swiftmailer.transport.'.$config['transport']);
      $configuration->setAlias('swiftmailer.transport', 'swiftmailer.transport.spool');
      $configuration->setAlias('swiftmailer.spool', 'swiftmailer.spool.'.$type);

      foreach (array('path') as $key)
      {
        if (isset($config['spool'][$key]))
        {
          $configuration->setParameter('swiftmailer.spool.'.$type.'.'.$key, $config['spool'][$key]);
        }
      }
    }

    $configuration->setAlias(isset($config['alias']) ? $config['alias'] : 'mailer', 'swiftmailer.mailer');

    return $configuration;
  }

  /**
   * Returns the namespace to be used for this extension (XML namespace).
   *
   * @return string The XML namespace
   */
  public function getNamespace()
  {
    return 'http://www.symfony-project.org/schema/swiftmailer';
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
    return 'swift';
  }
}
