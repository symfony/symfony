<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\CacheWarmer;

use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmer;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Finder\Finder;

/**
 * Computes the association between template names and their paths on the disk.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class TemplatePathsCacheWarmer extends CacheWarmer
{
    protected $kernel;
    protected $rootDir;

    /**
     * Constructor.
     *
     * @param KernelInterface $kernel  A KernelInterface instance
     * @param string          $rootDir The directory where global templates can be stored
     */
    public function __construct(KernelInterface $kernel, $rootDir)
    {
        $this->kernel = $kernel;
        $this->rootDir = $rootDir;
    }

    /**
     * Warms up the cache.
     *
     * @param string $cacheDir The cache directory
     */
    public function warmUp($cacheDir)
    {
        $templates = $this->computeTemplatePaths();

        $this->writeCacheFile($cacheDir.'/templates.php', sprintf('<?php return %s;', var_export($templates, true)));
    }

    /**
     * Checks whether this warmer is optional or not.
     *
     * @return Boolean always false
     */
    public function isOptional()
    {
        return false;
    }

    protected function computeTemplatePaths()
    {
        $prefix = '/Resources/views';
        $templates = array();
        foreach ($this->kernel->getBundles() as $name => $bundle) {
            if (!is_dir($dir = $bundle->getPath().$prefix)) {
                continue;
            }

            $finder = new Finder();
            foreach ($finder->files()->followLinks()->in($dir) as $file) {
                if (false !== $template = $this->parseTemplateName($file, $prefix.'/', $bundle->getName())) {
                    $resource = '@'.$template['bundle'].'/Resources/views/'.$template['controller'].'/'.$template['name'].'.'.$template['format'].'.'.$template['engine'];

                    $templates[md5(serialize($template))] = $this->kernel->locateResource($resource, $this->rootDir);
                }
            }
        }

        if (is_dir($this->rootDir)) {
            $finder = new Finder();
            foreach ($finder->files()->followLinks()->in($this->rootDir) as $file) {
                if (false !== $template = $this->parseTemplateName($file, strtr($this->rootDir, '\\', '/').'/')) {
                    $templates[md5(serialize($template))] = (string) $file;
                }
            }
        }

        return  $templates;
    }

    protected function parseTemplateName($file, $prefix, $bundle = '')
    {
        $path = strtr($file->getPathname(), '\\', '/');

        list(, $tmp) = explode($prefix, $path, 2);
        $parts = explode('/', strtr($tmp, '\\', '/'));

        $elements = explode('.', array_pop($parts));
        if (3 !== count($elements)) {
            return false;
        }

        return array(
            'bundle'     => $bundle,
            'controller' => implode('/', $parts),
            'name'       => $elements[0],
            'format'     => $elements[1],
            'engine'     => $elements[2],
        );
    }
}
