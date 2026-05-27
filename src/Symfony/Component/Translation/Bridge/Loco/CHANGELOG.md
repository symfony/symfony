CHANGELOG
=========

8.2
---

 * Calling `LocoProvider::read()` without locale now fetch them all
 * Deprecate passing `LocoProvider` and `LocoProviderFactory` constructor a `$defaultLocale` argument: it has no effect and can be removed
 * Deprecate passing no domains or `*` to `LocoProvider::read()`, configure your loco provider domains as an associative array with an empty string key and `*` as value
 * Allow to map a tag filter to a domain

7.2
---

 * Add support for the `status` query parameter of Loco translation API

6.1
---

 * Include header `If-Modified-Since` as catalog metadata to support verifying whether a translation value was changed
 * Add `$translatorBag` constructor argument of `TranslatorBagInterface` to `LocoProviderFactory` and `LocoProvider`

5.4
---

 * The bridge is not experimental anymore

5.3
---

 * Create the bridge
