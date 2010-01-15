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
 * ZendExtension is an extension for the Zend Framework libraries.
 *
 * @package    symfony
 * @subpackage dependency_injection
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class ZendExtension extends LoaderExtension
{
  /**
   * Loads the logger configuration.
   *
   * Usage example:
   *
   *      <zend:logger priority="info" path="/path/to/some.log" />
   *
   * @param array $config A configuration array
   *
   * @return BuilderConfiguration A BuilderConfiguration instance
   */
  public function loggerLoad($config)
  {
    $configuration = new BuilderConfiguration();

    $loader = new XmlFileLoader(__DIR__.'/xml/zend');
    $configuration->merge($loader->load('logger-1.0.xml'));

    if (isset($config['priority']))
    {
      $configuration->setParameter('zend.logger.priority', is_int($config['priority']) ? $config['priority'] : constant('\Zend_Log::'.strtoupper($config['priority'])));
    }

    if (isset($config['path']))
    {
      $configuration->setParameter('zend.logger.path', $config['path']);
    }

    return $configuration;
  }

  /**
   * Loads the mail configuration.
   *
   * Usage example:
   *
   *      <zend:mail transport="gmail">
   *        <zend:username>fabien</zend:username>
   *        <zend:password>xxxxxx</zend:password>
   *      </zend:mail>
   *
   * @param array $config A configuration array
   *
   * @return BuilderConfiguration A BuilderConfiguration instance
   */
  public function mailLoad($config)
  {
    $configuration = new BuilderConfiguration();

    $loader = new XmlFileLoader(__DIR__.'/xml/zend');
    $configuration->merge($loader->load('mail-1.0.xml'));

    if (isset($config['transport']))
    {
      if ('gmail' === $config['transport'])
      {
        $config['ssl'] = 'ssl';
        $config['auth'] = 'login';
        $config['host'] = 'smtp.gmail.com';

        $configuration->setAlias('zend.mail.transport', 'zend.mail.transport.smtp.ssl');
      }
      else
      {
        if (isset($config['ssl']) && $config['ssl'])
        {
          $config['transport'] = $config['transport'].'.ssl';
        }
        $configuration->setAlias('zend.mail.transport', 'zend.mail.transport.'.$config['transport']);
      }
    }

    if (isset($config['ssl']))
    {
      if (true === $config['ssl'] || 'ssl' === $config['ssl'])
      {
        $config['ssl'] = 'ssl';
        if (!isset($config['port']))
        {
          $config['port'] = 465;
        }
      }
      $configuration->setParameter('zend.mail.smtp.ssl', $config['ssl']);
    }

    foreach (array('port', 'host', 'username', 'password', 'auth') as $key)
    {
      if (isset($config[$key]))
      {
        $configuration->setParameter('zend.mail.smtp.'.$key, $config[$key]);
      }
    }

    return $configuration;
  }

  public function getNamespace()
  {
    return 'http://www.symfony-project.org/schema/zend';
  }

  public function getAlias()
  {
    return 'zend';
  }
}
