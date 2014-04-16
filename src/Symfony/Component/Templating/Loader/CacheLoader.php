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
use Symfony\Component\Templating\Storage\FileStorage;
use Symfony\Component\Templating\TemplateReferenceInterface;

/**
 * CacheLoader is a loader that caches other loaders responses
 * on the filesystem.
 *
 * This cache only caches on disk to allow PHP accelerators to cache the opcodes.
 * All other mechanism would imply the use of `eval()`.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class CacheLoader extends Loader
{
    protected $loader;
    protected $dir;

    /**
     * Constructor.
     *
     * @param LoaderInterface $loader A Loader instance
     * @param string          $dir    The directory where to store the cache files
     */
    public function __construct(LoaderInterface $loader, $dir)
    {
        $this->loader = $loader;
        $this->dir = $dir;
    }

    /**
     * Loads a template.
     *
     * @param TemplateReferenceInterface $template A template
     *
     * @return Storage|bool    false if the template cannot be loaded, a Storage instance otherwise
     */
    public function load(TemplateReferenceInterface $template)
    {
        $key = md5($template->getLogicalName());
        $dir = $this->dir.DIRECTORY_SEPARATOR.substr($key, 0, 2);
        $file = substr($key, 2).'.tpl';
        $path = $dir.DIRECTORY_SEPARATOR.$file;

        if (is_file($path)) {
            if (null !== $this->debugger) {
                $this->debugger->log(sprintf('Fetching template "%s" from cache', $template->get('name')));
            }

            return new FileStorage($path);
        }

        if (false === $storage = $this->loader->load($template)) {
            return false;
        }

        $content = $storage->getContent();

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents($path, $content);

        if (null !== $this->debugger) {
            $this->debugger->log(sprintf('Storing template "%s" in cache', $template->get('name')));
        }

        return new FileStorage($path);
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
        return $this->loader->isFresh($template, $time);
    }
}
