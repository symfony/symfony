<?php

namespace Symfony\Framework\WebBundle\Templating;

use Symfony\Components\Templating\Engine as BaseEngine;
use Symfony\Components\Templating\Loader\LoaderInterface;
use Symfony\Components\OutputEscaper\Escaper;
use Symfony\Components\DependencyInjection\ContainerInterface;

/*
 * This file is part of the symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * This engine knows how to render Symfony templates and automatically
 * escapes template parameters.
 *
 * @package symfony
 * @author  Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class Engine extends BaseEngine
{
  protected $container;
  protected $escaper;
  protected $level;

  /**
   * Constructor.
   *
   * @param ContainerInterface $container A ContainerInterface instance
   * @param LoaderInterface    $loader    A loader instance
   * @param array              $renderers An array of renderer instances
   * @param mixed              $escaper   The escaper to use (or false to disable escaping)
   */
  public function __construct(ContainerInterface $container, LoaderInterface $loader, array $renderers = array(), $escaper)
  {
    parent::__construct($loader, $renderers);

    $this->level = 0;
    $this->container = $container;
    $this->escaper = $escaper;

    $this->helpers = array();
    foreach ($this->container->findAnnotatedServiceIds('templating.helper') as $id => $attributes)
    {
      if (isset($attributes[0]['alias']))
      {
        $this->helpers[$attributes[0]['alias']] = $id;
      }
    }
  }

  public function render($name, array $parameters = array())
  {
    ++$this->level;

    // escape only once
    if (1 === $this->level && !isset($parameters['_data']))
    {
      $parameters = $this->escapeParameters($parameters);
    }

    $content = parent::render($name, $parameters);

    --$this->level;

    return $content;
  }

  public function has($name)
  {
    return isset($this->helpers[$name]);
  }

  public function get($name)
  {
    if (!isset($this->helpers[$name]))
    {
      throw new \InvalidArgumentException(sprintf('The helper "%s" is not defined.', $name));
    }

    if (is_string($this->helpers[$name]))
    {
      $this->helpers[$name] = $this->container->getService('templating.helper.'.$name);
      $this->helpers[$name]->setEngine($this);
    }

    return $this->helpers[$name];
  }

  protected function escapeParameters(array $parameters)
  {
    if (false !== $this->escaper)
    {
      Escaper::setCharset($this->getCharset());

      $parameters['_data'] = Escaper::escape($this->escaper, $parameters);
      foreach ($parameters['_data'] as $key => $value)
      {
        $parameters[$key] = $value;
      }
    }
    else
    {
      $parameters['_data'] = Escaper::escape('raw', $parameters);
    }

    return $parameters;
  }

  // Bundle:controller:action(:renderer)
  protected function splitTemplateName($name)
  {
    $parts = explode(':', $name, 3);

    $options = array(
      'bundle'     => str_replace('\\', '/', $parts[0]),
      'controller' => $parts[1],
      'renderer'   => isset($parts[3]) ? $parts[3] : 'php',
      'format'     => '',
    );

    $format = $this->container->getRequestService()->getRequestFormat();
    if (null !== $format && 'html' !== $format)
    {
      $options['format'] = '.'.$format;
    }

    return array($parts[2], $options);
  }
}
