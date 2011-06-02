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

use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;
use Symfony\Bundle\FrameworkBundle\CacheWarmer\TemplatePathsCacheWarmer;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Bundle\FrameworkBundle\Templating\Loader\TemplateFinder;

/**
 * Generates the Twig cache for all templates.
 *
 * This warmer must be registered after TemplatePathsCacheWarmer,
 * as the Twig loader will need the cache generated by it.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class TemplateCacheCacheWarmer implements CacheWarmerInterface
{
    protected $container;
    protected $warmer;

    /**
     * Constructor.
     *
     * @param ContainerInterface $container The dependency injection container
     * @param TemplateFinder     $finder    The template paths cache warmer
     */
    public function __construct(ContainerInterface $container, TemplateFinder $finder)
    {
        // We don't inject the Twig environment directly as it depends on the
        // template locator (via the loader) which might be a cached one.
        // The cached template locator is available once the TemplatePathsCacheWarmer
        // has been warmed up
        $this->container = $container;
        $this->finder = $finder;
    }

    /**
     * Warms up the cache.
     *
     * @param string $cacheDir The cache directory
     */
    public function warmUp($cacheDir)
    {
        $twig = $this->container->get('twig');

        foreach ($this->finder->findAllTemplates() as $template) {
            if ('twig' === $template->get('engine')) {
                $twig->loadTemplate($template);
            }
        }
    }

    /**
     * Checks whether this warmer is optional or not.
     *
     * @return Boolean always true
     */
    public function isOptional()
    {
        return true;
    }
}
