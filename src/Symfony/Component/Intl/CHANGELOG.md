CHANGELOG
=========

2.4.0
-----

 * [BC BREAK] the various Intl methods now throw a `NoSuchLocaleException`
   whenever an invalid locale is given
 * [BC BREAK] the various Intl methods now throw a `NoSuchEntryException`
   whenever a non-existing language, currency, etc. is accessed
