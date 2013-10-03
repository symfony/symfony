CHANGELOG
=========

2.4.0
-----

 * [BC BREAK] the various Intl methods now throw a `NoSuchEntryException`
   whenever a non-existing locale, language, currency, etc. is accessed
 * the available locales of each resource bundle are now stored in a generic
   "misc.res" file in order to improve reading performance
 * improved `LocaleBundleTransformationRule` to not generate duplicate locale
   names when fallback (e.g. "en_GB"->"en") is possible anyway. This reduced
   the Resources/ directory file size of the Icu 1.2.x branch from 14M to 12M at
   the time of this writing
 * [BC BREAK] a new method `getLocaleAliases()` was added to `LocaleBundleInterface`
 * deprecated `StructuredBundleReader` and `StructuredBundleReaderInterface` in
   favor of `BundleEntryReader` and `BundleEntryReaderInterface`
 * `BundleEntryReader` now follows aliases when looking for fallback locales
 * [BC BREAK] changed default value of the argument `$fallback` in the protected
   method `AbstractBundle::readEntry()` to `true` in order to be consistent with
   the proxied `BundleEntryReaderInterface::readEntry()` method
 * deprecated `BundleCompiler` in favor of `GenrbBundleCompiler`
