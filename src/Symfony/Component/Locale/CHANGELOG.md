CHANGELOG
=========

2.3.0
-----

The Locale component is deprecated since version 2.3 and will be removed in
Symfony 3.0. You should use the more capable Intl component instead.

2.1.0
-----

 * added Locale::getIntlIcuVersion(), Locale::getIntlIcuDataVersion(), Locale::getIcuDataVersion() and Locale::getIcuDataDirectory()
 * renamed update-data.php to build-data.php, the script usage changed, now it is easier to update the ICU data
 * updated the ICU data to the release 49.1.2
