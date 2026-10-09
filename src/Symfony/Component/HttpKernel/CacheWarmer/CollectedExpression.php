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

use Symfony\Component\ExpressionLanguage\Expression;

/**
 * An expression held by an attribute of a controller or listed in the configuration.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
final class CollectedExpression
{
    /**
     * @param list<string>|null $variables The variables the expression can read, or null when any variable is allowed
     * @param string            $source    Where the expression comes from, e.g. 'the "if" option of #[Cache] on "App\Controller\BlogController::show()"'
     */
    public function __construct(
        public readonly Expression|string $expression,
        public readonly ?array $variables,
        public readonly string $source,
    ) {
    }
}
