<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing;

use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Config\ConfigCacheInterface;
use Symfony\Component\Config\ConfigCacheFactoryInterface;
use Symfony\Component\Config\ConfigCacheFactory;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Generator\ConfigurableRequirementsInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Generator\Dumper\GeneratorDumperInterface;
use Symfony\Component\Routing\Matcher\RequestMatcherInterface;
use Symfony\Component\Routing\Matcher\UrlMatcherInterface;
use Symfony\Component\Routing\Matcher\Dumper\MatcherDumperInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\ExpressionLanguage\ExpressionFunctionProviderInterface;

/**
 * The Router class is an example of the integration of all pieces of the
 * routing system for easier use.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Router implements RouterInterface, RequestMatcherInterface
{
    /**
     * @var UrlMatcherInterface|null
     */
    protected $matcher;

    /**
     * @var UrlGeneratorInterface|null
     */
    protected $generator;

    private $baseUri;
    private $scriptName;
    private $defaultParameters = array();

    /**
     * @var RequestContext
     *
     * @deprecated since version 2.8, to be removed in 3.0.
     */
    protected $context;

    /**
     * @var LoaderInterface
     */
    protected $loader;

    /**
     * @var RouteCollection|null
     */
    protected $collection;

    /**
     * @var mixed
     */
    protected $resource;

    /**
     * @var array
     */
    protected $options = array();

    /**
     * @var LoggerInterface|null
     */
    protected $logger;

    /**
     * @var ConfigCacheFactoryInterface|null
     */
    private $configCacheFactory;

    /**
     * @var ExpressionFunctionProviderInterface[]
     */
    private $expressionLanguageProviders = array();

    /**
     * Constructor.
     *
     * @param LoaderInterface     $loader   A LoaderInterface instance
     * @param mixed               $resource The main resource to load
     * @param array               $options  An array of options
     * @param string|UriInterface $baseUri  The base URI as string or PSR-7 UriInterface implementation
     * @param LoggerInterface     $logger   A logger instance
     */
    public function __construct(LoaderInterface $loader, $resource, array $options = array(), $baseUri = 'http://localhost/', LoggerInterface $logger = null)
    {
        $this->loader = $loader;
        $this->resource = $resource;
        $this->logger = $logger;
        if (null === $baseUri) {
            $this->setContext(new RequestContext());
        } elseif ($baseUri instanceof RequestContext) {
            $this->setContext($baseUri);
        } else {
            $this->setBaseUri($baseUri);
        }
        $this->setOptions($options);
    }

    /**
     * Sets the base URI to use for generating references.
     *
     * The base URI is usually the current URI of the request. With it the generator knows how to construct relative references
     * to the target route. So it's purpose is comparable to the <base href> HTML tag. The information in the base URI will also
     * be used when generating an absolute URI for a route without specific scheme or host requirement. The default base URI
     * before calling this method is "http://localhost/".
     *
     * @param string|UriInterface $uri The base URI as string or PSR-7 UriInterface implementation
     */
    public function setBaseUri($uri)
    {
        // Lazy initialize the generator. So it is only created once generate() is actually called.
        if (null !== $this->generator) {
            $this->getGenerator()->setBaseUri($uri);
        } else {
            $this->baseUri = $uri;
        }
    }

    /**
     * Sets the script path used as path prefix for generated URIs.
     *
     * This is usually the front controller path, e.g. "/app_dev.php", that all paths should start with. It should be empty,
     * which is the default, when URI rewriting is used or the URI should not contain the script. The naming is based on
     * SCRIPT_NAME in the CGI spec which is also available in the $_SERVER global in PHP.
     *
     * @param string $scriptName The URL encoded script name
     */
    public function setScriptName($scriptName)
    {
        if (null !== $this->generator) {
            $this->getGenerator()->setScriptName($scriptName);
        } else {
            $this->scriptName = $scriptName;
        }
    }

    /**
     * Sets a parameter value that will be used for placeholders by default.
     *
     * This placeholder parameter will be used if none has not been provided explicitly in the generate() method.
     *
     * @param string           $name  A parameter name
     * @param string|int|float $value The parameter value
     */
    public function setDefaultParameter($name, $value)
    {
        if (null !== $this->generator) {
            $this->getGenerator()->setDefaultParameter($name, $value);
        } else {
            $this->defaultParameters[$name] = $value;
        }
    }

    /**
     * Sets options.
     *
     * Available options:
     *
     *   * cache_dir:     The cache directory (or null to disable caching)
     *   * debug:         Whether to enable debugging or not (false by default)
     *   * resource_type: Type hint for the main resource (optional)
     *
     * @param array $options An array of options
     *
     * @throws \InvalidArgumentException When unsupported option is provided
     */
    public function setOptions(array $options)
    {
        $this->options = array(
            'cache_dir' => null,
            'debug' => false,
            'generator_class' => 'Symfony\\Component\\Routing\\Generator\\UrlGenerator',
            'generator_base_class' => 'Symfony\\Component\\Routing\\Generator\\UrlGenerator',
            'generator_dumper_class' => 'Symfony\\Component\\Routing\\Generator\\Dumper\\PhpGeneratorDumper',
            'generator_cache_class' => 'ProjectUrlGenerator',
            'matcher_class' => 'Symfony\\Component\\Routing\\Matcher\\UrlMatcher',
            'matcher_base_class' => 'Symfony\\Component\\Routing\\Matcher\\UrlMatcher',
            'matcher_dumper_class' => 'Symfony\\Component\\Routing\\Matcher\\Dumper\\PhpMatcherDumper',
            'matcher_cache_class' => 'ProjectUrlMatcher',
            'resource_type' => null,
            'strict_requirements' => true,
            'http_port' => 80,
            'https_port' => 443,
        );

        // check option names and live merge, if errors are encountered Exception will be thrown
        $invalid = array();
        foreach ($options as $key => $value) {
            if (array_key_exists($key, $this->options)) {
                $this->options[$key] = $value;
            } else {
                $invalid[] = $key;
            }
        }

        if ($invalid) {
            throw new \InvalidArgumentException(sprintf('The Router does not support the following options: "%s".', implode('", "', $invalid)));
        }
    }

    /**
     * Sets an option.
     *
     * @param string $key   The key
     * @param mixed  $value The value
     *
     * @throws \InvalidArgumentException
     */
    public function setOption($key, $value)
    {
        if (!array_key_exists($key, $this->options)) {
            throw new \InvalidArgumentException(sprintf('The Router does not support the "%s" option.', $key));
        }

        $this->options[$key] = $value;
    }

    /**
     * Gets an option value.
     *
     * @param string $key The key
     *
     * @return mixed The value
     *
     * @throws \InvalidArgumentException
     */
    public function getOption($key)
    {
        if (!array_key_exists($key, $this->options)) {
            throw new \InvalidArgumentException(sprintf('The Router does not support the "%s" option.', $key));
        }

        return $this->options[$key];
    }

    /**
     * {@inheritdoc}
     */
    public function getRouteCollection()
    {
        if (null === $this->collection) {
            $this->collection = $this->loader->load($this->resource, $this->options['resource_type']);
        }

        return $this->collection;
    }

    /**
     * {@inheritdoc}
     *
     * @deprecated since version 2.8, to be removed in 3.0. Use setBaseUri and setScriptName instead.
     */
    public function setContext(RequestContext $context)
    {
        @trigger_error('The '.__METHOD__.' method is deprecated since version 2.8 and will be removed in 3.0. Use setBaseUri and setScriptName instead.', E_USER_DEPRECATED);

        $this->context = $context;

        if (null !== $this->matcher) {
            $this->getMatcher()->setContext($context);
        }
        if (null !== $this->generator) {
            $this->getGenerator()->setContext($context);
        }
    }

    /**
     * {@inheritdoc}
     *
     * @deprecated since version 2.8, to be removed in 3.0.
     */
    public function getContext()
    {
        @trigger_error('The '.__METHOD__.' method is deprecated since version 2.8 and will be removed in 3.0.', E_USER_DEPRECATED);

        if (null === $this->context) {
            $this->context = new RequestContext();
        }

        return $this->context;
    }

    /**
     * Sets the ConfigCache factory to use.
     *
     * @param ConfigCacheFactoryInterface $configCacheFactory The factory to use.
     */
    public function setConfigCacheFactory(ConfigCacheFactoryInterface $configCacheFactory)
    {
        $this->configCacheFactory = $configCacheFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function generate($name, $parameters = array(), $referenceType = self::ABSOLUTE_PATH)
    {
        return $this->getGenerator()->generate($name, $parameters, $referenceType);
    }

    /**
     * {@inheritdoc}
     */
    public function match($pathinfo)
    {
        return $this->getMatcher()->match($pathinfo);
    }

    /**
     * {@inheritdoc}
     */
    public function matchRequest(Request $request)
    {
        $matcher = $this->getMatcher();
        if (!$matcher instanceof RequestMatcherInterface) {
            // fallback to the default UrlMatcherInterface
            return $matcher->match($request->getPathInfo());
        }

        return $matcher->matchRequest($request);
    }

    /**
     * Gets the UrlMatcher instance associated with this Router.
     *
     * @return UrlMatcherInterface A UrlMatcherInterface instance
     */
    public function getMatcher()
    {
        if (null !== $this->matcher) {
            return $this->matcher;
        }

        if (null === $this->options['cache_dir'] || null === $this->options['matcher_cache_class']) {
            $this->matcher = new $this->options['matcher_class']($this->getRouteCollection(), $this->getContext());
            if (method_exists($this->matcher, 'addExpressionLanguageProvider')) {
                foreach ($this->expressionLanguageProviders as $provider) {
                    $this->matcher->addExpressionLanguageProvider($provider);
                }
            }

            return $this->matcher;
        }

        $class = $this->options['matcher_cache_class'];
        $baseClass = $this->options['matcher_base_class'];
        $expressionLanguageProviders = $this->expressionLanguageProviders;
        $that = $this; // required for PHP 5.3 where "$this" cannot be use()d in anonymous functions. Change in Symfony 3.0.

        $cache = $this->getConfigCacheFactory()->cache($this->options['cache_dir'].'/'.$class.'.php',
            function (ConfigCacheInterface $cache) use ($that, $class, $baseClass, $expressionLanguageProviders) {
                $dumper = $that->getMatcherDumperInstance();
                if (method_exists($dumper, 'addExpressionLanguageProvider')) {
                    foreach ($expressionLanguageProviders as $provider) {
                        $dumper->addExpressionLanguageProvider($provider);
                    }
                }

                $options = array(
                    'class' => $class,
                    'base_class' => $baseClass,
                );

                $cache->write($dumper->dump($options), $that->getRouteCollection()->getResources());
            }
        );

        require_once $cache->getPath();

        return $this->matcher = new $class($this->context);
    }

    /**
     * Gets the UrlGenerator instance associated with this Router.
     *
     * @return UrlGeneratorInterface A UrlGeneratorInterface instance
     */
    public function getGenerator()
    {
        if (null !== $this->generator) {
            return $this->generator;
        }

        if (null !== $this->context) {
            $this->baseUri = $this->context->getScheme().'://'.$this->context->getHost().$this->context->getBaseUrl().$this->context->getPathInfo();
        }

        if (null === $this->options['cache_dir'] || null === $this->options['generator_cache_class']) {
            $this->generator = new $this->options['generator_class']($this->getRouteCollection(), $this->baseUri, $this->logger, $this->options['http_port'], $this->options['https_port']);
        } else {
            $class = $this->options['generator_cache_class'];
            $baseClass = $this->options['generator_base_class'];
            $that = $this; // required for PHP 5.3 where "$this" cannot be use()d in anonymous functions. Change in Symfony 3.0.
            $cache = $this->getConfigCacheFactory()->cache($this->options['cache_dir'].'/'.$class.'.php',
                function (ConfigCacheInterface $cache) use ($that, $class, $baseClass) {
                    $dumper = $that->getGeneratorDumperInstance();

                    $options = array(
                        'class' => $class,
                        'base_class' => $baseClass,
                    );

                    $cache->write($dumper->dump($options), $that->getRouteCollection()->getResources());
                }
            );

            require_once $cache->getPath();

            $this->generator = new $class($this->baseUri, $this->logger, $this->options['http_port'], $this->options['https_port']);
        }

        if ($this->generator instanceof ConfigurableRequirementsInterface) {
            $this->generator->setStrictRequirements($this->options['strict_requirements']);
        }

        $this->generator->setScriptName($this->scriptName);

        foreach ($this->defaultParameters as $name => $value) {
            $this->generator->setDefaultParameter($name, $value);
        }

        return $this->generator;
    }

    public function addExpressionLanguageProvider(ExpressionFunctionProviderInterface $provider)
    {
        $this->expressionLanguageProviders[] = $provider;
    }

    /**
     * This method is public because it needs to be callable from a closure in PHP 5.3. It should be converted back to protected in 3.0.
     *
     * @internal
     *
     * @return GeneratorDumperInterface
     */
    public function getGeneratorDumperInstance()
    {
        return new $this->options['generator_dumper_class']($this->getRouteCollection());
    }

    /**
     * This method is public because it needs to be callable from a closure in PHP 5.3. It should be converted back to protected in 3.0.
     *
     * @internal
     *
     * @return MatcherDumperInterface
     */
    public function getMatcherDumperInstance()
    {
        return new $this->options['matcher_dumper_class']($this->getRouteCollection());
    }

    /**
     * Provides the ConfigCache factory implementation, falling back to a
     * default implementation if necessary.
     *
     * @return ConfigCacheFactoryInterface $configCacheFactory
     */
    private function getConfigCacheFactory()
    {
        if (null === $this->configCacheFactory) {
            $this->configCacheFactory = new ConfigCacheFactory($this->options['debug']);
        }

        return $this->configCacheFactory;
    }
}
