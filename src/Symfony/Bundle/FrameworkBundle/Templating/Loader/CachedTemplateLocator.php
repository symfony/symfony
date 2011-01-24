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

    public function locate($name)
    {
        if (!isset($this->templates[$name])) {
            throw new \InvalidArgumentException(sprintf('Unable to find template "%s".', $name));
        }

        return $this->templates[$name];
    }
}
