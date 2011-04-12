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
    protected $path;
    protected $cache;

    /**
     * Constructor.
     *
     * @param FileLocatorInterface $locator A FileLocatorInterface instance
     * @param string               $path    A global fallback path
     */
    public function __construct(FileLocatorInterface $locator, $path)
    {
        $this->locator = $locator;
        $this->path = $path;
        $this->cache = array();
    }

    /**
     * Returns a full path for a given file.
     *
     * @param TemplateReferenceInterface    $template     A template
     * @param string                        $currentPath  Unused
     * @param Boolean                       $first        Unused
     *
     * @return string The full path for the file
     *
     * @throws \InvalidArgumentException When file is not found
     */
    public function locate($template, $currentPath = null, $first = true)
    {
        $key = $template->getSignature();

        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        try {
            return $this->cache[$key] = $this->locator->locate($template->getPath(), $this->path);
        } catch (\InvalidArgumentException $e) {
            throw new \InvalidArgumentException(sprintf('Unable to find template "%s" in "%s".', $template, $this->path), 0, $e);
        }
    }
}
