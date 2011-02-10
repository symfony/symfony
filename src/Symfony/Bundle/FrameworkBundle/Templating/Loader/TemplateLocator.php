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

/**
 * TemplateLocator locates templates in bundles.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
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
     * @param array  $template The template name as an array
     * @param string $currentPath The current path
     *
     * @return string The full path for the file
     *
     * @throws \InvalidArgumentException When file is not found
     */
    public function locate($template, $currentPath = null, $first = true)
    {
        $key = md5(serialize($template));

        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        if (!$template['bundle']) {
            if (is_file($file = $this->path.'/views/'.$template['controller'].'/'.$template['name'].'.'.$template['format'].'.'.$template['engine'])) {
                return $this->cache[$key] = $file;
            }

            throw new \InvalidArgumentException(sprintf('Unable to find template "%s" in "%s".', json_encode($template), $this->path));
        }

        $resource = $template['bundle'].'/Resources/views/'.$template['controller'].'/'.$template['name'].'.'.$template['format'].'.'.$template['engine'];

        try {
            return $this->locator->locate('@'.$resource, $this->path);
        } catch (\Exception $e) {
            throw new \InvalidArgumentException(sprintf('Unable to find template "%s".', json_encode($template), $this->path), 0, $e);
        }
    }
}
