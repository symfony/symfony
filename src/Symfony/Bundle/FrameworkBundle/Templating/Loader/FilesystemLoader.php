<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Templating\Loader;

use Symfony\Component\Templating\Storage\FileStorage;
use Symfony\Component\Templating\Loader\LoaderInterface;
use Symfony\Component\Config\FileLocatorInterface;
use Symfony\Component\Templating\TemplateReferenceInterface;

/**
 * FilesystemLoader is a loader that read templates from the filesystem.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class FilesystemLoader implements LoaderInterface
{
    protected $locator;

    /**
     * Constructor.
     *
     * @param FileLocatorInterface $locator A FileLocatorInterface instance
     */
    public function __construct(FileLocatorInterface $locator)
    {
        $this->locator = $locator;
    }

    /**
     * Loads a template.
     *
     * @param TemplateReferenceInterface $template A template
     *
     * @return FileStorage|Boolean false if the template cannot be loaded, a Storage instance otherwise
     */
    public function load(TemplateReferenceInterface $template)
    {
        try {
            $file = $this->locator->locate($template);
        } catch (\InvalidArgumentException $e) {
            return false;
        }

        return new FileStorage($file);
    }

    /**
     * Returns true if the template is still fresh.
     *
     * @param TemplateReferenceInterface $template The template name as an array
     * @param integer                    $time     The last modification time of the cached template (timestamp)
     *
     * @return Boolean
     */
    public function isFresh(TemplateReferenceInterface $template, $time)
    {
        if (false === $storage = $this->load($template)) {
            return false;
        }

        if (!is_readable((string) $storage)) {
            return false;
        }

        return filemtime((string) $storage) < $time;
    }
}
