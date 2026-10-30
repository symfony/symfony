CHANGELOG
=========

8.2
---

 * Add `RateLimiterBundle`, which provides the `rate_limiter` configuration and the services previously provided by `FrameworkBundle` under `framework.rate_limiter`
 * Add `RateLimitExceededEvent`
 * Add `LimiterInterface::refund()`, to give back the tokens of a `consume()` call that should not count
 * Make `CompoundLimiter::consume()` all-or-nothing: it stops at the first limiter that rejects the request and gives back the tokens of the limiters that accepted
 * Add `RateLimiterBuilder` and the `rate_limiter.builder` option
 * Allow `\DateInterval` for the `interval` and `rate.interval` options of `RateLimiterFactory`
 * Add `RateLimit::getResetAt()`
 * Add a `$keys` argument to `CompoundRateLimiterFactory`, and accept a map in the `limiters` option of a compound rate limiter, to fix the key of some of its sub-limiters

8.1
---

 * Add an optional `$anchorAt` argument to `FixedWindowLimiter` to align the window to a calendar reference datetime instead of the first hit

7.3
---

 * Add `RateLimiterFactoryInterface`
 * Add `CompoundRateLimiterFactory`

6.4
---

 * Add `SlidingWindowLimiter::reserve()`

6.2
---

 * Move `symfony/lock` to dev dependency in `composer.json`

5.4
---

 * The component is not experimental anymore
 * Add support for long intervals (months and years)

5.2.0
-----

 * added the component
