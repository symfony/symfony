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

/**
 * Rate limits the controller.
 *
 * @see https://symfony.com/doc/current/rate_limiter.html
 *
 * @author Ayyoub AFW-ALLAH <ayyoub.afwallah@gmail.com>
 */
#[\Attribute(\Attribute::IS_REPEATABLE | \Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::TARGET_FUNCTION)]
final class RateLimit
{
    /** @var string[] */
    public readonly array $methods;

    /**
     * @param string                          $limiter The configured limiter name
     * @param string|Expression|\Closure|null $key     A literal string key, an Expression, or a Closure (defaults to client IP + method + path)
     * @param int                             $tokens  The number of tokens to consume
     * @param string[]|string                 $methods HTTP methods to rate limit; empty means all methods
     */
    public function __construct(
        public readonly string $limiter,
        public readonly string|Expression|\Closure|null $key = null,
        public readonly int $tokens = 1,
        array|string $methods = [],
    ) {
        if ($this->tokens < 1) {
            throw new \InvalidArgumentException(\sprintf('The "$tokens" argument of "%s" must be greater than 0, "%d" given.', self::class, $this->tokens));
        }

        if (\in_array('GET', $methods = array_map('strtoupper', (array) $methods), true)) {
            $methods[] = 'HEAD';
        }
        $this->methods = $methods;
    }
}
