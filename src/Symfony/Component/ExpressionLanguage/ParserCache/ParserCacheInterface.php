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
     * @param  string                $key
     * @param  ParsedExpression      $data
     */
    public function save($key, ParsedExpression $expression);

    /**
     * @param  string                $key
     * @return ParsedExpression|null
     */
    public function fetch($key);
}
