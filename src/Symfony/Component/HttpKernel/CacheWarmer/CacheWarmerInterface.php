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
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
interface CacheWarmerInterface
{
    /**
     * Warms up the cache.
     *
     * @param string $cacheDir The cache directory
     */
    function warmUp($cacheDir);

    /**
     * Checks whether this warmer is optional or not.
     *
     * Optional warmers can be ignored on certain conditions.
     *
     * A warmer should return true if the cache can be
     * generated incrementally and on-demand.
     *
     * @return Boolean true if the warmer is optional, false otherwise
     */
    function isOptional();
    
    /**
     * Returns the list or warmers that should be executed before this one.
     * 
     * @return array List of warmers to run before this one
     */
    function getPreWarmers();

    /**
     * Returns the list or warmers that should be executed after this one.
     * 
     * @return array List of warmers to run after this one
     */
    function getPostWarmers();
    
    /**
     * Returns the warmer name.
     * 
     * The name must be unique.
     * 
     * @return string The warmer name
     */
    function getName();
    
    
}
