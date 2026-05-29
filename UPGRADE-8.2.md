UPGRADE FROM 8.1 to 8.2
=======================

Symfony 8.2 is a minor release. According to the Symfony release process, there should be no significant
backward compatibility breaks. Minor backward compatibility breaks are prefixed in this document with
`[BC BREAK]`, make sure your code is compatible with these entries before upgrading.
Read more about this in the [Symfony documentation](https://symfony.com/doc/8.2/setup/upgrade_minor.html).

If you're upgrading from a version below 8.1, follow the [8.1 upgrade guide](UPGRADE-8.1.md) first.

FrameworkBundle
---------------

 * Deprecate the `framework.ide` config option, use the `SYMFONY_IDE` env var instead

HttpFoundation
--------------

 * Add argument `$version` to `UriSigner::sign()`, `UriSigner::check()`, `UriSigner::checkRequest()`, and `UriSigner::verify()`
