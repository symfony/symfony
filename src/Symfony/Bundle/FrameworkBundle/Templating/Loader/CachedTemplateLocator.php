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
 * CachedTemplateLocator locates templates in the cache.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class CachedTemplateLocator extends TemplateLocator
{
    protected $templates;

    /**
     * Constructor.
     *
     * @param string               $cacheDir The cache path
     * @param FileLocatorInterface $locator  A FileLocatorInterface instance
     * @param string               $path     A global fallback path
     */
    public function __construct($cacheDir, FileLocatorInterface $locator, $path)
    {
        if (!file_exists($cache = $cacheDir.'/templates.php')) {
            throw new \RuntimeException(sprintf('The template locator cache is not warmed up (%s).', $cache));
        }

        $this->templates = require $cache;
        parent::__construct($locator, $path);
    }

    /**
     * Returns a full path for a given file.
     *
     * @param TemplateReferenceInterface $template    The template
     * @param string                     $currentPath Unused
     * @param Boolean                    $first       Unused
     *
     * @return string The full path for the file
     *
     * @throws \InvalidArgumentException When file is not found
     */
    public function locate($template, $currentPath = null, $first = true)
    {
        $key = $template->getSignature();

        if (!isset($this->templates[$key])) {
            return parent::locate($template, $currentPath, $first);
        }

        return $this->templates[$key];
    }
}
