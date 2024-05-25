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
 *
 * @implements \IteratorAggregate<int, string>
 */
class TemplateIterator implements \IteratorAggregate
{
    private \Traversable $templates;

    /**
     * @param array       $paths        Additional Twig paths to warm
     * @param string|null $defaultPath  The directory where global templates can be stored
     * @param string[]    $namePatterns Pattern of file names
     */
    public function __construct(
        private KernelInterface $kernel,
        private array $paths = [],
        private ?string $defaultPath = null,
        private array $namePatterns = [],
    ) {
    }

    public function getIterator(): \Traversable
    {
        if (isset($this->templates)) {
            return $this->templates;
        }

        $templates = null !== $this->defaultPath ? [$this->findTemplatesInDirectory($this->defaultPath, null, ['bundles'])] : [];

        foreach ($this->kernel->getBundles() as $bundle) {
            $name = $bundle->getName();
            if (str_ends_with($name, 'Bundle')) {
                $name = substr($name, 0, -6);
            }

            $bundleTemplatesDir = is_dir($bundle->getPath().'/Resources/views') ? $bundle->getPath().'/Resources/views' : $bundle->getPath().'/templates';

            $templates[] = $this->findTemplatesInDirectory($bundleTemplatesDir, $name);
            if (null !== $this->defaultPath) {
                $templates[] = $this->findTemplatesInDirectory($this->defaultPath.'/bundles/'.$bundle->getName(), $name);
            }
        }

        foreach ($this->paths as $dir => $namespace) {
            $templates[] = $this->findTemplatesInDirectory($dir, $namespace);
        }

        return $this->templates = new \ArrayIterator(array_unique(array_merge([], ...$templates)));
    }

    /**
     * Find templates in the given directory.
     *
     * @return string[]
     */
    private function findTemplatesInDirectory(string $dir, ?string $namespace = null, array $excludeDirs = []): array
    {
        if (!is_dir($dir)) {
            return [];
        }

        $templates = [];
        foreach (Finder::create()->files()->followLinks()->in($dir)->exclude($excludeDirs)->name($this->namePatterns) as $file) {
            $templates[] = (null !== $namespace ? '@'.$namespace.'/' : '').str_replace('\\', '/', $file->getRelativePathname());
        }

        return $templates;
    }
}
