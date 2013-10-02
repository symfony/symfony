CHANGELOG
=========

2.4.0
-----

 * [BC BREAK] the various Intl methods now throw a `NoSuchLocaleException`
   whenever an invalid locale is given
 * [BC BREAK] the various Intl methods now throw a `NoSuchEntryException`
   whenever a non-existing language, currency, etc. is accessed
 * the available locales of each resource bundle are now stored in a generic
   "misc.res" file in order to improve reading performance
