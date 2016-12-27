<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Loader;

use Symfony\Component\Config\Loader\FileLoader;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Config\Resource\DirectoryResource;

class DirectoryLoader extends FileLoader
{
    /**
     * {@inheritdoc}
     */
    public function load($file, $type = null)
    {
        $file = rtrim($file, '/');

        $this->setCurrentResource($file.'/');

        $path = $this->locate($file);

        $collection = new RouteCollection();
        $collection->addResource(new DirectoryResource($path));

        foreach (scandir($path) as $dir) {
            if ('.' !== $dir[0]) {
                $type = null;
                if (is_dir($path.'/'.$dir)) {
                    $dir .= '/';
                    $type = 'directory';
                }

                $collection->addCollection($this->import($dir, $type));
            }
        }

        return $collection;
    }

    /**
     * {@inheritdoc}
     */
    public function supports($resource, $type = null)
    {
        // only when type is forced to directory, not to conflict with AnnotationLoader

        return 'directory' === $type;
    }
}
