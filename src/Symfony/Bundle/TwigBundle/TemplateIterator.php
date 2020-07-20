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
 *
 * @internal
 */
class TemplateIterator implements \IteratorAggregate
{
    private $kernel;
    private $templates;
    private $paths;
    private $defaultPath;

    /**
     * @param array       $paths       Additional Twig paths to warm
     * @param string|null $defaultPath The directory where global templates can be stored
     */
    public function __construct(KernelInterface $kernel, array $paths = [], string $defaultPath = null)
    {
        $this->kernel = $kernel;
        $this->paths = $paths;
        $this->defaultPath = $defaultPath;
    }

    public function getIterator(): \Traversable
    {
        if (null !== $this->templates) {
            return $this->templates;
        }

        $templates = null !== $this->defaultPath ? $this->findTemplatesInDirectory($this->defaultPath, null, ['bundles']) : [];

        foreach ($this->kernel->getBundles() as $bundle) {
            $name = $bundle->getName();
            if ('Bundle' === substr($name, -6)) {
                $name = substr($name, 0, -6);
            }

            $bundleTemplatesDir = is_dir($bundle->getPath().'/Resources/views') ? $bundle->getPath().'/Resources/views' : $bundle->getPath().'/templates';

            $templates = array_merge(
                $templates,
                $this->findTemplatesInDirectory($bundleTemplatesDir, $name),
                null !== $this->defaultPath ? $this->findTemplatesInDirectory($this->defaultPath.'/bundles/'.$bundle->getName(), $name) : []
            );
        }

        foreach ($this->paths as $dir => $namespace) {
            $templates = array_merge($templates, $this->findTemplatesInDirectory($dir, $namespace));
        }

        return $this->templates = new \ArrayIterator(array_unique($templates));
    }

    /**
     * Find templates in the given directory.
     *
     * @return string[]
     */
    private function findTemplatesInDirectory(string $dir, string $namespace = null, array $excludeDirs = []): array
    {
        if (!is_dir($dir)) {
            return [];
        }

        $templates = [];
        foreach (Finder::create()->files()->followLinks()->in($dir)->exclude($excludeDirs) as $file) {
            $templates[] = (null !== $namespace ? '@'.$namespace.'/' : '').str_replace('\\', '/', $file->getRelativePathname());
        }

        return $templates;
    }
}
