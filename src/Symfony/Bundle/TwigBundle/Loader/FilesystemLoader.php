<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\TwigBundle\Loader;

use Symfony\Bundle\FrameworkBundle\Templating\Loader\TemplateLocatorInterface;
use Symfony\Component\Templating\TemplateNameParserInterface;

/**
 * FilesystemLoader extends the default Twig filesystem loader
 * to work with the Symfony2 paths.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class FilesystemLoader implements \Twig_LoaderInterface
{
    protected $locator;
    protected $parser;
    protected $cache;

    /**
     * Constructor.
     *
     * @param TemplateLocator $locator A TemplateLocator instance
     */
    public function __construct(TemplateLocatorInterface $locator, TemplateNameParserInterface $parser)
    {
        $this->locator = $locator;
        $this->parser = $parser;
        $this->cache = array();
    }

    /**
     * Gets the source code of a template, given its name.
     *
     * @param  string $name The name of the template to load
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
     * @param  string $name The name of the template to load
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
     * @param string    $name The template name
     * @param timestamp $time The last modification time of the cached template
     */
    public function isFresh($name, $time)
    {
        return filemtime($this->findTemplate($name)) < $time;
    }

    protected function findTemplate($name)
    {
        $tpl = is_array($name) ? $name : $this->parser->parse($name);

        $key = md5(serialize($tpl));
        if (isset($this->cache[$key])) {
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
            throw new \Twig_Error_Loader(sprintf('Unable to find template "%s".', json_encode($name)), 0, null, $previous);
        }

        return $this->cache[$key] = $file;
    }
}
