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
     * @param array       $paths          Additional Twig paths to warm
     * @param string|null $defaultPath    The directory where global templates can be stored
     * @param string[]    $namePatterns   Pattern of file names
     * @param string|null $formThemesPath A directory of form themes to warm only when they are default ones or when a warmed template names them
     * @param string[]    $formThemes     The default form themes
     */
    public function __construct(
        private KernelInterface $kernel,
        private array $paths = [],
        private ?string $defaultPath = null,
        private array $namePatterns = [],
        private ?string $formThemesPath = null,
        private array $formThemes = [],
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

            /*
             * The bundle's own templates are also registered with the "!" prefix namespace - this matches
             * @see \Symfony\Bundle\TwigBundle\DependencyInjection\TwigExtension::load()
             */
            $templates[] = $this->findTemplatesInDirectory($bundleTemplatesDir, '!'.$name);
        }

        foreach ($this->paths as $dir => $namespace) {
            if ($dir !== $this->formThemesPath) {
                $templates[] = $this->findTemplatesInDirectory($dir, $namespace);
            }
        }

        if (null !== $this->formThemesPath) {
            $templates[] = $this->findReachableFormThemes(array_keys(array_merge([], ...$templates)));
        }

        return $this->templates = new \ArrayIterator(array_unique(array_merge([], ...array_map(array_values(...), $templates))));
    }

    /**
     * Find templates in the given directory.
     *
     * @return array<string, string> Template names, keyed by file path
     */
    private function findTemplatesInDirectory(string $dir, ?string $namespace = null, array $excludeDirs = []): array
    {
        if (!is_dir($dir)) {
            return [];
        }

        $templates = [];
        foreach (Finder::create()->files()->followLinks()->in($dir)->exclude($excludeDirs)->name($this->namePatterns) as $file) {
            $templates[$file->getPathname()] = (null !== $namespace ? '@'.$namespace.'/' : '').str_replace('\\', '/', $file->getRelativePathname());
        }

        return $templates;
    }

    /**
     * Finds the form themes that are default ones or that the given templates name, and the ones these form themes name in turn.
     *
     * @param string[] $files The paths of the templates
     *
     * @return array<string, string> Form theme names, keyed by file path
     */
    private function findReachableFormThemes(array $files): array
    {
        $formThemes = array_flip($this->findTemplatesInDirectory($this->formThemesPath));
        $reachedThemes = array_intersect_key($formThemes, array_flip($this->formThemes));
        $formThemes = array_diff_key($formThemes, $reachedThemes);
        $files = array_merge($files, array_values($reachedThemes));

        while ($formThemes && $files) {
            $code = file_get_contents(array_pop($files));

            foreach ($formThemes as $name => $file) {
                if (str_contains($code, $name)) {
                    $reachedThemes[$name] = $files[] = $file;
                    unset($formThemes[$name]);
                }
            }
        }

        return array_flip($reachedThemes);
    }
}
