<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\DependencyInjection\Loader;

/**
 * DirectoryLoader is a recursive loader to go through directories.
 *
 * @author Sebastien Lavoie <seb@wemakecustom.com>
 */
class DirectoryLoader extends FileLoader
{
    /**
     * {@inheritdoc}
     */
    public function load($file, $type = null)
    {
        $file = rtrim($file, '/');
        $path = $this->locator->locate($file);
        $this->container->fileExists($path, false);

        foreach (scandir($path) as $dir) {
            if ('.' !== $dir[0]) {
                if (is_dir($path.'/'.$dir)) {
                    $dir .= '/'; // append / to allow recursion
                }

                $this->setCurrentDir($path);

                $this->import($dir, null, false, $path);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function supports($resource, $type = null)
    {
        if ('directory' === $type) {
            return true;
        }

        return null === $type && is_string($resource) && '/' === substr($resource, -1);
    }
}
