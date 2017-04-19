<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Security;

use Psr\Container\ContainerInterface;
use Symfony\Component\Security\Http\FirewallMapInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * This is a lazy-loading firewall map implementation.
 *
 * Listeners will only be initialized if we really need them.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class FirewallMap extends _FirewallMap implements FirewallMapInterface
{
    /**
     * @deprecated since version 3.3, to be removed in 4.0 alongside with magic methods below
     */
    private $container;

    /**
     * @deprecated since version 3.3, to be removed in 4.0 alongside with magic methods below
     */
    private $map;

    public function __construct(ContainerInterface $container, $map)
    {
        parent::__construct($container, $map);
        $this->container = $container;
        $this->map = $map;
    }

    /**
     * @internal
     */
    public function __get($name)
    {
        if ('map' === $name || 'container' === $name) {
            @trigger_error(sprintf('Using the "%s::$%s" property is deprecated since version 3.3 as it will be removed/private in 4.0.', __CLASS__, $name), E_USER_DEPRECATED);

            if ('map' === $name && $this->map instanceof \Traversable) {
                $this->map = iterator_to_array($this->map);
            }
        }

        return $this->$name;
    }

    /**
     * @internal
     */
    public function __set($name, $value)
    {
        if ('map' === $name || 'container' === $name) {
            @trigger_error(sprintf('Using the "%s::$%s" property is deprecated since version 3.3 as it will be removed/private in 4.0.', __CLASS__, $name), E_USER_DEPRECATED);

            $set = \Closure::bind(function ($name, $value) { $this->$name = $value; }, $this, parent::class);
            $set($name, $value);
        }

        $this->$name = $value;
    }

    /**
     * @internal
     */
    public function __isset($name)
    {
        if ('map' === $name || 'container' === $name) {
            @trigger_error(sprintf('Using the "%s::$%s" property is deprecated since version 3.3 as it will be removed/private in 4.0.', __CLASS__, $name), E_USER_DEPRECATED);
        }

        return isset($this->$name);
    }

    /**
     * @internal
     */
    public function __unset($name)
    {
        if ('map' === $name || 'container' === $name) {
            @trigger_error(sprintf('Using the "%s::$%s" property is deprecated since version 3.3 as it will be removed/private in 4.0.', __CLASS__, $name), E_USER_DEPRECATED);

            $unset = \Closure::bind(function ($name) { unset($this->$name); }, $this, parent::class);
            $unset($name);
        }

        unset($this->$name);
    }
}

/**
 * @internal to be removed in 4.0
 */
class _FirewallMap
{
    private $container;
    private $map;
    private $contexts;

    public function __construct(ContainerInterface $container, $map)
    {
        $this->container = $container;
        $this->map = $map;
        $this->contexts = new \SplObjectStorage();
    }

    public function getListeners(Request $request)
    {
        $context = $this->getFirewallContext($request);

        if (null === $context) {
            return array(array(), null);
        }

        return array($context->getListeners(), $context->getExceptionListener());
    }

    /**
     * @return FirewallConfig|null
     */
    public function getFirewallConfig(Request $request)
    {
        $context = $this->getFirewallContext($request);

        if (null === $context) {
            return;
        }

        return $context->getConfig();
    }

    private function getFirewallContext(Request $request)
    {
        if ($this->contexts->contains($request)) {
            return $this->contexts[$request];
        }

        foreach ($this->map as $contextId => $requestMatcher) {
            if (null === $requestMatcher || $requestMatcher->matches($request)) {
                return $this->contexts[$request] = $this->container->get($contextId);
            }
        }
    }
}
