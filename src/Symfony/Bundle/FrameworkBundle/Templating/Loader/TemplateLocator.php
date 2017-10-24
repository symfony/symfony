<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Templating\Loader;

use Symfony\Component\Config\FileLocatorInterface;
use Symfony\Component\Templating\TemplateReferenceInterface;

/**
 * TemplateLocator locates templates in bundles.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class TemplateLocator implements FileLocatorInterface
{
    protected $locator;
    protected $cache;

    private $cacheHits = array();

    /**
     * @param FileLocatorInterface $locator  A FileLocatorInterface instance
     * @param string               $cacheDir The cache path
     */
    public function __construct(FileLocatorInterface $locator, $cacheDir = null)
    {
        if (null !== $cacheDir && file_exists($cache = $cacheDir.'/templates.php')) {
            $this->cache = require $cache;
        }

        $this->locator = $locator;
    }

    /**
     * Returns a full path for a given file.
     *
     * @return string The full path for the file
     */
    protected function getCacheKey($template)
    {
        return $template->getLogicalName();
    }

    /**
     * Returns a full path for a given file.
     *
     * @param TemplateReferenceInterface $template    A template
     * @param string                     $currentPath Unused
     * @param bool                       $first       Unused
     *
     * @return string The full path for the file
     *
     * @throws \InvalidArgumentException When the template is not an instance of TemplateReferenceInterface
     * @throws \InvalidArgumentException When the template file can not be found
     */
    public function locate($template, $currentPath = null, $first = true)
    {
        if (!$template instanceof TemplateReferenceInterface) {
            throw new \InvalidArgumentException('The template must be an instance of TemplateReferenceInterface.');
        }

        $key = $this->getCacheKey($template);

        if (isset($this->cacheHits[$key])) {
            return $this->cacheHits[$key];
        }
        if (isset($this->cache[$key])) {
            return $this->cacheHits[$key] = realpath($this->cache[$key]) ?: $this->cache[$key];
        }

        try {
            return $this->cacheHits[$key] = $this->locator->locate($template->getPath(), $currentPath);
        } catch (\InvalidArgumentException $e) {
            throw new \InvalidArgumentException(sprintf('Unable to find template "%s" : "%s".', $template, $e->getMessage()), 0, $e);
        }
    }
}
