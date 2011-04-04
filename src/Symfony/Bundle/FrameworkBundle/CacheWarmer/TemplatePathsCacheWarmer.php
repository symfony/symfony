<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\CacheWarmer;

use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmer;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Bundle\FrameworkBundle\Templating\TemplateNameParser;

/**
 * Computes the association between template names and their paths on the disk.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class TemplatePathsCacheWarmer extends CacheWarmer
{
    protected $kernel;
    protected $rootDir;
    protected $parser;

    /**
     * Constructor.
     *
     * @param KernelInterface      $kernel  A KernelInterface instance
     * @param TemplateNameParser   $parser  A TemplateNameParser instance
     * @param string               $rootDir The directory where global templates can be stored
     */
    public function __construct(KernelInterface $kernel, TemplateNameParser $parser, $rootDir)
    {
        $this->kernel = $kernel;
        $this->parser = $parser;
        $this->rootDir = $rootDir;
    }

    /**
     * Warms up the cache.
     *
     * @param string $cacheDir The cache directory
     */
    public function warmUp($cacheDir)
    {
        $templates = array();

        foreach ($this->kernel->getBundles() as $name => $bundle) {
            $templates += $this->findTemplatesIn($this->rootDir.'/'.$name.'/views', $name);
            $templates += $this->findTemplatesIn($bundle->getPath().'/Resources/views', $name);
        }

        $templates += $this->findTemplatesIn($this->rootDir.'/views');

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

    /**
     * Find templates in the given directory
     *
     * @param string $dir       The folder where to look for templates
     * @param string $bundle    The name of the bundle (null when out of a bundle)
     *
     * @return array An array of template paths
     */
    protected function findTemplatesIn($dir, $bundle = null)
    {
        $templates = array();

        if (is_dir($dir)) {
            $finder = new Finder();
            foreach ($finder->files()->followLinks()->in($dir) as $file) {
                $template = $this->parser->parseFromFilename($file->getRelativePathname());
                if (false !== $template) {
                    if (null !== $bundle) {
                      $template->set('bundle', $bundle);
                    }
                    $templates[$template->getSignature()] = $file->getRealPath();
                }
            }
        }

        return $templates;
    }
}
