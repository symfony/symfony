<?php

namespace Symfony\Components\Templating;

use Symfony\Components\Templating\Loader\LoaderInterface;
use Symfony\Components\Templating\Helper\HelperSet;
use Symfony\Components\Templating\Renderer\PhpRenderer;
use Symfony\Components\Templating\Renderer\RendererInterface;

/*
 * This file is part of the symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Engine is the main class of the templating component.
 *
 * @package    symfony
 * @subpackage templating
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class Engine
{
  protected $loader;
  protected $renderers;
  protected $current;
  protected $helperSet;
  protected $parents;
  protected $stack;
  protected $slots;
  protected $openSlots;
  protected $charset;

  /**
   * Constructor.
   *
   * @param LoaderInterface $loader    A loader instance
   * @param array           $renderers An array of renderer instances
   * @param HelperSet       $helperSet A helper set instance
   */
  public function __construct(LoaderInterface $loader, array $renderers = array(), HelperSet $helperSet = null)
  {
    $this->loader    = $loader;
    $this->renderers = $renderers;
    $this->parents   = array();
    $this->stack     = array();
    $this->slots     = array();
    $this->openSlots = array();
    $this->charset   = 'UTF-8';

    $this->setHelperSet(null === $helperSet ? new HelperSet() : $helperSet);

    if (!isset($this->renderers['php']))
    {
      $this->renderers['php'] = new PhpRenderer();
    }

    foreach ($this->renderers as $renderer)
    {
      $renderer->setEngine($this);
    }
  }

  /**
   * Renders a template.
   *
   * The template name is composed of segments separated by a colon (:).
   * By default, this engine knows how to parse templates with one or two segments:
   *
   *  * index:      The template logical name is index and the renderer is php
   *  * index:twig: The template logical name is index and the renderer is twig
   *
   * @param string $name       A template name
   * @param array  $parameters An array of parameters to pass to the template
   *
   * @return string The evaluated template as a string
   *
   * @throws \InvalidArgumentException if the renderer does not exist or if the template does not exist
   * @throws \RuntimeException if the template cannot be rendered
   */
  public function render($name, array $parameters = array())
  {
    list($name, $options) = $this->splitTemplateName($name);

    // load
    $template = $this->loader->load($name, $options);

    if (false === $template)
    {
      throw new \InvalidArgumentException(sprintf('The template "%s" does not exist (renderer: %s).', $name, $options['renderer']));
    }

    $this->current = $name;
    $this->parents[$name] = null;

    // renderer
    $renderer = $template->getRenderer() ? $template->getRenderer() : $options['renderer'];

    if (!isset($this->renderers[$options['renderer']]))
    {
      throw new \InvalidArgumentException(sprintf('The renderer "%s" is not registered.', $renderer));
    }

    // render
    if (false === $content = $this->renderers[$renderer]->evaluate($template, $parameters))
    {
      throw new \RuntimeException(sprintf('The template "%s" cannot be rendered (renderer: %s).', $name, $renderer));
    }

    // decorator
    if ($this->parents[$name])
    {
      $this->stack[] = $this->get('content');
      $this->set('content', $content);

      $content = $this->render($this->parents[$name], $parameters);

      $this->set('content', array_pop($this->stack));
    }

    return $content;
  }

  /**
   * Sets a helper value.
   *
   * @param string          $name  The helper name
   * @param HelperInterface $value The helper value
   */
  public function setHelperSet(HelperSet $helperSet)
  {
    $this->helperSet = $helperSet;

    $helperSet->setEngine($this);
  }

  /**
   * Gets all helper values.
   *
   * @return array An array of all helper values
   */
  public function getHelperSet()
  {
    return $this->helperSet;
  }

  /**
   * Gets a helper value.
   *
   * @param string $name The helper name
   *
   * @return mixed The helper value
   *
   * @throws \InvalidArgumentException if the helper is not defined
   */
  public function __get($name)
  {
    return $this->helperSet->get($name);
  }

  /**
   * Decorates the current template with another one.
   *
   * @param string $template  The decorator logical name
   */
  public function extend($template)
  {
    $this->parents[$this->current] = $template;
  }

  /**
   * Starts a new slot.
   *
   * This method starts an output buffer that will be
   * closed when the stop() method is called.
   *
   * @param string $name  The slot name
   *
   * @throws \InvalidArgumentException if a slot with the same name is already started
   */
  public function start($name)
  {
    if (in_array($name, $this->openSlots))
    {
      throw new \InvalidArgumentException(sprintf('A slot named "%s" is already started.', $name));
    }

    $this->openSlots[] = $name;
    $this->slots[$name] = '';

    ob_start();
    ob_implicit_flush(0);
  }

  /**
   * Stops a slot.
   *
   * @throws \LogicException if no slot has been started
   */
  public function stop()
  {
    $content = ob_get_clean();

    if (!$this->openSlots)
    {
      throw new \LogicException('No slot started.');
    }

    $name = array_pop($this->openSlots);

    $this->slots[$name] = $content;
  }

  /**
   * Returns true if the slot exists.
   *
   * @param string $name The slot name
   */
  public function has($name)
  {
    return isset($this->slots[$name]);
  }

  /**
   * Gets the slot value.
   *
   * @param string $name    The slot name
   * @param string $default The default slot content
   *
   * @return string The slot content
   */
  public function get($name, $default = false)
  {
    return isset($this->slots[$name]) ? $this->slots[$name] : $default;
  }

  /**
   * Sets a slot value.
   *
   * @param string $name    The slot name
   * @param string $content The slot content
   */
  public function set($name, $content)
  {
    $this->slots[$name] = $content;
  }

  /**
   * Outputs a slot.
   *
   * @param string $name    The slot name
   * @param string $default The default slot content
   *
   * @return Boolean true if the slot is defined or if a default content has been provided, false otherwise
   */
  public function output($name, $default = false)
  {
    if (!isset($this->slots[$name]))
    {
      if (false !== $default)
      {
        echo $default;

        return true;
      }

      return false;
    }

    echo $this->slots[$name];

    return true;
  }

  /**
   * Escapes a string by using the current charset.
   *
   * @param string $value A string to escape
   *
   * @return string The escaped string or the original value if not a string
   */
  public function escape($value)
  {
    return is_string($value) || (is_object($value) && method_exists($value, '__toString')) ? htmlspecialchars($value, ENT_QUOTES, $this->charset) : $value;
  }

  /**
   * Sets the charset to use.
   *
   * @param string $charset The charset
   */
  public function setCharset($charset)
  {
    $this->charset = $charset;
  }

  /**
   * Gets the current charset.
   *
   * @return string The current charset
   */
  public function getCharset()
  {
    return $this->charset;
  }

  /**
   * Gets the loader associated with this engine.
   *
   * @return LoaderInterface A LoaderInterface instance
   */
  public function getLoader()
  {
    return $this->loader;
  }

  /**
   * Sets a template renderer.
   *
   * @param string            $name     The renderer name
   * @param RendererInterface $renderer A RendererInterface instance
   */
  public function setRenderer($name, RendererInterface $renderer)
  {
    $this->renderers[$name] = $renderer;
    $renderer->setEngine($this);
  }

  protected function splitTemplateName($name)
  {
    if (false !== $pos = strpos($name, ':'))
    {
      $renderer = substr($name, $pos + 1);
      $name = substr($name, 0, $pos);
    }
    else
    {
      $renderer = 'php';
    }

    return array($name, array('renderer' => $renderer));
  }
}
