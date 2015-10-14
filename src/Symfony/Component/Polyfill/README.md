Symfony Polyfill Component
==========================

This component backports features found in latest PHP versions. It also provides
compatibility PHP implementations for some extensions and functions. You should
use it when portability across PHP versions and extensions is desired.

Polyfills are provided for:
- the `mbstring` extension;
- the `Normalizer` class and the `grapheme_*` functions;
- the `utf8_encode` and `utf8_decode` functions from the `xml` extension;
- the `hex2bin` function, the `CallbackFilterIterator`,
  `RecursiveCallbackFilterIterator` and `SessionHandlerInterface` classes
  introduced in PHP 5.4;
- the `array_column`, `boolval`, `json_last_error_msg` and `hash_pbkdf2`
  functions introduced in PHP 5.5;
- the `hash_equals` and `ldap_escape` functions introduced in PHP 5.6;
- the `*Error` classes, the `intdiv`, `preg_replace_callback_array` and
  `error_clear_last` functions introduced in PHP 7.0;
- a `Binary` utility class to be used when compatibility with
  `mbstring.func_overload` is required.

If `symfony/intl` is also installed, more polyfills are provided, limited to the
"en" locale, for:
- the `Collator`, `NumberFormatter`, `Locale` and `IntlDateFormatter` classes;
- the `intl_error_name`, `intl_get_error_code`, `intl_get_error_message` and
  `intl_is_failure` functions.

It is strongly recommended to upgrade your PHP version and/or install the missing
extensions when possible. This polyfill should be used only when there is no
better choice or when portability is a requirement.

Compatiblity notes
==================

To write portable code between PHP5 and PHP7, some care must be taken:
- `\*Error` exceptions must by caught before `\Exception`;
- after calling `error_clear_last()`, the result of `$e = error_get_last()` must be
  verified using `isset($e['message'][0])` instead of `null === $e`.

Design
======

This component is designed for low overhead and high quality polyfilling.

It adds only one lightweight `require` to the bootstrapping process of your
applications for all polyfills. Implementations are then loaded on-demand when
they are needed during code execution.

Polyfills are unit-tested alongside with their native implementation so that
feature and behavior parity can be proven and enforced on the long run.
