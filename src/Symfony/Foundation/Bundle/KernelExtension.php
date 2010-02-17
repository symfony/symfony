<?php

namespace Symfony\Foundation\Bundle;

/*
 * This file is part of the symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\Components\DependencyInjection\Loader\LoaderExtension;
use Symfony\Components\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Components\DependencyInjection\BuilderConfiguration;

/**
 *
 * @package Symfony
 * @author  Fabien Potencier <fabien.potencier@symfony-project.org>
 */
class KernelExtension extends LoaderExtension
{
  public function configLoad($config)
  {
    $configuration = new BuilderConfiguration();

    $loader = new XmlFileLoader(array(__DIR__.'/../Resources/config', __DIR__.'/Resources/config'));
    $configuration->merge($loader->load('services.xml'));

    if (isset($config['charset']))
    {
      $configuration->setParameter('kernel.charset', $config['charset']);
    }

    if (!array_key_exists('compilation', $config))
    {
      $classes = array(
        'Symfony\\Components\\Routing\\Router',
        'Symfony\\Components\\Routing\\RouterInterface',
        'Symfony\\Components\\EventDispatcher\\Event',
        'Symfony\\Components\\Routing\\Matcher\\UrlMatcherInterface',
        'Symfony\\Components\\Routing\\Matcher\\UrlMatcher',
        'Symfony\\Components\\RequestHandler\\RequestInterface',
        'Symfony\\Components\\RequestHandler\\Request',
        'Symfony\\Components\\RequestHandler\\RequestHandler',
        'Symfony\\Components\\RequestHandler\\ResponseInterface',
        'Symfony\\Components\\RequestHandler\\Response',
        'Symfony\\Components\\Templating\\Loader\\LoaderInterface',
        'Symfony\\Components\\Templating\\Loader\\Loader',
        'Symfony\\Components\\Templating\\Loader\\FilesystemLoader',
        'Symfony\\Components\\Templating\\Engine',
        'Symfony\\Components\\Templating\\Renderer\\RendererInterface',
        'Symfony\\Components\\Templating\\Renderer\\Renderer',
        'Symfony\\Components\\Templating\\Renderer\\PhpRenderer',
        'Symfony\\Components\\Templating\\Storage\\Storage',
        'Symfony\\Components\\Templating\\Storage\\FileStorage',
        'Symfony\\Framework\\WebBundle\\Controller',
        'Symfony\\Framework\\WebBundle\\Listener\\RequestParser',
        'Symfony\\Framework\\WebBundle\\Listener\\ControllerLoader',
        'Symfony\\Framework\\WebBundle\\Listener\\ResponseFilter',
        'Symfony\\Framework\\WebBundle\\Templating\\Engine',
      );
    }
    else
    {
      $classes = array();
      foreach (explode("\n", $config['compilation']) as $class)
      {
        if ($class)
        {
          $classes[] = trim($class);
        }
      }
    }
    $configuration->setParameter('kernel.compiled_classes', $classes);

    if (array_key_exists('error_handler_level', $config))
    {
      $configuration->setParameter('error_handler.level', $config['error_handler_level']);
    }

    return $configuration;
  }

  /**
   * Returns the base path for the XSD files.
   *
   * @return string The XSD base path
   */
  public function getXsdValidationBasePath()
  {
    return false;
  }

  public function getNamespace()
  {
    return 'http://www.symfony-project.org/schema/dic/symfony/kernel';
  }

  public function getAlias()
  {
    return 'kernel';
  }
}
