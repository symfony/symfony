CHANGELOG
=========

5.3
---

 * Add `Currencies::getCashFractionDigits()` and `Currencies::getCashRoundingIncrement()`

5.0.0
-----

 * removed `ResourceBundle` namespace

4.4.0
-----

 * excluded language code `root`
 * added to both `Countries` and `Languages` the methods `getAlpha3Codes`, `getAlpha3Code`, `getAlpha2Code`, `alpha3CodeExists`, `getAlpha3Name` and `getAlpha3Names`
 * excluded localized languages (e.g. `en_US`) from `Languages` in `getLanguageCodes()` and `getNames()`

4.3.0
-----

 * deprecated `ResourceBundle` namespace
 * added `Currencies` in favor of `Intl::getCurrencyBundle()`
 * added `Languages` and `Scripts` in favor of `Intl::getLanguageBundle()`
 * added `Locales` in favor of `Intl::getLocaleBundle()`
 * added `Countries` in favor of `Intl::getRegionBundle()`
 * added `Timezones`
 * made country codes ISO 3166 compliant
 * excluded script code `Zzzz`

4.2.0
-----

 * excluded language codes `mis`, `mul`, `und` and `zxx`
