<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
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
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class CachedTemplateLocator implements FileLocatorInterface
{
    protected $templates;

    /**
     * Constructor.
     */
    public function __construct($cacheDir)
    {
        if (!file_exists($cache = $cacheDir.'/templates.php')) {
            throw new \RuntimeException(sprintf('The template locator cache is not warmed up (%s).', $cache));
        }

        $this->templates = require $cache;
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
            throw new \InvalidArgumentException(sprintf('Unable to find template "%s".', json_encode($template)));
        }

        return $this->templates[$key];
    }
}
