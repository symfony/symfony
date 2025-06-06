UPGRADE FROM 8.0 to 8.1
=======================

Symfony 8.1 is a minor release. According to the Symfony release process, there should be no significant
backward compatibility breaks. Minor backward compatibility breaks are prefixed in this document with
`[BC BREAK]`, make sure your code is compatible with these entries before upgrading.
Read more about this in the [Symfony documentation](https://symfony.com/doc/8.1/setup/upgrade_minor.html).

If you're upgrading from a version below 8.0, follow the [8.0 upgrade guide](UPGRADE-8.0.md) first.

Table of Contents
-----------------

Bundles

 * [FrameworkBundle](#FrameworkBundle)

FrameworkBundle
---------------

 * Deprecate setting the `framework.profiler.collect_serializer_data` config option
