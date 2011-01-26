<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Templating\Loader;

use Symfony\Component\Templating\Storage\FileStorage;
use Symfony\Component\Templating\Loader\LoaderInterface;

/**
 * FilesystemLoader is a loader that read templates from the filesystem.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class FilesystemLoader implements LoaderInterface
{
    protected $locator;

    /**
     * Constructor.
     *
     * @param TemplateLocatorInterface $locator A TemplateLocatorInterface instance
     */
    public function __construct(TemplateLocatorInterface $locator)
    {
        $this->locator = $locator;
    }

    /**
     * Loads a template.
     *
     * @param array $template The template name as an array
     *
     * @return Storage|Boolean false if the template cannot be loaded, a Storage instance otherwise
     */
    public function load($template)
    {
        if (false === $file = $this->locator->locate($template)) {
            return false;
        }

        return new FileStorage($file);
    }

    /**
     * Returns true if the template is still fresh.
     *
     * @param array     $template The template name as an array
     * @param timestamp $time     The last modification time of the cached template
     */
    public function isFresh($template, $time)
    {
        if (false === $template = $this->load($template)) {
            return false;
        }

        return filemtime((string) $template) < $time;
    }
}
