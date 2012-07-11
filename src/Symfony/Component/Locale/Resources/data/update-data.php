<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

function bailout($message)
{
    exit($message."\n");
}

function check_dir($source)
{
    if (!file_exists($source)) {
        bailout('The directory '.$source.' does not exist');
    }

    if (!is_dir($source)) {
        bailout('The file '.$source.' is not a directory');
    }
}

function check_command($command)
{
    exec('which '.$command, $output, $result);

    if ($result !== 0) {
        bailout('The command "'.$command.'" is not installed');
    }
}

function clear_directory($directory)
{
    $iterator = new \DirectoryIterator($directory);

    foreach ($iterator as $file) {
        if (!$file->isDot()) {
            if ($file->isDir()) {
                clear_directory($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }
    }
}

function make_directory($directory)
{
    if (!file_exists($directory)) {
        mkdir($directory);
    }

    if (!is_dir($directory)) {
        bailout('The file '.$directory.' already exists but is no directory');
    }
}

function list_files($directory, $extension)
{
    $files = array();
    $iterator = new \DirectoryIterator($directory);

    foreach ($iterator as $file) {
        if (!$file->isDot() && substr($file->getFilename(), -strlen($extension)) === $extension) {
            $files[] = substr($file->getFilename(), 0, -strlen($extension));
        }
    }

    return $files;
}

function genrb($source, $target)
{
    exec('genrb -d '.$target.' '.$source.DIRECTORY_SEPARATOR.'*.txt', $output, $result);

    if ($result !== 0) {
        bailout('genrb failed');
    }
}

function genrb_file($target, $source, $locale)
{
    exec('genrb -v -d '.$target.' '.$source.DIRECTORY_SEPARATOR.$locale.'.txt', $output, $result);

    if ($result !== 0) {
        bailout('genrb failed');
    }
}

function load_resource_bundle($locale, $directory)
{
    $bundle = \ResourceBundle::create($locale, $directory);

    if (null === $bundle) {
        bailout('The resource bundle for locale '.$locale.' could not be loaded from directory '.$directory);
    }

    return $bundle;
}

function get_data($index, $dataDir, $locale = 'en', $constraint = null)
{
    $data = array();
    $bundle = load_resource_bundle($locale, __DIR__.DIRECTORY_SEPARATOR.$dataDir);

    foreach ($bundle->get($index) as $code => $name) {
        if (null !== $constraint) {
            if ($constraint($code)) {
                $data[$code] = $name;
            }
            continue;
        }

        $data[$code] = $name;
    }

    $collator = new \Collator($locale);
    $collator->asort($data);

    return $data;
}

function create_stub_datafile($locale, $target, $data)
{
    $template = <<<TEMPLATE
<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

return %s;

TEMPLATE;

    $data = var_export($data, true);
    $data = preg_replace('/array \(/', 'array(', $data);
    $data = preg_replace('/\n {1,10}array\(/', 'array(', $data);
    $data = preg_replace('/  /', '    ', $data);
    $data = sprintf($template, $data);

    file_put_contents($target.DIRECTORY_SEPARATOR.$locale.'.php', $data);
}

if ($GLOBALS['argc'] !== 2) {
    bailout(<<<MESSAGE
Usage: php update-data.php [icu-data-directory]

Updates the ICU resources in Symfony2 from the given ICU data directory. You
can checkout the ICU data directory via SVN:

    $ svn co http://source.icu-project.org/repos/icu/icu/trunk/source/data icu-data

MESSAGE
    );
}

// Verify that all required directories exist
$source = $GLOBALS['argv'][1];

check_dir($source);

$source = realpath($source);

check_dir($source.DIRECTORY_SEPARATOR.'curr');
check_dir($source.DIRECTORY_SEPARATOR.'lang');
check_dir($source.DIRECTORY_SEPARATOR.'locales');
check_dir($source.DIRECTORY_SEPARATOR.'region');

check_command('genrb');

// Convert the *.txt resource bundles to *.res files
$target = __DIR__;
$currDir = $target.DIRECTORY_SEPARATOR.'curr';
$langDir = $target.DIRECTORY_SEPARATOR.'lang';
$localesDir = $target.DIRECTORY_SEPARATOR.'locales';
$namesDir = $target.DIRECTORY_SEPARATOR.'names';
$namesGeneratedDir = $namesDir.DIRECTORY_SEPARATOR.'generated';
$regionDir = $target.DIRECTORY_SEPARATOR.'region';

make_directory($currDir);
clear_directory($currDir);

genrb_file($currDir, $source.DIRECTORY_SEPARATOR.'curr', 'en');
genrb_file($currDir, $source.DIRECTORY_SEPARATOR.'curr', 'supplementalData');

// It seems \ResourceBundle does not like locale names with uppercase chars then we rename the binary file
// See: http://bugs.php.net/bug.php?id=54025
$filename_from = $currDir.DIRECTORY_SEPARATOR.'supplementalData.res';
$filename_to   = $currDir.DIRECTORY_SEPARATOR.'supplementaldata.res';

if (!rename($filename_from, $filename_to)) {
    bailout('The file '.$filename_from.' could not be renamed');
}

make_directory($langDir);
clear_directory($langDir);
genrb($source.DIRECTORY_SEPARATOR.'lang', $langDir);

make_directory($localesDir);
clear_directory($localesDir);
genrb($source.DIRECTORY_SEPARATOR.'locales', $localesDir);

make_directory($regionDir);
clear_directory($regionDir);
genrb($source.DIRECTORY_SEPARATOR.'region', $regionDir);

make_directory($namesDir);
clear_directory($namesDir);

make_directory($namesGeneratedDir);
clear_directory($namesGeneratedDir);

// Discover the list of supported locales, which are the names of the resource
// bundles in the "locales" directory
$supportedLocales = list_files($localesDir, '.res');

sort($supportedLocales);

// Delete unneeded locales
foreach ($supportedLocales as $key => $supportedLocale) {
    // Delete all aliases from the list
    // i.e., "az_AZ" is an alias for "az_Latn_AZ"
    $localeBundleOrig = file_get_contents($source.DIRECTORY_SEPARATOR.'locales'.DIRECTORY_SEPARATOR.$supportedLocale.'.txt');

    // The key "%%ALIAS" is not accessible through the \ResourceBundle class
    if (strpos($localeBundleOrig, '%%ALIAS') !== false) {
        unset($supportedLocales[$key]);
    }

    // Delete locales that have no content (i.e. only "Version" key)
    $localeBundle = load_resource_bundle($supportedLocale, $localesDir);

    // There seems to be no other way for identifying all keys in this specific
    // resource bundle
    $bundleKeys = array();

    foreach ($localeBundle as $bundleKey => $_) {
        $bundleKeys[] = $bundleKey;
    }

    if ($bundleKeys === array('Version')) {
        unset($supportedLocales[$key]);
    }
}

// Discover the list of locales for which individual language/region names
// exist. This list contains for example "de" and "de_CH", but not "de_DE" which
// is equal to "de"
$translatedLocales = array_unique(array_merge(
    list_files($langDir, '.res'),
    list_files($regionDir, '.res')
));

sort($translatedLocales);

// For each translated locale, generate a list of locale names
// Each locale name has the form: "Language (Script, Region, Variant1, ...)
// Script, Region and Variants are optional. If none of them is available,
// the braces are not printed.
foreach ($translatedLocales as $translatedLocale) {
    // Don't include ICU's root resource bundle
    if ($translatedLocale === 'root') {
        continue;
    }

    $langBundle = load_resource_bundle($translatedLocale, $langDir);
    $regionBundle = load_resource_bundle($translatedLocale, $regionDir);
    $localeNames = array();

    foreach ($supportedLocales as $supportedLocale) {
        // Don't include ICU's root resource bundle
        if ($supportedLocale === 'root') {
            continue;
        }

        $lang = \Locale::getPrimaryLanguage($supportedLocale);
        $script = \Locale::getScript($supportedLocale);
        $region = \Locale::getRegion($supportedLocale);
        $variants = \Locale::getAllVariants($supportedLocale);

        // Currently the only available variant is POSIX, which we don't want
        // to include in the list
        if (count($variants) > 0) {
            continue;
        }

        $langName = $langBundle->get('Languages')->get($lang);
        $extras = array();

        // Some languages are simply not translated
        // Example: "az" (Azerbaijani) has no translation in "af" (Afrikaans)
        if (!$langName) {
            continue;
        }

        // "af" (Afrikaans) has no "Scripts" block
        if (!$langBundle->get('Scripts')) {
            continue;
        }

        // "as" (Assamese) has no "Variants" block
        if (!$langBundle->get('Variants')) {
            continue;
        }

        // Discover the name of the script part of the locale
        // i.e. in zh_Hans_MO, "Hans" is the script
        if ($script) {
            // Some languages are translated together with their script,
            // i.e. "zh_Hans" is translated as "Simplified Chinese"
            if ($langBundle->get('Languages')->get($lang.'_'.$script)) {
                $langName = $langBundle->get('Languages')->get($lang.'_'.$script);

                // If the script is appended in braces, extract it
                // i.e. "zh_Hans" is translated as "Chinesisch (vereinfacht)"
                // in "de"
                if (strpos($langName, '(') !== false) {
                    list($langName, $scriptName) = preg_split('/[\s()]/', $langName, null, PREG_SPLIT_NO_EMPTY);

                    $extras[] = $scriptName;
                }
            } else {
                $scriptName = $langBundle->get('Scripts')->get($script);

                // Some scripts are not translated into every language
                if (!$scriptName) {
                    continue;
                }

                $extras[] = $scriptName;
            }
        }

        // Discover the name of the region part of the locale
        // i.e. in de_AT, "AT" is the region
        if ($region) {
            // Some languages are translated together with their region,
            // i.e. "en_GB" is translated as "British English"
            // we don't include these languages though because they mess up
            // the locale sorting
            // if ($langBundle->get('Languages')->get($lang.'_'.$region)) {
            //     $langName = $langBundle->get('Languages')->get($lang.'_'.$region);
            // } else {
            $regionName = $regionBundle->get('Countries')->get($region);

            // Some regions are not translated into every language
            if (!$regionName) {
                continue;
            }

            $extras[] = $regionName;
            // }
        }

        if (count($extras) > 0) {
            $langName .= ' ('.implode(', ', $extras).')';
        }

        $localeNames[$supportedLocale] = $langName;
    }

    // If no names could be generated for the current locale, skip it
    if (count($localeNames) === 0) {
        continue;
    }

    echo "Generating $translatedLocale...\n";

    $file = fopen($namesGeneratedDir.DIRECTORY_SEPARATOR.$translatedLocale.'.txt', 'w');

    fwrite($file, "$translatedLocale{\n");
    fwrite($file, "    Locales{\n");

    foreach ($localeNames as $supportedLocale => $langName) {
        fwrite($file, "        $supportedLocale{\"$langName\"}\n");
    }

    fwrite($file, "    }\n");
    fwrite($file, "}\n");
    fclose($file);
}

// Convert generated files to binary format
genrb($namesGeneratedDir, $namesDir);

// Clean up
clear_directory($namesGeneratedDir);
rmdir($namesGeneratedDir);

// Generate the data to the stubbed intl classes We only extract data for the 'en' locale
// The extracted data is used only by the stub classes
$defaultLocale = 'en';

$currencies = array();
$currenciesMeta = array();
$defaultMeta = array();

$bundle = load_resource_bundle('supplementaldata', __DIR__.'/curr');

foreach ($bundle->get('CurrencyMeta') as $code => $data) {
    // The 'DEFAULT' key contains the fraction digits and the rounding increment that are common for a lot of currencies
    // Only currencies with different values are added to the icu-data (e.g: CHF and JPY)
    if ('DEFAULT' == $code) {
        $defaultMeta = array(
            'fractionDigits'    => $data[0],
            'roundingIncrement' => $data[1],
        );

        continue;
    }

    $currenciesMeta[$code]['fractionDigits'] = $data[0];
    $currenciesMeta[$code]['roundingIncrement'] = $data[1];
}

$bundle = load_resource_bundle('en', __DIR__.'/curr');

foreach ($bundle->get('Currencies') as $code => $data) {
    $currencies[$code]['symbol'] = $data[0];
    $currencies[$code]['name']   = $data[1];

    if (!isset($currenciesMeta[$code])) {
        $currencies[$code]['fractionDigits'] = $defaultMeta['fractionDigits'];
        $currencies[$code]['roundingIncrement'] = $defaultMeta['roundingIncrement'];
        continue;
    }

    $currencies[$code]['fractionDigits'] = $currenciesMeta[$code]['fractionDigits'];
    $currencies[$code]['roundingIncrement'] = $currenciesMeta[$code]['roundingIncrement'];
}

// Countries.
$countriesConstraint = function($code) {
    // Global countries (f.i. "America") have numeric codes
    // Countries have alphabetic codes
    // "ZZ" is the code for unknown country
    if (ctype_alpha($code) && 'ZZ' !== $code) {
        return true;
    }

    return false;
};

$countries = get_data('Countries', 'region', $defaultLocale, $countriesConstraint);

// Languages
$languagesConstraint = function($code) {
    // "mul" is the code for multiple languages
    if ('mul' !== $code) {
        return true;
    }

    return false;
};

$languages = get_data('Languages', 'lang', $defaultLocale, $languagesConstraint);

// Display locales
$displayLocales = get_data('Locales', 'names', $defaultLocale);

// Create the stubs datafiles
$stubDir = $target.DIRECTORY_SEPARATOR.'stub';
$stubCurrDir = $stubDir.DIRECTORY_SEPARATOR.'curr';
$stubLangDir = $stubDir.DIRECTORY_SEPARATOR.'lang';
$stubNamesDir = $stubDir.DIRECTORY_SEPARATOR.'names';
$stubRegionDir = $stubDir.DIRECTORY_SEPARATOR.'region';

// Create the directories
make_directory($stubDir);
make_directory($stubCurrDir);
make_directory($stubLangDir);
make_directory($stubNamesDir);
make_directory($stubRegionDir);

clear_directory($stubCurrDir);
clear_directory($stubLangDir);
clear_directory($stubNamesDir);
clear_directory($stubRegionDir);

create_stub_datafile($defaultLocale, $stubCurrDir, $currencies);
create_stub_datafile($defaultLocale, $stubLangDir, $languages);
create_stub_datafile($defaultLocale, $stubNamesDir, $displayLocales);
create_stub_datafile($defaultLocale, $stubRegionDir, $countries);

// Clean up
clear_directory($currDir);
rmdir($currDir);
