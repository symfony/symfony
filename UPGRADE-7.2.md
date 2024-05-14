UPGRADE FROM 7.1 to 7.2
=======================

Symfony 7.2 is a minor release. According to the Symfony release process, there should be no significant
backward compatibility breaks. Minor backward compatibility breaks are prefixed in this document with
`[BC BREAK]`, make sure your code is compatible with these entries before upgrading.
Read more about this in the [Symfony documentation](https://symfony.com/doc/7.2/setup/upgrade_minor.html).

If you're upgrading from a version below 7.1, follow the [7.1 upgrade guide](UPGRADE-7.1.md) first.

Cache
-----

 * `igbinary_serialize()` is not used by default when the igbinary extension is installed

Form
----

 * Deprecate the `VersionAwareTest` trait, use feature detection instead

FrameworkBundle
---------------

 * [BC BREAK] The `secrets:decrypt-to-local` command terminates with a non-zero exit code when a secret could not be read

Security
--------

 * Add `$token` argument to `UserCheckerInterface::checkPostAuth()`
 * Deprecate argument `$secret` of `RememberMeToken` and `RememberMeAuthenticator`
 * Add discovery support to `OidcTokenHandler` and `OidcUserInfoTokenHandler`

String
------

 * `truncate` method now also accept `TruncateMode` enum instead of a boolean:
   * `TruncateMode::Char` is equivalent to `true` value ;
   * `TruncateMode::WordAfter` is equivalent to `false` value ;
   * `TruncateMode::WordBefore` is a new mode that will cut the sentence on the last word before the limit is reached.

Yaml
----

 * Deprecate parsing duplicate mapping keys whose value is `null`
