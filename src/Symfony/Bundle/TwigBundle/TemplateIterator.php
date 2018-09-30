<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\TwigBundle;

use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Iterator for all templates in bundles and in the application Resources directory.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class TemplateIterator implements \IteratorAggregate
{
    private $kernel;
    private $rootDir;
    private $templates;
    private $paths;
    private $defaultPath;

    /**
     * @param KernelInterface $kernel      A KernelInterface instance
     * @param string          $rootDir     The directory where global templates can be stored
     * @param array           $paths       Additional Twig paths to warm
     * @param string          $defaultPath The directory where global templates can be stored
     */
    public function __construct(KernelInterface $kernel, $rootDir, array $paths = array(), $defaultPath = null)
    {
        $this->kernel = $kernel;
        $this->rootDir = $rootDir;
        $this->paths = $paths;
        $this->defaultPath = $defaultPath;
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator()
    {
        if (null !== $this->templates) {
            return $this->templates;
        }

        $this->templates = array_merge(
            $this->findTemplatesInDirectory($this->rootDir.'/Resources/views'),
            $this->findTemplatesInDirectory($this->defaultPath, null, array('bundles'))
        );
        foreach ($this->kernel->getBundles() as $bundle) {
            $name = $bundle->getName();
            if ('Bundle' === substr($name, -6)) {
                $name = substr($name, 0, -6);
            }

            $this->templates = array_merge(
                $this->templates,
                $this->findTemplatesInDirectory($bundle->getPath().'/Resources/views', $name),
                $this->findTemplatesInDirectory($this->rootDir.'/Resources/'.$bundle->getName().'/views', $name),
                $this->findTemplatesInDirectory($this->defaultPath.'/bundles/'.$bundle->getName(), $name)
            );
        }

        foreach ($this->paths as $dir => $namespace) {
            $this->templates = array_merge($this->templates, $this->findTemplatesInDirectory($dir, $namespace));
        }

        return $this->templates = new \ArrayIterator(array_unique($this->templates));
    }

    /**
     * Find templates in the given directory.
     *
     * @param string      $dir       The directory where to look for templates
     * @param string|null $namespace The template namespace
     *
     * @return array
     */
    private function findTemplatesInDirectory($dir, $namespace = null, array $excludeDirs = array())
    {
        if (!is_dir($dir)) {
            return array();
        }

        $templates = array();
        foreach (Finder::create()->files()->followLinks()->in($dir)->exclude($excludeDirs) as $file) {
            $templates[] = (null !== $namespace ? '@'.$namespace.'/' : '').str_replace('\\', '/', $file->getRelativePathname());
        }

        return $templates;
    }
}
