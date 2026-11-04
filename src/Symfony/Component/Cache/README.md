Symfony PSR-6 implementation for caching
========================================

The Cache component provides extended
[PSR-6](https://www.php-fig.org/psr/psr-6/) implementations for adding cache to
your applications. It is designed to have a low overhead so that caching is
fastest. It ships with adapters for the most widespread caching backends.
It also provides a [PSR-16](https://www.php-fig.org/psr/psr-16/) adapter,
and implementations for [symfony/cache-contracts](https://github.com/symfony/cache-contracts)'
`CacheInterface` and `TagAwareCacheInterface`.

`DefaultMarshaller` uses the [phpser extension](https://github.com/iliaal/phpser)
(v0.10.3 or higher) when installed, and native PHP serialization otherwise. Install phpser in every
process that reads the same cache. Pass `false` as the first argument to
`DefaultMarshaller` to keep native PHP serialization.

`CacheBundle` configures `cache.default_marshaller` to use HMAC signing when phpser is installed
and `kernel.secret` is defined. The signing key is derived from that secret.

You can pass a key of at least 32 bytes to enable phpser's HMAC signing. The key is
ignored when phpser is not loaded:

```php
$marshaller = new DefaultMarshaller(null, false, $signingKey);
```

The marshaller verifies the signature before decoding values. Existing unsigned
entries and entries signed with another key become cache misses.

Sponsor
-------

This package is looking for a [backer][1].

Help Symfony by [sponsoring][3] its development!

Resources
---------

 * [Documentation](https://symfony.com/doc/current/components/cache.html)
 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)

[1]: https://symfony.com/backers
[3]: https://symfony.com/sponsor
