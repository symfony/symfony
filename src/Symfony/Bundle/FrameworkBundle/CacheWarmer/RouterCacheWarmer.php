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
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;
use Symfony\Component\HttpKernel\CacheWarmer\WarmableInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Service\ServiceSubscriberInterface;

/**
 * Generates the router matcher and generator classes.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @final
 */
class RouterCacheWarmer implements CacheWarmerInterface, ServiceSubscriberInterface
{
    private $container;

    public function __construct(ContainerInterface $container)
    {
        // As this cache warmer is optional, dependencies should be lazy-loaded, that's why a container should be injected.
        $this->container = $container;
    }

    /**
     * {@inheritdoc}
     *
     * @return string[]
     */
    public function warmUp(string $cacheDir)
    {
        $router = $this->container->get('router');

        if ($router instanceof WarmableInterface) {
            return (array) $router->warmUp($cacheDir);
        }

        throw new \LogicException(sprintf('The router "%s" cannot be warmed up because it does not implement "%s".', get_debug_type($router), WarmableInterface::class));
    }

    /**
     * Checks whether this warmer is optional or not.
     *
     * @return bool always true
     */
    public function isOptional(): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedServices(): array
    {
        return [
            'router' => RouterInterface::class,
        ];
    }
}
