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
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Finder\Finder;

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
    protected $parser;
    protected $kernel;

    /**
     * Constructor.
     *
     * @param ContainerInterface $container The dependency injection container
     * @param string             $rootDir The directory where global templates can be stored
     */
    public function __construct(ContainerInterface $container, $rootDir)
    {
        // we don't inject the Twig environment directly as it needs
        // the loader, which is a cached one, and the cache is not
        // yet available when this instance is created (the
        // TemplateCacheCacheWarmer has not been run yet).
        $this->container = $container;
        $this->parser = $container->get('templating.name_parser');
        $this->kernel = $container->get('kernel');
        $this->rootDir = $rootDir;
    }

    /**
     * Warms up the cache.
     *
     * @param string $cacheDir The cache directory
     */
    public function warmUp($cacheDir)
    {
        $twig = $this->container->get('twig');

        foreach ($this->kernel->getBundles() as $name => $bundle) {
            foreach ($this->findTemplatesIn($bundle->getPath().'/Resources/views', $name) as $template) {
                $twig->loadTemplate($template);
            }
        }

        foreach ($this->findTemplatesIn($this->rootDir.'/views') as $template) {
            $twig->loadTemplate($template);
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

    /**
     * Find templates in the given directory
     *
     * @param string $dir       The folder where to look for templates
     * @param string $bundle    The name of the bundle (null when out of a bundle)
     *
     * @return array An array of TemplateReference
     */
    protected function findTemplatesIn($dir, $bundle = null)
    {
        $templates = array();

        if (is_dir($dir)) {
            $finder = new Finder();
            foreach ($finder->files()->followLinks()->in($dir) as $file) {
                $template = $this->parser->parseFromFilename($file->getRelativePathname());
                if (false !== $template && 'twig' == $template->get('engine')) {
                    if (null !== $bundle) {
                      $template->set('bundle', $bundle);
                    }
                    $templates[] = $template->getLogicalName();
                }
            }
        }

        return $templates;
    }
}
