<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Attribute;

use Symfony\Component\HttpKernel\Controller\ArgumentResolver\MapRateLimitValueResolver;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;

/**
 * Maps a #[RateLimit] result to a controller argument.
 * Selects the matching result with the fewest remaining calls.
 *
 * @author Ayyoub AFW-ALLAH <ayyoub.afwallah@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
final class MapRateLimit extends ValueResolver
{
    /**
     * @param string|null                                 $limiter  The configured limiter name; null includes every limiter
     * @param bool|null                                   $exposed  Filter by exposeHeaders; null includes both exposed and hidden results
     * @param class-string<ValueResolverInterface>|string $resolver The name of the resolver to use
     */
    public function __construct(
        public readonly ?string $limiter = null,
        public readonly ?bool $exposed = null,
        string $resolver = MapRateLimitValueResolver::class,
    ) {
        parent::__construct($resolver);
    }
}
