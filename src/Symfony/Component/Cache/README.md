# Symfony Cache

This component provides a very simple cache interface to be used
with other Symfony components the framework or third party bundles.
Complexity in the cache interface is intentionaly left out to provide
a common denominator that clients of this interface can rely on.

A lightweight APC implementation is also shipped to support the most
commonly available caching implementation.

For other cache drivers or more complex caching needs you should
use any cache provider that has an implementation of the
`Symfony\Component\Cache\CacheInterface`.

If you are using Doctrine\Common in your project you can use the
delegate cache driver `Symfony\Component\Cache\DoctrineCache`.

## Usage

    use Symfony\Component\Cache\ApcCache;

    $cache = new ApcCache();
    $cache->save("key", "value");

    if ($cache->contains("key")) {
        echo $cache->fetch("key");
    }
    $cache->delete("key");
