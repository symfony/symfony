Intl Component
=============

A PHP replacement layer for the C intl extension that includes additional data
from the ICU library.

The replacement layer is limited to the locale "en". If you want to use other
locales, you should [install the intl extension] [10] instead.

Installation
------------

You can install the component in two different ways:

* Using the official Git repository (https://github.com/symfony/Intl);
* [Install it via Composer] [0] (`symfony/intl` on [Packagist] [1]).

If you install the component via Composer, the following classes and functions
of the intl extension will be automatically provided if the intl extension is
not loaded:

* [`\Locale`] [2]
* [`\NumberFormatter`] [3]
* [`\IntlDateFormatter`] [4]
* [`\Collator`] [5]
* [`intl_is_failure()`] [6]
* [`intl_get_error_code()`] [7]
* [`intl_get_error_message()`] [8]
* [`intl_error_name()`] [9]

If you don't use Composer but the Symfony ClassLoader component, you need to
load them manually by adding the following lines to your autoload code:

    if (!function_exists('intl_is_failure')) {
        require '/path/to/Icu/Resources/stubs/functions.php';

        $loader->registerPrefixFallback('/path/to/Icu/Resources/stubs');
    }

Stubbed Classes
---------------

The stubbed classes of the intl extension are limited to the locale "en" and
will throw an exception if you try to use a different locale. For using other
locales, [install the intl extension] [10] instead.

### Locale

The only method supported in the [´\Locale`] [2] class is `getDefault()` and
will always return "en". All other methods will throw an exception when used.

### NumberFormatter

Numbers can be formatted with the [`\NumberFormatter`] [3] class. The following
methods are supported. All other methods are not supported and will throw an
exception when used.

##### __construct($locale = $style = null, $pattern = null)

The only supported locale is "en". The supported styles are
`\NumberFormatter::DECIMAL` and `\NumberFormatter::CURRENCY`. The argument
`$pattern` may not be used.

##### ::create($locale = $style = null, $pattern = null)

See `__construct()`.

##### formatCurrency($value, $currency)

Fully supported.

##### format($value, $type = \NumberFormatter::TYPE_DEFAULT)

Only type `\NumberFormatter::TYPE_DEFAULT` is supported.

##### getAttribute($attr)

Fully supported.

##### getErrorCode()

Fully supported.

##### getErrorMessage()

Fully supported.

##### getLocale($type = \Locale::ACTUAL_LOCALE)

The parameter `$type` is ignored.

##### parse($value, $type = \NumberFormatter::TYPE_DOUBLE, &$position = null)

The supported types are `\NumberFormatter::TYPE_DOUBLE`,
`\NumberFormatter::TYPE_INT32` and `\NumberFormatter::TYPE_INT64`. The
parameter `$position` must always be `null`.

##### setAttribute($attr, $value)

The only supported attributes are `\NumberFormatter::FRACTION_DIGITS`,
`\NumberFormatter::GROUPING_USED` and `\NumberFormatter::ROUNDING_MODE`.

The only supported rounding modes are `\NumberFormatter::ROUND_HALFEVEN`,
`\NumberFormatter::ROUND_HALFDOWN` and `\NumberFormatter::ROUND_HALFUP`.

Included Resource Bundles
-------------------------

The ICU data is located in several "resource bundles". You can access a PHP
wrapper of these bundles through the static Intl class.

Languages and Scripts
~~~~~~~~~~~~~~~~~~~~~

The translations of language and script names can be found in the language
bundle.

    $languages = Intl::getLanguageBundle()->getLanguageNames();
    // => array('ab' => 'Abkhazian', ...)

    $language = Intl::getLanguageBundle()->getLanguageName('de');
    // => 'German'

    $language = Intl::getLanguageBundle()->getLanguageName('de', 'AT);
    // => 'Austrian German'

    $scripts = Intl::getLanguageBundle()->getScriptNames();
    // => array('Arab' => 'Arabic', ...)

    $script = Intl::getLanguageBundle()->getScriptName('Hans');
    // => 'Simplified'

Countries
~~~~~~~~~

The translations of country names can be found in the region bundle.

    $countries = Intl::getRegionBundle()->getCountryNames();
    // => array('AF' => 'Afghanistan', ...)

    $country = Intl::getRegionBundle()->getCountryName('GB');
    // => 'United Kingdom'

Locales
~~~~~~~

The translations of locale names can be found in the locale bundle.

    $locales = Intl::getLocaleBundle()->getLocaleNames();
    // => array('af' => 'Afrikaans', ...)

    $locale = Intl::getLocaleBundle()->getLocaleName('zh_Hans_MO');
    // => 'Chinese (Simplified, Macau SAR China)'

Currencies
~~~~~~~~~~

The translations of currency names and other currency-related information can
be found in the currency bundle.

    $currencies = Intl::getCurrencyBundle()->getCurrencyNames();
    // => array('AFN' => 'Afghan Afghani', ...)

    $currency = Intl::getCurrencyBundle()->getCurrencyNames('INR');
    // => 'Indian Rupee'

    $symbol = Intl::getCurrencyBundle()->getCurrencyNames('INR');
    // => '₹'

    $fractionDigits = Intl::getCurrencyBundle()->getFractionDigits('INR');
    // => 2

    $roundingIncrement = Intl::getCurrencyBundle()->getRoundingIncrement('INR');
    // => 0

Resources
---------

You can run the unit tests with the following command:

    $ cd path/to/Symfony/Component/Intl/
    $ composer.phar install --dev
    $ phpunit

[0]: /components/using_components
[1]: https://packagist.org/packages/symfony/intl
[2]: http://www.php.net/manual/en/class.locale.php
[3]: http://www.php.net/manual/en/class.numberformatter.php
[4]: http://www.php.net/manual/en/class.intldateformatter.php
[5]: http://www.php.net/manual/en/class.collator.php
[6]: http://www.php.net/manual/en/function.intl-error-name.php
[7]: http://www.php.net/manual/en/function.intl-get-error-code.php
[8]: http://www.php.net/manual/en/function.intl-get-error-message.php
[9]: http://www.php.net/manual/en/function.intl-is-failure.php
[10]: http://www.php.net/manual/en/intl.setup.php
