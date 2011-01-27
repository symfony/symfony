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

/**
 * CachedTemplateLocator locates templates in the cache.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class CachedTemplateLocator implements TemplateLocatorInterface
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
     * Locates a template on the filesystem.
     *
     * @param array $template The template name as an array
     *
     * @return string An absolute file name
     */
    public function locate($template)
    {
        $key = md5(serialize($template));

        if (!isset($this->templates[$key])) {
            throw new \InvalidArgumentException(sprintf('Unable to find template "%s".', json_encode($template)));
        }

        return $this->templates[$key];
    }
}
