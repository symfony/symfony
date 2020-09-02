<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\CacheWarmer;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ServiceSubscriberInterface;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;
use Symfony\Component\HttpKernel\CacheWarmer\WarmableInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * Generates the router matcher and generator classes.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @final since version 3.4
 */
class RouterCacheWarmer implements CacheWarmerInterface, ServiceSubscriberInterface
{
    protected $router;

    /**
     * @param ContainerInterface $container
     */
    public function __construct($container)
    {
        // As this cache warmer is optional, dependencies should be lazy-loaded, that's why a container should be injected.
        if ($container instanceof ContainerInterface) {
            $this->router = $container->get('router'); // For BC, the $router property must be populated in the constructor
        } elseif ($container instanceof RouterInterface) {
            $this->router = $container;
            @trigger_error(sprintf('Using a "%s" as first argument of %s is deprecated since Symfony 3.4 and will be unsupported in version 4.0. Use a %s instead.', RouterInterface::class, __CLASS__, ContainerInterface::class), \E_USER_DEPRECATED);
        } else {
            throw new \InvalidArgumentException(sprintf('"%s" only accepts instance of Psr\Container\ContainerInterface as first argument.', __CLASS__));
        }
    }

    /**
     * Warms up the cache.
     *
     * @param string $cacheDir The cache directory
     */
    public function warmUp($cacheDir)
    {
        if ($this->router instanceof WarmableInterface) {
            $this->router->warmUp($cacheDir);
        }
    }

    /**
     * Checks whether this warmer is optional or not.
     *
     * @return bool always true
     */
    public function isOptional()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedServices()
    {
        return [
            'router' => RouterInterface::class,
        ];
    }
}
