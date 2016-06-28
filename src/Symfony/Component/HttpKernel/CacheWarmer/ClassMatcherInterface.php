<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\CacheWarmer;

/**
 * A class matcher find classes matching given patterns.
 *
 * @author Titouan Galopin <galopintitouan@gmail.com>
 */
interface ClassMatcherInterface
{
    /**
     * Return classes matching at least one of the given patterns.
     *
     * @param array $classes  All the possibles classes
     * @param array $patterns The patterns to filter these classes
     *
     * @return array The classes matching the patterns
     */
    public function match(array $classes, array $patterns);
}
