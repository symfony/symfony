<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Templating\Loader;

use Symfony\Component\Templating\Storage;

/**
 * ChainLoader is a loader that calls other loaders to load templates.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class ChainLoader extends Loader
{
    protected $loaders;

    /**
     * Constructor.
     *
     * @param Loader[] $loaders An array of loader instances
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
     * @param Loader $loader A Loader instance
     */
    public function addLoader(Loader $loader)
    {
        $this->loaders[] = $loader;
    }

    /**
     * Loads a template.
     *
     * @param string $template The logical template name
     *
     * @return Storage|Boolean false if the template cannot be loaded, a Storage instance otherwise
     */
    public function load($template)
    {
        foreach ($this->loaders as $loader) {
            if (false !== $ret = $loader->load($template)) {
                return $ret;
            }
        }

        return false;
    }

    /**
     * Returns true if the template is still fresh.
     *
     * @param string    $template The template name
     * @param timestamp $time     The last modification time of the cached template
     */
    public function isFresh($template, $time)
    {
        foreach ($this->loaders as $loader) {
            return $loader->isFresh($template);
        }

        return false;
    }
}
