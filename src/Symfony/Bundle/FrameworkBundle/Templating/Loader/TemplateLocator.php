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

use Symfony\Component\HttpKernel\KernelInterface;

/**
 * TemplateLocator locates templates in bundles.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class TemplateLocator implements TemplateLocatorInterface
{
    protected $kernel;
    protected $path;
    protected $cache;

    /**
     * Constructor.
     *
     * @param KernelInterface $kernel A KernelInterface instance
     * @param string          $path   A global fallback path
     */
    public function __construct(KernelInterface $kernel, $path)
    {
        $this->kernel = $kernel;
        $this->path = $path;
        $this->cache = array();
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
            return $this->kernel->locateResource('@'.$resource, $this->path);
        } catch (\Exception $e) {
            throw new \InvalidArgumentException(sprintf('Unable to find template "%s".', json_encode($template), $this->path), 0, $e);
        }
    }
}
