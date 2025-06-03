<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\TwigBundle\CacheWarmer;

use Psr\Container\ContainerInterface;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;
use Symfony\Contracts\Service\ServiceSubscriberInterface;
use Twig\Cache\CacheInterface;
use Twig\Cache\NullCache;
use Twig\Environment;
use Twig\Error\Error;

/**
 * Generates the Twig cache for all templates.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @final since Symfony 7.1
 */
class TemplateCacheWarmer implements CacheWarmerInterface, ServiceSubscriberInterface
{
    private Environment $twig;

    /**
     * As this cache warmer is optional, dependencies should be lazy-loaded, that's why a container should be injected.
     */
    public function __construct(
        private ContainerInterface $container,
        private iterable $iterator,
        private ?CacheInterface $cache = null,
    ) {
    }

    public function warmUp(string $cacheDir, ?string $buildDir = null): array
    {
        $this->twig ??= $this->container->get('twig');

        $originalCache = $this->twig->getCache();
        if ($originalCache instanceof NullCache) {
            // There's no point to warm up a cache that won't be used afterward
            return [];
        }

        if (null !== $this->cache) {
            if (!$buildDir) {
                /*
                 * The cache has already been warmup during the build of the container, when $buildDir was set.
                 */
                return [];
            }
            // Swap the cache for the warmup as the Twig Environment has the ChainCache injected
            $this->twig->setCache($this->cache);
        }

        try {
            foreach ($this->iterator as $template) {
                try {
                    $this->twig->load($template);
                } catch (Error) {
                    /*
                     * Problem during compilation, give up for this template (e.g. syntax errors).
                     * Failing silently here allows to ignore templates that rely on functions that aren't available in
                     * the current environment. For example, the WebProfilerBundle shouldn't be available in the prod
                     * environment, but some templates that are never used in prod might rely on functions the bundle provides.
                     * As we can't detect which templates are "really" important, we try to load all of them and ignore
                     * errors. Error checks may be performed by calling the lint:twig command.
                     */
                }
            }
        } finally {
            $this->twig->setCache($originalCache);
        }

        return [];
    }

    public function isOptional(): bool
    {
        return true;
    }

    public static function getSubscribedServices(): array
    {
        return [
            'twig' => Environment::class,
        ];
    }
}
