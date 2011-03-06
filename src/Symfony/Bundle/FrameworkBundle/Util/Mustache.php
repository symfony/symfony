<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Util;

/**
 * Mustache.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Mustache
{
    /**
     * Renders a single line. Looks for {{ var }}
     *
     * @param string $string
     * @param array $parameters
     *
     * @return string
     */
    static public function renderString($string, array $parameters)
    {
        $replacer = function ($match) use ($parameters)
        {
            return isset($parameters[$match[1]]) ? $parameters[$match[1]] : $match[0];
        };

        return preg_replace_callback('/{{\s*(.+?)\s*}}/', $replacer, $string);
    }

    /**
     * Renders a file by replacing the contents of $file with rendered output.
     *
     * @param string $file filename for the file to be rendered
     * @param array $parameters
     */
    static public function renderFile($file, array $parameters)
    {
        file_put_contents($file, static::renderString(file_get_contents($file), $parameters));
    }

    /**
     * Renders a directory recursively
     *
     * @param string $dir Path to the directory that will be recursively rendered
     * @param array $parameters
     */
    static public function renderDir($dir, array $parameters)
    {
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir), \RecursiveIteratorIterator::LEAVES_ONLY) as $file) {
            if ($file->isFile()) {
                static::renderFile((string) $file, $parameters);
            }
        }
    }
}
