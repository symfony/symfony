<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Templating\Loader;

use Symfony\Component\Templating\Storage\Storage;
use Symfony\Component\Templating\TemplateReferenceInterface;

/**
 * ChainLoader is a loader that calls other loaders to load templates.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ChainLoader extends Loader
{
    protected $loaders;

    /**
     * Constructor.
     *
     * @param LoaderInterface[] $loaders An array of loader instances
     */
    public function __construct(array $loaders = array())
    {
        $this->loaders = array();
        foreach ($loaders as $loader) {
            $this->addLoader($loader);
        }
    }

    /**
     * Adds a loader instance.
     *
     * @param LoaderInterface $loader A Loader instance
     */
    public function addLoader(LoaderInterface $loader)
    {
        $this->loaders[] = $loader;
    }

    /**
     * Loads a template.
     *
     * @param TemplateReferenceInterface $template A template
     *
     * @return Storage|bool false if the template cannot be loaded, a Storage instance otherwise
     */
    public function load(TemplateReferenceInterface $template)
    {
        foreach ($this->loaders as $loader) {
            if (false !== $storage = $loader->load($template)) {
                return $storage;
            }
        }

        return false;
    }

    /**
     * Returns true if the template is still fresh.
     *
     * @param TemplateReferenceInterface $template A template
     * @param int                        $time     The last modification time of the cached template (timestamp)
     *
     * @return bool
     */
    public function isFresh(TemplateReferenceInterface $template, $time)
    {
        foreach ($this->loaders as $loader) {
            return $loader->isFresh($template, $time);
        }

        return false;
    }
}
