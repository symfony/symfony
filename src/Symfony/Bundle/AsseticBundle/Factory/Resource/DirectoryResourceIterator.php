<?php

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfony\Bundle\AsseticBundle\Factory\Resource;

use Symfony\Component\Templating\Loader\LoaderInterface;

class DirectoryResourceIterator extends \RecursiveIteratorIterator
{
    protected $loader;
    protected $bundle;
    protected $path;

    /**
     * Constructor.
     *
     * @param LoaderInterface   $loader   The templating loader
     * @param string            $bundle   The current bundle name
     * @param string            $path     The directory
     * @param RecursiveIterator $iterator The inner iterator
     */
    public function __construct(LoaderInterface $loader, $bundle, $path, \RecursiveIterator $iterator)
    {
        $this->loader = $loader;
        $this->bundle = $bundle;
        $this->path = $path;

        parent::__construct($iterator);
    }

    public function current()
    {
        $file = parent::current();

        return new FileResource($this->loader, $this->bundle, $this->path, $file->getPathname());
    }
}
