<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Templating\CacheWarmer;

use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmer;
use Symfony\Component\HttpKernel\Kernel;
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
     * @param Kernel $kernel  A Kernel instance
     * @param string $rootDir The directory where global templates can be stored
     */
    public function __construct(Kernel $kernel, $rootDir)
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
            foreach ($finder->files()->followLinks()->name('*.twig')->in($dir) as $file) {
                list($category, $template) = $this->parseTemplateName($file, $prefix.'/');
                $name = sprintf('%s:%s:%s', $bundle->getName(), $category, $template);
                $resource = '@'.$bundle->getName().$prefix.'/'.$category.'/'.$template;

                $templates[$name] = $this->kernel->locateResource($resource, $this->rootDir);
            }
        }

        return  $templates;
    }

    protected function parseTemplateName($file, $prefix)
    {
        $path = $file->getPathname();

        list(, $tmp) = explode($prefix, $path, 2);
        $parts = explode('/', strtr($tmp, '\\', '/'));
        $template = array_pop($parts);

        return array(implode('/', $parts), $template);
    }
}
