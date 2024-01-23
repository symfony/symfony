<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Templating;

use Symfony\Component\Templating\Helper\HelperInterface;
use Symfony\Component\Templating\Loader\LoaderInterface;
use Symfony\Component\Templating\Storage\FileStorage;
use Symfony\Component\Templating\Storage\Storage;
use Symfony\Component\Templating\Storage\StringStorage;

trigger_deprecation('symfony/templating', '6.4', '"%s" is deprecated since version 6.4 and will be removed in 7.0. Use Twig instead.', PhpEngine::class);

/**
 * PhpEngine is an engine able to render PHP templates.
 *
 * @implements \ArrayAccess<string, HelperInterface>
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @deprecated since Symfony 6.4, use Twig instead
 */
class PhpEngine implements EngineInterface, \ArrayAccess
{
    protected $loader;
    protected $current;
    /**
     * @var HelperInterface[]
     */
    protected $helpers = [];
    protected $parents = [];
    protected $stack = [];
    protected $charset = 'UTF-8';
    protected $cache = [];
    protected $escapers = [];
    protected static $escaperCache = [];
    protected $globals = [];
    protected $parser;

    private Storage $evalTemplate;
    private array $evalParameters;

    /**
     * @param HelperInterface[] $helpers An array of helper instances
     */
    public function __construct(TemplateNameParserInterface $parser, LoaderInterface $loader, array $helpers = [])
    {
        $this->parser = $parser;
        $this->loader = $loader;

        $this->addHelpers($helpers);

        $this->initializeEscapers();
        foreach ($this->escapers as $context => $escaper) {
            $this->setEscaper($context, $escaper);
        }
    }

    /**
     * @throws \InvalidArgumentException if the template does not exist
     */
    public function render(string|TemplateReferenceInterface $name, array $parameters = []): string
    {
        $storage = $this->load($name);
        $key = hash('sha256', serialize($storage));
        $this->current = $key;
        $this->parents[$key] = null;

        // attach the global variables
        $parameters = array_replace($this->getGlobals(), $parameters);
        // render
        if (false === $content = $this->evaluate($storage, $parameters)) {
            throw new \RuntimeException(sprintf('The template "%s" cannot be rendered.', $this->parser->parse($name)));
        }

        // decorator
        if ($this->parents[$key]) {
            $slots = $this->get('slots');
            $this->stack[] = $slots->get('_content');
            $slots->set('_content', $content);

            $content = $this->render($this->parents[$key], $parameters);

            $slots->set('_content', array_pop($this->stack));
        }

        return $content;
    }

    public function exists(string|TemplateReferenceInterface $name): bool
    {
        try {
            $this->load($name);
        } catch (\InvalidArgumentException) {
            return false;
        }

        return true;
    }

    public function supports(string|TemplateReferenceInterface $name): bool
    {
        $template = $this->parser->parse($name);

        return 'php' === $template->get('engine');
    }

    /**
     * Evaluates a template.
     *
     * @throws \InvalidArgumentException
     */
    protected function evaluate(Storage $template, array $parameters = []): string|false
    {
        $this->evalTemplate = $template;
        $this->evalParameters = $parameters;
        unset($template, $parameters);

        if (isset($this->evalParameters['this'])) {
            throw new \InvalidArgumentException('Invalid parameter (this).');
        }
        if (isset($this->evalParameters['view'])) {
            throw new \InvalidArgumentException('Invalid parameter (view).');
        }

        // the view variable is exposed to the require file below
        $view = $this;
        if ($this->evalTemplate instanceof FileStorage) {
            extract($this->evalParameters, \EXTR_SKIP);
            unset($this->evalParameters);

            ob_start();
            require $this->evalTemplate;

            unset($this->evalTemplate);

            return ob_get_clean();
        } elseif ($this->evalTemplate instanceof StringStorage) {
            extract($this->evalParameters, \EXTR_SKIP);
            unset($this->evalParameters);

            ob_start();
            eval('; ?>'.$this->evalTemplate.'<?php ;');

            unset($this->evalTemplate);

            return ob_get_clean();
        }

        return false;
    }

    /**
     * Gets a helper value.
     *
     * @throws \InvalidArgumentException if the helper is not defined
     */
    public function offsetGet(mixed $name): HelperInterface
    {
        return $this->get($name);
    }

    /**
     * Returns true if the helper is defined.
     */
    public function offsetExists(mixed $name): bool
    {
        return isset($this->helpers[$name]);
    }

    /**
     * Sets a helper.
     */
    public function offsetSet(mixed $name, mixed $value): void
    {
        $this->set($name, $value);
    }

    /**
     * Removes a helper.
     *
     * @throws \LogicException
     */
    public function offsetUnset(mixed $name): void
    {
        throw new \LogicException(sprintf('You can\'t unset a helper (%s).', $name));
    }

    /**
     * Adds some helpers.
     *
     * @param HelperInterface[] $helpers An array of helper
     *
     * @return void
     */
    public function addHelpers(array $helpers)
    {
        foreach ($helpers as $alias => $helper) {
            $this->set($helper, \is_int($alias) ? null : $alias);
        }
    }

    /**
     * Sets the helpers.
     *
     * @param HelperInterface[] $helpers An array of helper
     *
     * @return void
     */
    public function setHelpers(array $helpers)
    {
        $this->helpers = [];
        $this->addHelpers($helpers);
    }

    /**
     * @return void
     */
    public function set(HelperInterface $helper, ?string $alias = null)
    {
        $this->helpers[$helper->getName()] = $helper;
        if (null !== $alias) {
            $this->helpers[$alias] = $helper;
        }

        $helper->setCharset($this->charset);
    }

    /**
     * Returns true if the helper if defined.
     */
    public function has(string $name): bool
    {
        return isset($this->helpers[$name]);
    }

    /**
     * Gets a helper value.
     *
     * @throws \InvalidArgumentException if the helper is not defined
     */
    public function get(string $name): HelperInterface
    {
        if (!isset($this->helpers[$name])) {
            throw new \InvalidArgumentException(sprintf('The helper "%s" is not defined.', $name));
        }

        return $this->helpers[$name];
    }

    /**
     * Decorates the current template with another one.
     *
     * @return void
     */
    public function extend(string $template)
    {
        $this->parents[$this->current] = $template;
    }

    /**
     * Escapes a string by using the current charset.
     */
    public function escape(mixed $value, string $context = 'html'): mixed
    {
        if (is_numeric($value)) {
            return $value;
        }

        // If we deal with a scalar value, we can cache the result to increase
        // the performance when the same value is escaped multiple times (e.g. loops)
        if (\is_scalar($value)) {
            if (!isset(self::$escaperCache[$context][$value])) {
                self::$escaperCache[$context][$value] = $this->getEscaper($context)($value);
            }

            return self::$escaperCache[$context][$value];
        }

        return $this->getEscaper($context)($value);
    }

    /**
     * Sets the charset to use.
     *
     * @return void
     */
    public function setCharset(string $charset)
    {
        if ('UTF8' === $charset = strtoupper($charset)) {
            $charset = 'UTF-8'; // iconv on Windows requires "UTF-8" instead of "UTF8"
        }
        $this->charset = $charset;

        foreach ($this->helpers as $helper) {
            $helper->setCharset($this->charset);
        }
    }

    /**
     * Gets the current charset.
     */
    public function getCharset(): string
    {
        return $this->charset;
    }

    /**
     * Adds an escaper for the given context.
     *
     * @return void
     */
    public function setEscaper(string $context, callable $escaper)
    {
        $this->escapers[$context] = $escaper;
        self::$escaperCache[$context] = [];
    }

    /**
     * Gets an escaper for a given context.
     *
     * @throws \InvalidArgumentException
     */
    public function getEscaper(string $context): callable
    {
        if (!isset($this->escapers[$context])) {
            throw new \InvalidArgumentException(sprintf('No registered escaper for context "%s".', $context));
        }

        return $this->escapers[$context];
    }

    /**
     * @return void
     */
    public function addGlobal(string $name, mixed $value)
    {
        $this->globals[$name] = $value;
    }

    /**
     * Returns the assigned globals.
     */
    public function getGlobals(): array
    {
        return $this->globals;
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
     *
     * @return void
     */
    protected function initializeEscapers()
    {
        $flags = \ENT_QUOTES | \ENT_SUBSTITUTE;

        $this->escapers = [
            'html' =>
                /**
                 * Runs the PHP function htmlspecialchars on the value passed.
                 *
                 * @param string $value The value to escape
                 *
                 * @return string
                 */
                fn ($value) => // Numbers and Boolean values get turned into strings which can cause problems
// with type comparisons (e.g. === or is_int() etc).
\is_string($value) ? htmlspecialchars($value, $flags, $this->getCharset(), false) : $value,

            'js' =>
                /**
                 * A function that escape all non-alphanumeric characters
                 * into their \xHH or \uHHHH representations.
                 *
                 * @param string $value The value to escape
                 *
                 * @return string
                 */
                function ($value) {
                    if ('UTF-8' != $this->getCharset()) {
                        $value = iconv($this->getCharset(), 'UTF-8', $value);
                    }

                    $callback = function ($matches) {
                        $char = $matches[0];

                        // \xHH
                        if (!isset($char[1])) {
                            return '\\x'.substr('00'.bin2hex($char), -2);
                        }

                        // \uHHHH
                        $char = iconv('UTF-8', 'UTF-16BE', $char);

                        return '\\u'.substr('0000'.bin2hex($char), -4);
                    };

                    if (null === $value = preg_replace_callback('#[^\p{L}\p{N} ]#u', $callback, $value)) {
                        throw new \InvalidArgumentException('The string to escape is not a valid UTF-8 string.');
                    }

                    if ('UTF-8' != $this->getCharset()) {
                        $value = iconv('UTF-8', $this->getCharset(), $value);
                    }

                    return $value;
                },
        ];

        self::$escaperCache = [];
    }

    /**
     * Gets the loader associated with this engine.
     */
    public function getLoader(): LoaderInterface
    {
        return $this->loader;
    }

    /**
     * Loads the given template.
     *
     * @throws \InvalidArgumentException if the template cannot be found
     */
    protected function load(string|TemplateReferenceInterface $name): Storage
    {
        $template = $this->parser->parse($name);

        $key = $template->getLogicalName();
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        $storage = $this->loader->load($template);

        if (false === $storage) {
            throw new \InvalidArgumentException(sprintf('The template "%s" does not exist.', $template));
        }

        return $this->cache[$key] = $storage;
    }
}
