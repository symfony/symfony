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
class ArrayParserCache implements ParserCacheInterface
{
    /**
     * @var array
     */
    private $cache = array();

    /**
     * {@inheritdoc}
     */
    public function fetch($key)
    {
        return isset($this->cache[$key]) ? $this->cache[$key] : null;
    }

    /**
     * {@inheritdoc}
     */
    public function save($key, ParsedExpression $expression)
    {
        $this->cache[$key] = $expression;
    }
}
