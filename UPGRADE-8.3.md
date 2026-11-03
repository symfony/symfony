UPGRADE FROM 8.2 to 8.3
=======================

Symfony 8.3 is a minor release.
According to the Symfony release process, there should be no significant backward compatibility breaks.
Minor backward compatibility breaks are prefixed in this document with `[BC BREAK]`, make sure your code is compatible with these entries before upgrading.
Read more about this in the [Symfony documentation](https://symfony.com/doc/8.3/setup/upgrade_minor.html).

If you're upgrading from a version below 8.2, follow the [8.2 upgrade guide](UPGRADE-8.2.md) first.

Cache
-----

 * Deprecate nesting `DeflateMarshaller` decorators. Use a single decorator and clear the affected cache pool.
