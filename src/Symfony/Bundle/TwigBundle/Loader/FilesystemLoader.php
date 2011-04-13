<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\TwigBundle\Loader;

use Symfony\Component\Templating\TemplateNameParserInterface;
use Symfony\Component\Config\FileLocatorInterface;
use Symfony\Component\Templating\TemplateReferenceInterface;

/**
 * FilesystemLoader extends the default Twig filesystem loader
 * to work with the Symfony2 paths.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class FilesystemLoader implements \Twig_LoaderInterface
{
    protected $locator;
    protected $parser;
    protected $cache;

    /**
     * Constructor.
     *
     * @param FileLocatorInterface $locator A FileLocatorInterface instance
     */
    public function __construct(FileLocatorInterface $locator, TemplateNameParserInterface $parser)
    {
        $this->locator = $locator;
        $this->parser = $parser;
        $this->cache = array();
    }

    /**
     * Gets the source code of a template, given its name.
     *
     * @param  mixed $name The template name or a TemplateReferenceInterface instance
     *
     * @return string The template source code
     */
    public function getSource($name)
    {
        return file_get_contents($this->findTemplate($name));
    }

    /**
     * Gets the cache key to use for the cache for a given template name.
     *
     * @param  mixed $name The template name or a TemplateReferenceInterface instance
     *
     * @return string The cache key
     */
    public function getCacheKey($name)
    {
        return $this->findTemplate($name);
    }

    /**
     * Returns true if the template is still fresh.
     *
     * @param mixed     $name The template name or a TemplateReferenceInterface instance
     * @param timestamp $time The last modification time of the cached template
     *
     * @throws \Twig_Error_Loader if the template does not exist
     */
    public function isFresh($name, $time)
    {
        return filemtime($this->findTemplate($name)) < $time;
    }

    /**
     * Returns the path to the template file
     *
     * @param $name The template logical name
     *
     * @return string The path to the template file
     */
    protected function findTemplate($name)
    {
        $tpl = $this->parser->parse($name);

        if (isset($this->cache[$key = $tpl->getSignature()])) {
            return $this->cache[$key];
        }

        $file = null;
        $previous = null;
        try {
            $file = $this->locator->locate($tpl);
        } catch (\InvalidArgumentException $e) {
            $previous = $e;
        }

        if (false === $file || null === $file) {
            throw new \Twig_Error_Loader(sprintf('Unable to find template "%s".', $tpl), -1, null, $previous);
        }

        return $this->cache[$key] = $file;
    }
}
