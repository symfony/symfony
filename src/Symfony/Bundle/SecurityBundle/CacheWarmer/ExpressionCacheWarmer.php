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
    private iterable $expressions;
    private ExpressionLanguage $expressionLanguage;

    /**
     * @param iterable<mixed, Expression|string> $expressions
     */
    public function __construct(iterable $expressions, ExpressionLanguage $expressionLanguage)
    {
        $this->expressions = $expressions;
        $this->expressionLanguage = $expressionLanguage;
    }

    public function isOptional(): bool
    {
        return true;
    }

    public function warmUp(string $cacheDir, ?string $buildDir = null): array
    {
        foreach ($this->expressions as $expression) {
            $this->expressionLanguage->parse($expression, ['token', 'user', 'object', 'subject', 'role_names', 'request', 'trust_resolver']);
        }

        return [];
    }
}
