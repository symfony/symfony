UPGRADE FROM 7.2 to 7.3
=======================

Symfony 7.3 is a minor release. According to the Symfony release process, there should be no significant
backward compatibility breaks. Minor backward compatibility breaks are prefixed in this document with
`[BC BREAK]`, make sure your code is compatible with these entries before upgrading.
Read more about this in the [Symfony documentation](https://symfony.com/doc/7.3/setup/upgrade_minor.html).

If you're upgrading from a version below 7.2, follow the [7.2 upgrade guide](UPGRADE-7.2.md) first.

TypeInfo
--------

 * Deprecate creation of `UnionType` and `IntersectionType` with only same types
