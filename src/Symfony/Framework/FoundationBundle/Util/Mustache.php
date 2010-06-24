<?php

namespace Symfony\Framework\FoundationBundle\Util;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * Mustache.
 *
 * @package    Symfony
 * @subpackage Framework_FoundationBundle
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class Mustache
{
    static public function renderString($string, $parameters)
    {
        $replacer = function ($match) use($parameters)
        {
            return isset($parameters[$match[1]]) ? $parameters[$match[1]] : $match[0];
        };

        return preg_replace_callback('/{{\s*(.+?)\s*}}/', $replacer, $string);
    }

    static public function renderFile($file, $parameters)
    {
        file_put_contents($file, static::renderString(file_get_contents($file), $parameters));
    }

    static public function renderDir($dir, $parameters)
    {
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir), \RecursiveIteratorIterator::LEAVES_ONLY) as $file) {
            if ($file->isFile()) {
                static::renderFile((string) $file, $parameters);
            }
        }
    }
}
