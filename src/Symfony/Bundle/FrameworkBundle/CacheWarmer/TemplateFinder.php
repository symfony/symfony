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

@trigger_error('The '.TemplateFinder::class.' class is deprecated since version 4.3 and will be removed in 5.0; use Twig instead.', E_USER_DEPRECATED);

use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Templating\TemplateNameParserInterface;
use Symfony\Component\Templating\TemplateReferenceInterface;

/**
 * Finds all the templates.
 *
 * @author Victor Berchet <victor@suumit.com>
 *
 * @deprecated since version 4.3, to be removed in 5.0; use Twig instead.
 */
class TemplateFinder implements TemplateFinderInterface
{
    private $kernel;
    private $parser;
    private $rootDir;
    private $templates;

    /**
     * @param string $rootDir The directory where global templates can be stored
     */
    public function __construct(KernelInterface $kernel, TemplateNameParserInterface $parser, string $rootDir)
    {
        $this->kernel = $kernel;
        $this->parser = $parser;
        $this->rootDir = $rootDir;
    }

    /**
     * Find all the templates in the bundle and in the kernel Resources folder.
     *
     * @return TemplateReferenceInterface[]
     */
    public function findAllTemplates()
    {
        if (null !== $this->templates) {
            return $this->templates;
        }

        $templates = [];

        foreach ($this->kernel->getBundles() as $bundle) {
            $templates = array_merge($templates, $this->findTemplatesInBundle($bundle));
        }

        $templates = array_merge($templates, $this->findTemplatesInFolder($this->rootDir.'/views'));

        return $this->templates = $templates;
    }

    /**
     * Find templates in the given directory.
     *
     * @return TemplateReferenceInterface[]
     */
    private function findTemplatesInFolder(string $dir): array
    {
        $templates = [];

        if (is_dir($dir)) {
            $finder = new Finder();
            foreach ($finder->files()->followLinks()->in($dir) as $file) {
                $template = $this->parser->parse($file->getRelativePathname());
                if (false !== $template) {
                    $templates[] = $template;
                }
            }
        }

        return $templates;
    }

    /**
     * Find templates in the given bundle.
     *
     * @param BundleInterface $bundle The bundle where to look for templates
     *
     * @return TemplateReferenceInterface[]
     */
    private function findTemplatesInBundle(BundleInterface $bundle): array
    {
        $name = $bundle->getName();
        $templates = array_unique(array_merge(
            $this->findTemplatesInFolder($bundle->getPath().'/Resources/views'),
            $this->findTemplatesInFolder($this->rootDir.'/'.$name.'/views')
        ));

        foreach ($templates as $i => $template) {
            $templates[$i] = $template->set('bundle', $name);
        }

        return $templates;
    }
}
