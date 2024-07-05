UPGRADE FROM 7.1 to 7.2
=======================

Symfony 7.2 is a minor release. According to the Symfony release process, there should be no significant
backward compatibility breaks. Minor backward compatibility breaks are prefixed in this document with
`[BC BREAK]`, make sure your code is compatible with these entries before upgrading.
Read more about this in the [Symfony documentation](https://symfony.com/doc/7.2/setup/upgrade_minor.html).

If you're upgrading from a version below 7.1, follow the [7.1 upgrade guide](UPGRADE-7.1.md) first.

Security
--------

 * Deprecate argument `$secret` of `RememberMeToken` and `RememberMeAuthenticator`

String
------

 * `truncate` method now also accept `TruncateMode` enum instead of a boolean:
   * `TruncateMode::Char` is equivalent to `true` value ;
   * `TruncateMode::WordAfter` is equivalent to `false` value ;
   * `TruncateMode::Word` is a new mode that will cut the sentence on the last word before the limit is reached.

Yaml
----

 * Deprecate parsing duplicate mapping keys whose value is `null`
