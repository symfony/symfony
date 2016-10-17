<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\ExpressionLanguage;

@trigger_error('The '.__NAMESPACE__.'\DoctrineParserCache class is deprecated since version 3.2 and will be removed in 4.0. Use the Symfony\Component\Cache\Adapter\DoctrineAdapter class instead.', E_USER_DEPRECATED);

use Doctrine\Common\Cache\Cache;
use Symfony\Component\ExpressionLanguage\ParsedExpression;
use Symfony\Component\ExpressionLanguage\ParserCache\ParserCacheInterface;

/**
 * @author Adrien Brault <adrien.brault@gmail.com>
 *
 * @deprecated DoctrineParserCache class is deprecated since version 3.2 and will be removed in 4.0. Use the Symfony\Component\Cache\Adapter\DoctrineAdapter class instead.
 */
class DoctrineParserCache implements ParserCacheInterface
{
    /**
     * @var Cache
     */
    private $cache;

    public function __construct(Cache $cache)
    {
        $this->cache = $cache;
    }

    /**
     * {@inheritdoc}
     */
    public function fetch($key)
    {
        if (false === $value = $this->cache->fetch($key)) {
            return;
        }

        return $value;
    }

    /**
     * {@inheritdoc}
     */
    public function save($key, ParsedExpression $expression)
    {
        $this->cache->save($key, $expression);
    }
}
