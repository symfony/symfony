<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Util;

/**
 * @since  %%NextVersion%%
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PropertyPath
{
    public static function append($basePath, $subPath)
    {
        if ('' !== (string) $subPath) {
            if ('[' === $subPath{1}) {
                return $basePath.$subPath;
            }

            return $basePath ? $basePath.'.'.$subPath : $subPath;
        }

        return $basePath;
    }

    private function __construct()
    {
    }
}
