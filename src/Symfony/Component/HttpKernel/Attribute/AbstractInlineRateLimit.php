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

use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\RateLimiter\RateLimiterBuilder;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * Base for the #[RateLimit*] attributes that configure their limiter inline instead
 * of referencing one declared in "rate_limiter" configuration.
 *
 * @author Kevin Bond <kevinbond@gmail.com>
 */
abstract class AbstractInlineRateLimit
{
    /** @var string[] */
    public readonly array $methods;

    /**
     * @param string|Expression|\Closure|null $key           A string where {name} is replaced by the "name" request attribute, such as a route parameter, or an Expression or a Closure evaluating to a string; null means client IP + method + path
     * @param int                             $tokens        The number of tokens to consume
     * @param string[]|string                 $methods       HTTP methods to rate limit; empty means all methods
     * @param bool                            $exposeHeaders Whether this limiter's state may be exposed via the `X-RateLimit-*` response headers, opt-in
     * @param bool|Expression|\Closure        $if            Whether the rate limit applies: a boolean, or an Expression or a Closure evaluating to one
     * @param string|null                     $id            Identity of the limiter bucket; defaults to the controller and attribute class, so stacking several of these on the same action needs a distinct one
     */
    public function __construct(
        public readonly string|Expression|\Closure|null $key = null,
        public readonly int $tokens = 1,
        array|string $methods = [],
        public readonly bool $exposeHeaders = false,
        public readonly bool|Expression|\Closure $if = true,
        public readonly ?string $id = null,
    ) {
        if ($this->tokens < 1) {
            throw new \InvalidArgumentException(\sprintf('The "$tokens" argument of "%s" must be greater than 0, "%d" given.', static::class, $this->tokens));
        }

        if (\in_array('GET', $methods = array_map('strtoupper', (array) $methods), true)) {
            $methods[] = 'HEAD';
        }
        $this->methods = $methods;
    }

    /**
     * @param string $id The limiter's identity, used as its storage/lock key prefix; must stay
     *                   stable across requests so the same window/bucket keeps accumulating
     */
    abstract public function createFactory(RateLimiterBuilder $builder, string $id): RateLimiterFactoryInterface;
}
