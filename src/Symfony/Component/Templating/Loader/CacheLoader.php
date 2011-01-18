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

use Symfony\Component\Templating\Storage\Storage;
use Symfony\Component\Templating\Storage\FileStorage;

/**
 * CacheLoader is a loader that caches other loaders responses
 * on the filesystem.
 *
 * This cache only caches on disk to allow PHP accelerators to cache the opcodes.
 * All other mechanism would imply the use of `eval()`.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class CacheLoader extends Loader
{
    protected $loader;
    protected $dir;

    /**
     * Constructor.
     *
     * @param Loader $loader A Loader instance
     * @param string $dir    The directory where to store the cache files
     */
    public function __construct(Loader $loader, $dir)
    {
        parent::__construct($loader->getTemplateNameParser());

        $this->loader = $loader;
        $this->dir = $dir;
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
        $parameters = $this->nameParser->parse($template);

        $tmp = md5(serialize($parameters)).'.tpl';
        $dir = $this->dir.DIRECTORY_SEPARATOR.substr($tmp, 0, 2);
        $file = substr($tmp, 2);
        $path = $dir.DIRECTORY_SEPARATOR.$file;

        if (file_exists($path)) {
            if (null !== $this->debugger) {
                $this->debugger->log(sprintf('Fetching template "%s" from cache', $parameters['name']));
            }

            return new FileStorage($path);
        }

        if (false === $storage = $this->loader->load($parameters['name'], $parameters)) {
            return false;
        }

        $content = $storage->getContent();

        if (!file_exists($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents($path, $content);

        if (null !== $this->debugger) {
            $this->debugger->log(sprintf('Storing template "%s" in cache', $parameters['name']));
        }

        return new FileStorage($path);
    }

    /**
     * Returns true if the template is still fresh.
     *
     * @param string    $template The template name
     * @param timestamp $time     The last modification time of the cached template
     */
    public function isFresh($template, $time)
    {
        return $this->loader->isFresh($template, $time);
    }
}
