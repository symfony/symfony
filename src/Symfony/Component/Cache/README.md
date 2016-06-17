Symfony PSR-6 implementation for caching
========================================

This component provides an extended [PSR-6](http://www.php-fig.org/psr/psr-6/)
implementation for adding cache to your applications. It is designed to have a
low overhead so that caching is fastest. It ships with a few caching adapters
for the most widespread and suited to caching backends. It also provides a
`doctrine/cache` proxy adapter to cover more advanced caching needs and a proxy
adapter for greater interoperability between PSR-6 implementations.
