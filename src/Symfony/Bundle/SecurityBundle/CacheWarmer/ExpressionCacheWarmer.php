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
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;

/**
 * @deprecated since Symfony 8.2, the expressions of access_control rules are compiled when warming up the cache
 */
final class ExpressionCacheWarmer implements CacheWarmerInterface
{
    /**
     * @param iterable<mixed, Expression|string> $expressions
     */
    public function __construct(
        private iterable $expressions,
        private ExpressionLanguage $expressionLanguage,
    ) {
        trigger_deprecation('symfony/security-bundle', '8.2', 'The "%s" class is deprecated, as the expressions of "access_control" rules are compiled when warming up the cache.', self::class);
    }

    public function isOptional(): bool
    {
        return true;
    }

    public function warmUp(string $cacheDir, ?string $buildDir = null): array
    {
        foreach ($this->expressions as $expression) {
            $this->expressionLanguage->parse($expression, ['token', 'user', 'object', 'subject', 'role_names', 'auth_checker', 'request', 'trust_resolver']);
        }

        return [];
    }
}
