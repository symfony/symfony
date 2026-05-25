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
 * Contains utility methods for dealing with property paths.
 *
 * For more extensive functionality, use Symfony's PropertyAccess component.
 *
 * This helper is kept for back compatibility and to avoid requiring
 * symfony/property-access at runtime. The same behavior is also available via
 * {@see \Symfony\Component\PropertyAccess\PropertyPath::append()}.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PropertyPath
{
    /**
     * Appends a path to a given property path.
     *
     * If the base path is empty, the appended path will be returned unchanged.
     * If the base path is not empty, and the appended path starts with a
     * squared opening bracket ("["), the concatenation of the two paths is
     * returned. Otherwise, the concatenation of the two paths is returned,
     * separated by a dot (".").
     *
     * @see \Symfony\Component\PropertyAccess\PropertyPath::append() identical helper exposed by the PropertyAccess component
     */
    public static function append(string $basePath, string $subPath): string
    {
        if ('' !== $subPath) {
            if ('[' === $subPath[0]) {
                return $basePath.$subPath;
            }

            return '' !== $basePath ? $basePath.'.'.$subPath : $subPath;
        }

        return $basePath;
    }

    /**
     * Not instantiable.
     */
    private function __construct()
    {
    }
}
