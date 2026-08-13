CHANGELOG
=========

8.2
---

 * Add `RateLimitExceededEvent`
 * `CompoundLimiter::consume()` now stops consuming at the first limiter that rejects the request
 * Add `RateLimiterBuilder`
 * Allow `\DateInterval` for the `interval` and `rate.interval` options of `RateLimiterFactory`
 * Add `RateLimit::getResetAt()`
 * Add a `$keys` argument to `CompoundRateLimiterFactory` to fix the key of some of its sub-limiters

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
