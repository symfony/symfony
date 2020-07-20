<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\CacheWarmer;

use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;
use Symfony\Component\Security\Core\Authorization\ExpressionLanguage;

class ExpressionCacheWarmer implements CacheWarmerInterface
{
    private $expressions;
    private $expressionLanguage;

    /**
     * @param iterable|Expression[] $expressions
     */
    public function __construct(iterable $expressions, ExpressionLanguage $expressionLanguage)
    {
        $this->expressions = $expressions;
        $this->expressionLanguage = $expressionLanguage;
    }

    public function isOptional()
    {
        return true;
    }

    /**
     * @return string[]
     */
    public function warmUp(string $cacheDir)
    {
        foreach ($this->expressions as $expression) {
            $this->expressionLanguage->parse($expression, ['token', 'user', 'object', 'subject', 'roles', 'request', 'trust_resolver']);
        }

        return [];
    }
}
