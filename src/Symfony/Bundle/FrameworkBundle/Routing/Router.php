<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Routing;

use Symfony\Component\Routing\Router as BaseRouter;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * This Router only creates the Loader only when the cache is empty.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Router extends BaseRouter
{
    private $container;

    /**
     * Constructor.
     *
     * @param ContainerInterface $container A ContainerInterface instance
     * @param mixed              $resource  The main resource to load
     * @param array              $options   An array of options
     * @param RequestContext     $context   The context
     * @param array              $defaults  The default values
     */
    public function __construct(ContainerInterface $container, $resource, array $options = array(), RequestContext $context = null, array $defaults = array())
    {
        $this->container = $container;

        $this->resource = $resource;
        $this->context = null === $context ? new RequestContext() : $context;
        $this->defaults = $defaults;
        $this->setOptions($options);
    }

    /**
     * {@inheritdoc}
     */
    public function getRouteCollection()
    {
        if (null === $this->collection) {
            $this->collection = $this->container->get('routing.loader')->load($this->resource, $this->options['resource_type']);
        }

        return $this->collection;
    }

    /*
     * Validate "Host" (untrusted user input)
     *
     * @param string $host           Contents of Host: header from Request
     * @param array  $trustedDomains An array of trusted domains
     *
     * @return boolean True if valid; false otherwise
     */
    public function isValidHost($host, $trustedDomains)
    {
        // Only punctuation we allow is '[', ']', ':', '.' and '-'
        $hostLength = function_exists('mb_orig_strlen') ? mb_orig_strlen($host) : strlen($host);
        if ($hostLength !== strcspn($host, '`~!@#$%^&*()_+={}\\|;"\'<>,?/ ')) {
            return false;
        }

        $untrustedHost = function_exists('mb_strtolower') ? mb_strtolower($host) : strtolower($host);
        $domainRegex   = str_replace('.', '\.', '/(^|.)' . implode('|', $trustedDomains) . '(:[0-9]+)?$/');

        return 0 !== preg_match($domainRegex, rtrim($untrustedHost, '.'));
    }

    /**
     * {@inheritdoc}
     */
    public function generate($name, $parameters = array(), $absolute = false)
    {
        if (!$absolute) {
            return $this->getGenerator()->generate($name, $parameters, $absolute);
        }

        $validRoute = !isset($this->container)
                   || $this->isValidHost($this->context->getHost(), $this->container->getParameter('trusted_domains'));
        if ($validRoute) {
            return $this->getGenerator()->generate($name, $parameters, $absolute);
        }

        throw new RouteNotFoundException(sprintf('The "%s" route requires a valid host.', $name));
    }
}
