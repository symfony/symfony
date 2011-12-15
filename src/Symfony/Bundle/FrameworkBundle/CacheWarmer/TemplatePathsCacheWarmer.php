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

use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmer;
use Symfony\Bundle\FrameworkBundle\Templating\Loader\TemplateLocator;

/**
 * Computes the association between template names and their paths on the disk.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class TemplatePathsCacheWarmer extends CacheWarmer
{
    protected $finder;
    protected $locator;

    /**
     * Constructor.
     *
     * @param TemplateFinderInterface   $finder  A template finder
     * @param TemplateLocator           $locator The template locator
     */
    public function __construct(TemplateFinderInterface $finder, TemplateLocator $locator)
    {
        $this->finder = $finder;
        $this->locator = $locator;
    }

    /**
     * Warms up the cache.
     *
     * @param string $cacheDir The cache directory
     */
    public function warmUp($cacheDir)
    {
        $templates = array();

        foreach ($this->finder->findAllTemplates() as $template) {
            $templates[$template->getLogicalName()] = $this->locator->locate($template);
        }

        $this->writeCacheFile($cacheDir.'/templates.php', sprintf('<?php return %s;', var_export($templates, true)));
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
