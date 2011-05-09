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

use Assetic\Factory\Resource\DirectoryResource as BaseDirectoryResource;
use Symfony\Component\Templating\Loader\LoaderInterface;

/**
 * A directory resource that creates Symfony2 templating resources.
 *
 * @author Kris Wallsmith <kris@symfony.com>
 */
class DirectoryResource extends BaseDirectoryResource
{
    protected $loader;
    protected $bundle;
    protected $path;

    /**
     * Constructor.
     *
     * @param LoaderInterface $loader  The templating loader
     * @param string          $bundle  The current bundle name
     * @param string          $path    The directory path
     * @param string          $pattern A regex pattern for file basenames
     */
    public function __construct(LoaderInterface $loader, $bundle, $path, $pattern = null)
    {
        $this->loader = $loader;
        $this->bundle = $bundle;
        $this->path = rtrim($path, '/').'/';

        parent::__construct($path, $pattern);
    }

    public function getIterator()
    {
        return is_dir($this->path)
            ? new DirectoryResourceIterator($this->loader, $this->bundle, $this->path, $this->getInnerIterator())
            : new \EmptyIterator();
    }
}
