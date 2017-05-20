<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ExpressionLanguage\ParserCache;

@trigger_error('The '.__NAMESPACE__.'\ParserCacheInterface interface is deprecated since version 3.2 and will be removed in 4.0. Use Psr\Cache\CacheItemPoolInterface instead.', E_USER_DEPRECATED);

use Symfony\Component\ExpressionLanguage\ParsedExpression;

/**
 * @author Adrien Brault <adrien.brault@gmail.com>
 *
 * @deprecated since version 3.2, to be removed in 4.0. Use Psr\Cache\CacheItemPoolInterface instead.
 */
interface ParserCacheInterface
{
    /**
     * Saves an expression in the cache.
     *
     * @param string           $key        The cache key
     * @param ParsedExpression $expression A ParsedExpression instance to store in the cache
     */
    public function save($key, ParsedExpression $expression);

    /**
     * Fetches an expression from the cache.
     *
     * @param string $key The cache key
     *
     * @return ParsedExpression|null
     */
    public function fetch($key);
}
