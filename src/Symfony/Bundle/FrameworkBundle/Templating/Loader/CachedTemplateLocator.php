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
     */
    public function __construct($cacheDir, FileLocatorInterface $locator)
    {
        if (!file_exists($cache = $cacheDir.'/templates.php')) {
            throw new \RuntimeException(sprintf('The template locator cache is not warmed up (%s).', $cache));
        }

        $this->templates = require $cache;

        parent::__construct($locator);
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
     * @throws \InvalidArgumentException When the template is not an instance of TemplateReferenceInterface
     * @throws \InvalidArgumentException When file is not found
     */
    public function locate($template, $currentPath = null, $first = true)
    {
        if (!$template instanceof TemplateReferenceInterface) {
            throw new \InvalidArgumentException("The template must be an instance of TemplateReferenceInterface.");
        }

        $path = $this->getCachedTemplatePath($template);

        return $path === null ? parent::locate($template) : $path;
    }

    /**
     * Returns the template path from the cache
     *
     * @param TemplateReferenceInterface $template The template
     *
     * @return string|null The path when it is present in the cache, false otherwise
     */
    protected function getCachedTemplatePath(TemplateReferenceInterface $template)
    {
        $key = $template->getSignature();
        return isset($this->templates[$key]) ? $this->templates[$key] : null;
    }
}
