<?php

namespace Symfony\Components\Templating;

use Symfony\Components\Templating\Loader\LoaderInterface;
use Symfony\Components\Templating\Renderer\PhpRenderer;
use Symfony\Components\Templating\Renderer\RendererInterface;
use Symfony\Components\Templating\Helper\HelperInterface;

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Engine is the main class of the templating component.
 *
 * @package    Symfony
 * @subpackage Components_Templating
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class Engine
{
    protected $loader;
    protected $renderers;
    protected $current;
    protected $helpers;
    protected $parents;
    protected $stack;
    protected $charset;
    protected $cache;

    /**
     * Constructor.
     *
     * @param LoaderInterface $loader    A loader instance
     * @param array           $renderers An array of renderer instances
     * @param array           $helpers   A array of helper instances
     */
    public function __construct(LoaderInterface $loader, array $renderers = array(), array $helpers = array())
    {
        $this->loader    = $loader;
        $this->renderers = $renderers;
        $this->helpers   = array();
        $this->parents   = array();
        $this->stack     = array();
        $this->charset   = 'UTF-8';
        $this->cache     = array();

        $this->addHelpers($helpers);

        if (!isset($this->renderers['php'])) {
            $this->renderers['php'] = new PhpRenderer();
        }

        foreach ($this->renderers as $renderer) {
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
        if (isset($this->cache[$name])) {
            list($name, $options, $template) = $this->cache[$name];
        } else {
            list($name, $options) = $this->splitTemplateName($old = $name);

            // load
            $template = $this->loader->load($name, $options);

            if (false === $template) {
                throw new \InvalidArgumentException(sprintf('The template "%s" does not exist (renderer: %s).', $name, $options['renderer']));
            }

            $this->cache[$old] = array($name, $options, $template);
        }

        $this->current = $name;
        $this->parents[$name] = null;

        // renderer
        $renderer = $template->getRenderer() ? $template->getRenderer() : $options['renderer'];

        if (!isset($this->renderers[$options['renderer']])) {
            throw new \InvalidArgumentException(sprintf('The renderer "%s" is not registered.', $renderer));
        }

        // render
        if (false === $content = $this->renderers[$renderer]->evaluate($template, $parameters)) {
            throw new \RuntimeException(sprintf('The template "%s" cannot be rendered (renderer: %s).', $name, $renderer));
        }

        // decorator
        if ($this->parents[$name]) {
            $slots = $this->get('slots');
            $this->stack[] = $slots->get('_content');
            $slots->set('_content', $content);

            $content = $this->render($this->parents[$name], $parameters);

            $slots->set('_content', array_pop($this->stack));
        }

        return $content;
    }

    /**
     * Outputs a rendered template.
     *
     * @param string $name       A template name
     * @param array  $parameters An array of parameters to pass to the template
     *
     * @see render()
     */
    public function output($name, array $parameters = array())
    {
        echo $this->render($name, $parameters);
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
        return $this->$name = $this->get($name);
    }

    /**
     * Returns true if the helper is defined.
     *
     * @param string  $name The helper name
     *
     * @return Boolean true if the helper is defined, false otherwise
     */
    public function __isset($name)
    {
        return isset($this->helpers[$name]);
    }

    /**
     * @param Helper[] $helpers An array of helper
     */
    public function addHelpers(array $helpers = array())
    {
        foreach ($helpers as $alias => $helper) {
            $this->set($helper, is_int($alias) ? null : $alias);
        }
    }

    /**
     * Sets a helper.
     *
     * @param HelperInterface $value The helper instance
     * @param string          $alias An alias
     */
    public function set(HelperInterface $helper, $alias = null)
    {
        $this->helpers[$helper->getName()] = $helper;
        if (null !== $alias) {
            $this->helpers[$alias] = $helper;
        }

        $helper->setCharset($this->charset);
    }

    /**
     * Returns true if the helper if defined.
     *
     * @param string  $name The helper name
     *
     * @return Boolean true if the helper is defined, false otherwise
     */
    public function has($name)
    {
        return isset($this->helpers[$name]);
    }

    /**
     * Gets a helper value.
     *
     * @param string $name The helper name
     *
     * @return HelperInterface The helper instance
     *
     * @throws \InvalidArgumentException if the helper is not defined
     */
    public function get($name)
    {
        if (!isset($this->helpers[$name])) {
            throw new \InvalidArgumentException(sprintf('The helper "%s" is not defined.', $name));
        }

        return $this->helpers[$name];
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

    public function splitTemplateName($name)
    {
        if (false !== $pos = strpos($name, ':')) {
            $renderer = substr($name, $pos + 1);
            $name = substr($name, 0, $pos);
        } else {
            $renderer = 'php';
        }

        return array($name, array('renderer' => $renderer));
    }
}
