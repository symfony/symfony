<?php

namespace Symfony\Bundle\TwigBundle\Loader;

use Symfony\Component\Templating\Storage\Storage;
use Symfony\Component\Templating\Storage\FileStorage;
use Symfony\Bundle\FrameworkBundle\Templating\TemplateNameConverter;
use Symfony\Component\Templating\Loader\LoaderInterface;

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class Loader implements \Twig_LoaderInterface
{
    protected $converter;
    protected $loader;

    public function __construct(TemplateNameConverter $converter, LoaderInterface $loader)
    {
        $this->converter = $converter;
        $this->loader = $loader;
    }

    /**
     * Gets the source code of a template, given its name.
     *
     * @param  string $name string The name of the template to load
     *
     * @return string The template source code
     */
    public function getSource($name)
    {
        if ($name instanceof Storage) {
            return $name->getContent();
        }

        list($name, $options) = $this->converter->fromShortNotation($name);

        $template = $this->loader->load($name, $options);

        if (false === $template) {
            throw new \InvalidArgumentException(sprintf('The template "%s" does not exist (renderer: %s).', $name, $options['renderer']));
        }

        return $template->getContent();
    }

    /**
     * Gets the cache key to use for the cache for a given template name.
     *
     * @param  string $name string The name of the template to load
     *
     * @return string The cache key
     */
    public function getCacheKey($name)
    {
        if ($name instanceof Storage) {
            return (string) $name;
        }

        list($name, $options) = $this->converter->fromShortNotation($name);

        return $name.'_'.serialize($options);
    }

    /**
     * Returns true if the template is still fresh.
     *
     * @param string    $name The template name
     * @param timestamp $time The last modification time of the cached template
     */
    public function isFresh($name, $time)
    {
        if ($name instanceof Storage) {
            if ($name instanceof FileStorage) {
                return filemtime((string) $name) < $time;
            }

            return false;
        }

        list($name, $options) = $this->converter->fromShortNotation($name);

        return $this->loader->isFresh($name, $options, $time);
    }
}
