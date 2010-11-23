<?php

namespace Symfony\Component\Templating;

use Symfony\Component\Templating\Loader\LoaderInterface;
use Symfony\Component\Templating\Renderer\PhpRenderer;
use Symfony\Component\Templating\Renderer\RendererInterface;
use Symfony\Component\Templating\Helper\HelperInterface;

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
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class Engine implements \ArrayAccess
{
    protected $loader;
    protected $renderers;
    protected $current;
    protected $currentRenderer;
    protected $helpers;
    protected $parents;
    protected $stack;
    protected $charset;
    protected $cache;
    protected $escapers;

    /**
     * Constructor.
     *
     * @param LoaderInterface $loader    A loader instance
     * @param array           $renderers An array of renderer instances
     * @param array           $helpers   A array of helper instances
     * @param array           $escapers  An array of escapers
     */
    public function __construct(LoaderInterface $loader, array $renderers = array(), array $helpers = array(), array $escapers = array())
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

        $this->initializeEscapers();

        foreach ($this->escapers as $context => $escaper) {
            $this->setEscaper($context, $escaper);
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
            list($tpl, $options, $template) = $this->cache[$name];
        } else {
            list($tpl, $options) = $this->splitTemplateName($name);

            // load
            $template = $this->loader->load($tpl, $options);

            if (false === $template) {
                throw new \InvalidArgumentException(sprintf('The template "%s" does not exist (renderer: %s).', $name, $options['renderer']));
            }

            $this->cache[$name] = array($tpl, $options, $template);
        }

        // renderer
        $renderer = $template->getRenderer() ? $template->getRenderer() : $options['renderer'];

        // a decorator must use the same renderer as its children
        if (null !== $this->currentRenderer && $renderer !== $this->currentRenderer) {
            throw new \LogicException(sprintf('A "%s" template cannot extend a "%s" template.', $this->currentRenderer, $renderer));
        }

        if (!isset($this->renderers[$options['renderer']])) {
            throw new \InvalidArgumentException(sprintf('The renderer "%s" is not registered.', $renderer));
        }

        $this->current = $name;
        $this->parents[$name] = null;

        // render
        if (false === $content = $this->renderers[$renderer]->evaluate($template, $parameters)) {
            throw new \RuntimeException(sprintf('The template "%s" cannot be rendered (renderer: %s).', $name, $renderer));
        }

        // decorator
        if ($this->parents[$name]) {
            $slots = $this->get('slots');
            $this->stack[] = $slots->get('_content');
            $slots->set('_content', $content);

            $this->currentRenderer = $renderer;
            $content = $this->render($this->parents[$name], $parameters);
            $this->currentRenderer = null;

            $slots->set('_content', array_pop($this->stack));
        }

        return $content;
    }

    /**
     * Returns true if the template exists.
     *
     * @param string $name A template name
     *
     * @return Boolean true if the template exists, false otherwise
     */
    public function exists($name)
    {
        return false !== $this->load($name);
    }

    /**
     * Returns true if the template exists.
     *
     * @param string $name A template name
     *
     * @return Boolean true if the template exists, false otherwise
     */
    public function load($name)
    {
        if (isset($this->cache[$name])) {
            list($tpl, $options, $template) = $this->cache[$name];
        } else {
            list($tpl, $options) = $this->splitTemplateName($name);

            // load
            $template = $this->loader->load($tpl, $options);

            if (false === $template) {
                return false;
            }

            $this->cache[$name] = array($tpl, $options, $template);
        }

        return $template;
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
    public function offsetGet($name)
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
    public function offsetExists($name)
    {
        return isset($this->helpers[$name]);
    }

    /**
     * Sets a helper.
     *
     * @param HelperInterface $value The helper instance
     * @param string          $alias An alias
     */
    public function offsetSet($name, $value)
    {
        $this->set($name, $value);
    }

    /**
     * Removes a helper.
     *
     * @param string $name The helper name
     */
    public function offsetUnset($name)
    {
        throw new \LogicException(sprintf('You can\'t unset a helper (%s).', $name));
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
     * @param mixed $value A variable to escape
     *
     * @return string The escaped value
     */
    public function escape($value, $context = 'html')
    {
        return call_user_func($this->getEscaper($context), $value);
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

    /**
     * Adds an escaper for the given context.
     *
     * @param string $name    The escaper context (html, js, ...)
     * @param mixed  $escaper A PHP callable
     */
    public function setEscaper($context, $escaper)
    {
        $this->escapers[$context] = $escaper;
    }

    /**
     * Gets an escaper for a given context.
     *
     * @param  string $name The context name
     *
     * @return mixed  $escaper A PHP callable
     */
    public function getEscaper($context)
    {
        if (!isset($this->escapers[$context])) {
            throw new \InvalidArgumentException(sprintf('No registered escaper for context "%s".', $context));
        }

        return $this->escapers[$context];
    }

    /**
     * Initializes the built-in escapers.
     *
     * Each function specifies a way for applying a transformation to a string
     * passed to it. The purpose is for the string to be "escaped" so it is
     * suitable for the format it is being displayed in.
     *
     * For example, the string: "It's required that you enter a username & password.\n"
     * If this were to be displayed as HTML it would be sensible to turn the
     * ampersand into '&amp;' and the apostrophe into '&aps;'. However if it were
     * going to be used as a string in JavaScript to be displayed in an alert box
     * it would be right to leave the string as-is, but c-escape the apostrophe and
     * the new line.
     *
     * For each function there is a define to avoid problems with strings being
     * incorrectly specified.
     */
    protected function initializeEscapers()
    {
        $that = $this;

        $this->escapers = array(
            'html' =>
                /**
                 * Runs the PHP function htmlspecialchars on the value passed.
                 *
                 * @param string $value the value to escape
                 *
                 * @return string the escaped value
                 */
                function ($value) use ($that)
                {
                    // Numbers and boolean values get turned into strings which can cause problems
                    // with type comparisons (e.g. === or is_int() etc).
                    return is_string($value) ? htmlspecialchars($value, ENT_QUOTES, $that->getCharset(), false) : $value;
                },

            'js' =>
                /**
                 * A function that escape all non-alphanumeric characters
                 * into their \xHH or \uHHHH representations
                 *
                 * @param string $value the value to escape
                 * @return string the escaped value
                 */
                function ($value) use ($that)
                {
                    if ('UTF-8' != $that->getCharset()) {
                        $string = $that->convertEncoding($string, 'UTF-8', $that->getCharset());
                    }

                    $callback = function ($matches) use ($that)
                    {
                        $char = $matches[0];

                        // \xHH
                        if (!isset($char[1])) {
                            return '\\x'.substr('00'.bin2hex($char), -2);
                        }

                        // \uHHHH
                        $char = $that->convertEncoding($char, 'UTF-16BE', 'UTF-8');

                        return '\\u'.substr('0000'.bin2hex($char), -4);
                    };

                    if (null === $string = preg_replace_callback('#[^\p{L}\p{N} ]#u', $callback, $string)) {
                        throw new \InvalidArgumentException('The string to escape is not a valid UTF-8 string.');
                    }

                    if ('UTF-8' != $that->getCharset()) {
                        $string = $that->convertEncoding($string, $that->getCharset(), 'UTF-8');
                    }

                    return $string;
                },
        );
    }

    public function convertEncoding($string, $to, $from)
    {
        if (function_exists('iconv')) {
            return iconv($from, $to, $string);
        } elseif (function_exists('mb_convert_encoding')) {
            return mb_convert_encoding($string, $to, $from);
        } else {
            throw new \RuntimeException('No suitable convert encoding function (use UTF-8 as your encoding or install the iconv or mbstring extension).');
        }
    }
}
