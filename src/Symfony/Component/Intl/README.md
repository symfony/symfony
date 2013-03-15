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

### IntlDateFormatter

Dates can be formatted with the [`\IntlDateFormatter`] [4] class. The following
methods are supported. All other methods are not supported and will throw an
exception when used.

##### __construct($locale, $datetype, $timetype, $timezone = null, $calendar = \IntlDateFormatter::GREGORIAN, $pattern = null)

The only supported locale is "en". The parameter `$calendar` can only be
`\IntlDateFormatter::GREGORIAN`.

##### ::create($locale, $datetype, $timetype, $timezone = null, $calendar = self::GREGORIAN, $pattern = null)

See `__construct()`.

##### format($timestamp)

Fully supported.

##### getCalendar()

Fully supported.

##### getDateType()

Fully supported.

##### getErrorCode()

Fully supported.

##### getErrorMessage()

Fully supported.

##### getLocale($type = StubLocale::ACTUAL_LOCALE)

The parameter `$type` is ignored.

##### getPattern()

Fully supported.

##### getTimeType()

Fully supported.

##### getTimeZoneId()

Fully supported.

##### isLenient()

Always returns `false`.

##### parse($value, &$position = null)

The parameter `$position` must always be `null`.

##### setLenient($lenient)

Only accepts `false`.

##### setPattern($pattern)

Fully supported.

##### setTimeZoneId($timeZoneId)

Fully supported.

##### setTimeZone($timeZone)

Fully supported.

### Collator

Localized strings can be sorted with the [`\Collator`] [5] class. The following
methods are supported. All other methods are not supported and will throw an
exception when used.

##### __construct($locale)

The only supported locale is "en".

##### create($locale)

See `__construct()`.

##### asort(&$array, $sortFlag = self::SORT_REGULAR)

Fully supported.

##### getErrorCode()

Fully supported.

##### getErrorMessage()

Fully supported.

##### getLocale($type = StubLocale::ACTUAL_LOCALE)

The parameter `$type` is ignored.

### ResourceBundle

The `\ResourceBundle` class is not and will not be supported. Instead, this
component ships a set of readers and writers for reading and writing arrays
(or array-like objects) from/to resource bundle files. The following classes
are supported:

##### TextBundleWriter

Writes an array or an array-like object to a plain text resource bundle. The
resulting .txt file can be converted to a binary .res file with the
`BundleCompiler` class.

    use Symfony\Component\Intl\ResourceBundle\Writer\TextBundleWriter;
    use Symfony\Component\Intl\ResourceBundle\Compiler\BundleCompiler;

    $writer = new TextBundleWriter();
    $writer->write('/path/to/bundle', 'en', array(
        'Data' => array(
            'entry1',
            'entry2',
            ...
        ),
    ));

    $compiler = new BundleCompiler();
    $compiler->compile('/path/to/bundle', '/path/to/binary/bundle');

The command "genrb" must be available for the `BundleCompiler` to work. If the
command is located in a non-standard location, you can pass its path to the
`BundleCompiler` constructor.

##### PhpBundleWriter

Writes an array or an array-like object to a .php resource bundle.

    use Symfony\Component\Intl\ResourceBundle\Writer\PhpBundleWriter;

    $writer = new PhpBundleWriter();
    $writer->write('/path/to/bundle', 'en', array(
        'Data' => array(
            'entry1',
            'entry2',
            ...
        ),
    ));

##### BinaryBundleReader

Reads binary resource bundle files and returns an array or an array-like object.
This class currently only works with the intl extension installed.

    use Symfony\Component\Intl\ResourceBundle\Reader\BinaryBundleReader;

    $reader = new BinaryBundleReader();
    $data = $reader->read('/path/to/bundle', 'en');

    echo $data['Data']['entry1'];

##### PhpBundleReader

Reads resource bundles from .php files and returns an array or an array-like
object.

    use Symfony\Component\Intl\ResourceBundle\Reader\PhpBundleReader;

    $reader = new PhpBundleReader();
    $data = $reader->read('/path/to/bundle', 'en');

    echo $data['Data']['entry1'];

##### BufferedBundleReader

Wraps another reader, but keeps the last N reads in a buffer, where N is a
buffer size passed to the constructor.

    use Symfony\Component\Intl\ResourceBundle\Reader\BinaryBundleReader;
    use Symfony\Component\Intl\ResourceBundle\Reader\BufferedBundleReader;

    $reader = new BufferedBundleReader(new BinaryBundleReader(), 10);

    // actually reads the file
    $data = $reader->read('/path/to/bundle', 'en');

    // returns data from the buffer
    $data = $reader->read('/path/to/bundle', 'en');

    // actually reads the file
    $data = $reader->read('/path/to/bundle', 'fr');

##### StructuredBundleReader

Wraps another reader and offers a `readEntry()` method for reading an entry
of the resource bundle without having to worry whether array keys are set or
not. If a path cannot be resolved, `null` is returned.

    use Symfony\Component\Intl\ResourceBundle\Reader\BinaryBundleReader;
    use Symfony\Component\Intl\ResourceBundle\Reader\StructuredBundleReader;

    $reader = new StructuredBundleReader(new BinaryBundleReader());

    $data = $reader->read('/path/to/bundle', 'en');

    // Produces an error if the key "Data" does not exist
    echo $data['Data']['entry1'];

    // Returns null if the key "Data" does not exist
    echo $reader->readEntry('/path/to/bundle', 'en', array('Data', 'entry1'));

Additionally, the `readEntry()` method resolves fallback locales. For example,
the fallback locale of "en_GB" is "en". For single-valued entries (strings,
numbers etc.), the entry will be read from the fallback locale if it cannot be
found in the more specific locale. For multi-valued entries (arrays), the
values of the more specific and the fallback locale will be merged. In order
to suppress this behavior, the last parameter `$fallback` can be set to `false`.

    echo $reader->readEntry('/path/to/bundle', 'en', array('Data', 'entry1'), false);

Included Resource Bundles
-------------------------

The ICU data is located in several "resource bundles". You can access a PHP
wrapper of these bundles through the static `Intl` class.

Languages and Scripts
~~~~~~~~~~~~~~~~~~~~~

The translations of language and script names can be found in the language
bundle.

    use Symfony\Component\Intl\Intl;

    \Locale::setDefault('en');

    $languages = Intl::getLanguageBundle()->getLanguageNames();
    // => array('ab' => 'Abkhazian', ...)

    $language = Intl::getLanguageBundle()->getLanguageName('de');
    // => 'German'

    $language = Intl::getLanguageBundle()->getLanguageName('de', 'AT');
    // => 'Austrian German'

    $scripts = Intl::getLanguageBundle()->getScriptNames();
    // => array('Arab' => 'Arabic', ...)

    $script = Intl::getLanguageBundle()->getScriptName('Hans');
    // => 'Simplified'

All methods accept the translation locale as last, optional parameter, which
defaults to the current default locale.

    $languages = Intl::getLanguageBundle()->getLanguageNames('de');
    // => array('ab' => 'Abchasisch', ...)

Countries
~~~~~~~~~

The translations of country names can be found in the region bundle.

    use Symfony\Component\Intl\Intl;

    \Locale::setDefault('en');

    $countries = Intl::getRegionBundle()->getCountryNames();
    // => array('AF' => 'Afghanistan', ...)

    $country = Intl::getRegionBundle()->getCountryName('GB');
    // => 'United Kingdom'

All methods accept the translation locale as last, optional parameter, which
defaults to the current default locale.

    $countries = Intl::getRegionBundle()->getCountryNames('de');
    // => array('AF' => 'Afghanistan', ...)

Locales
~~~~~~~

The translations of locale names can be found in the locale bundle.

    use Symfony\Component\Intl\Intl;

    \Locale::setDefault('en');

    $locales = Intl::getLocaleBundle()->getLocaleNames();
    // => array('af' => 'Afrikaans', ...)

    $locale = Intl::getLocaleBundle()->getLocaleName('zh_Hans_MO');
    // => 'Chinese (Simplified, Macau SAR China)'

All methods accept the translation locale as last, optional parameter, which
defaults to the current default locale.

    $locales = Intl::getLocaleBundle()->getLocaleNames('de');
    // => array('af' => 'Afrikaans', ...)

Currencies
~~~~~~~~~~

The translations of currency names and other currency-related information can
be found in the currency bundle.

    use Symfony\Component\Intl\Intl;

    \Locale::setDefault('en');

    $currencies = Intl::getCurrencyBundle()->getCurrencyNames();
    // => array('AFN' => 'Afghan Afghani', ...)

    $currency = Intl::getCurrencyBundle()->getCurrencyName('INR');
    // => 'Indian Rupee'

    $symbol = Intl::getCurrencyBundle()->getCurrencySymbol('INR');
    // => '₹'

    $fractionDigits = Intl::getCurrencyBundle()->getFractionDigits('INR');
    // => 2

    $roundingIncrement = Intl::getCurrencyBundle()->getRoundingIncrement('INR');
    // => 0

All methods (except for `getFractionDigits()` and `getRoundingIncrement()`)
accept the translation locale as last, optional parameter, which defaults to the
current default locale.

    $currencies = Intl::getCurrencyBundle()->getCurrencyNames('de');
    // => array('AFN' => 'Afghanische Afghani', ...)

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
