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
use Symfony\Component\Config\FileLocatorInterface;
use Symfony\Bundle\FrameworkBundle\Templating\Template;
use Symfony\Bundle\FrameworkBundle\Templating\TemplateNameParser;

/**
 * Computes the association between template names and their paths on the disk.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class TemplatePathsCacheWarmer extends CacheWarmer
{
    protected $locator;
    protected $kernel;
    protected $rootDir;
    protected $parser;

    /**
     * Constructor.
     *
     * @param KernelInterface      $kernel  A KernelInterface instance
     * @param FileLocatorInterface $locator A FileLocatorInterface instance
     * @param TemplateNameParser   $parser  A TemplateNameParser instance
     * @param string               $rootDir The directory where global templates can be stored
     */
    public function __construct(KernelInterface $kernel, FileLocatorInterface $locator, TemplateNameParser $parser, $rootDir)
    {
        $this->kernel = $kernel;
        $this->locator = $locator;
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
                $template = $this->parser->parseFromFilename($file->getRelativePathname());
                if (false !== $template) {
                    $template->set('bundle', $name);
                    $templates[$template->getSignature()] = $this->locator->locate($template->getPath(), $this->rootDir);
                }
            }
        }

        if (is_dir($this->rootDir)) {
            $finder = new Finder();
            foreach ($finder->files()->followLinks()->in($this->rootDir) as $file) {
                $template = $this->parser->parseFromFilename($file->getRelativePathname());
                if (false !== $template) {
                    $templates[$template->getSignature()] = $file->getRealPath();
                }
            }
        }

        return  $templates;
    }
}
