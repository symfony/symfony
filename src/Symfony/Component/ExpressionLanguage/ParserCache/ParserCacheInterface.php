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

use Symfony\Component\ExpressionLanguage\ParsedExpression;

/**
 * @author Adrien Brault <adrien.brault@gmail.com>
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
