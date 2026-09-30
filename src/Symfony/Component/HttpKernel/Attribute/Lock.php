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
 * Prevents concurrent requests from running the controller.
 *
 * While another request holds the lock, the request is rejected with a "409 Conflict" response,
 * unless $blocking is enabled, in which case it waits for the lock to be released.
 *
 * @see https://symfony.com/doc/current/lock.html
 */
#[\Attribute(\Attribute::IS_REPEATABLE | \Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::TARGET_FUNCTION)]
final class Lock
{
    /** @var string[] */
    public readonly array $methods;

    /**
     * @param string|Expression|\Closure|null $key      A literal string key, an Expression, or a Closure (defaults to the request path, so that all clients and methods contend for the same lock)
     * @param string                          $factory  The name of the lock factory to use, as configured under the "lock" resources
     * @param float|null                      $ttl      The maximum expected duration of the request in seconds, after which the lock expires; null means never
     * @param bool                            $blocking Whether to wait for the lock to be released instead of rejecting the request with a "409 Conflict" response
     * @param string[]|string                 $methods  HTTP methods to lock; empty means all methods
     */
    public function __construct(
        public readonly string|Expression|\Closure|null $key = null,
        public readonly string $factory = 'default',
        public readonly ?float $ttl = 30.0,
        public readonly bool $blocking = false,
        array|string $methods = [],
    ) {
        if (null !== $this->ttl && $this->ttl <= 0) {
            throw new \InvalidArgumentException(\sprintf('The "$ttl" argument of "%s" must be greater than 0 or null, "%s" given.', self::class, $this->ttl));
        }

        if (\in_array('GET', $methods = array_map('strtoupper', (array) $methods), true)) {
            $methods[] = 'HEAD';
        }
        $this->methods = $methods;
    }
}
