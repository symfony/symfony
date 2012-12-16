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

function genrb($source, $target, $icuBinPath = '', $params = '')
{
    exec($icuBinPath.'genrb --quiet '.$params.' -d '.$target.' '.$source.DIRECTORY_SEPARATOR.'*.txt', $output, $result);

    if ($result !== 0) {
        bailout('genrb failed');
    }
}

function genrb_file($target, $source, $locale, $icuBinPath = '')
{
    exec($icuBinPath.'genrb --quiet -d '.$target.' '.$source.DIRECTORY_SEPARATOR.$locale.'.txt', $output, $result);

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
    $bundle = load_resource_bundle($locale, $dataDir);

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

function icu_version()
{
    exec('icu-config --version', $output, $result);

    if ($result !== 0 || !isset($output[0])) {
        bailout('icu-config failed');
    }

    return $output[0];
}

function normalize_icu_version($version)
{
    preg_match('/^(?P<version>[0-9]\.[0-9]|[0-9]{2,})/', $version, $matches);

    return $matches['version'];
}

function download_icu_data($version)
{
    $icu = parse_ini_file(__DIR__.'/icu.ini');

    if (!isset($icu[$version])) {
        bailout('The version '.$version.' is not available in the datasource.ini file.');
    }

    $checkoutPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'data-'.$version;

    exec('svn checkout '.$icu[$version].' '.$checkoutPath, $output, $result);

    if ($result !== 0) {
        bailout('svn failed');
    }

    return $checkoutPath;
}

function is_icu_version_42_or_earlier($version)
{
    return version_compare($version, '4.4', '<');
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

    ksort($data);
    $data = var_export($data, true);
    $data = preg_replace('/array \(/', 'array(', $data);
    $data = preg_replace('/\n {1,10}array\(/', 'array(', $data);
    $data = preg_replace('/  /', '    ', $data);
    $data = sprintf($template, $data);

    file_put_contents($target.DIRECTORY_SEPARATOR.$locale.'.php', $data);
}

function create_svn_info_file($source, $target)
{
    exec('svn info --xml '.$source, $output, $result);

    $xml = simplexml_load_string(implode("\n", $output));

    if ($result !== 0) {
        bailout('svn info failed');
    }

    $url = (string) $xml->entry->url;
    $revision = (string) $xml->entry->commit['revision'];
    $author = (string) $xml->entry->commit->author;
    $date = (string) $xml->entry->commit->date;

    $data = <<<SVN_INFO_DATA
SVN info data
=============

URL: $url
Revision: $revision
Author: $author
Date: $date

SVN_INFO_DATA;

    file_put_contents($target.DIRECTORY_SEPARATOR.'svn-info.txt', $data);
}

if ($GLOBALS['argc'] < 1) {
    bailout(<<<MESSAGE
Usage: php build-data.php [icu-version] [icu-binaries-path]

Builds or update the ICU data in Symfony2 for a specific ICU version. If the
ICU version or the path to the binaries are not provided, it will build the
latest version released using the genrb found in the environment path.

Examples:

    php build-data.php 4.2
    It will build the ICU data using the 49 version data

    php build-data.php 4.2 /path/to/icu/bin
    It will build the ICU data using the 49 version data and the genrb and
    icu-config binaries found in the environment path

It is recommended to use the ICU binaries in the same version of the desired
version to build the data files.

Read the UPDATE.txt file for more info.

MESSAGE
    );
}

check_command('svn');

// Script options
$version = isset($GLOBALS['argv'][1]) ? $GLOBALS['argv'][1] : icu_version();
$icuBinPath = isset($GLOBALS['argv'][2]) ? $GLOBALS['argv'][2] : '';

// Slash the path
if ('' != $icuBinPath && (strrpos($icuBinPath, DIRECTORY_SEPARATOR) + 1 != strlen($icuBinPath))) {
    $icuBinPath .= DIRECTORY_SEPARATOR;
}

check_command($icuBinPath.'genrb');

$version = normalize_icu_version($version);
$source = download_icu_data($version);

// Verify that all required directories exist
check_dir($source);
check_dir($source.DIRECTORY_SEPARATOR.'.svn');

$source = realpath($source);

// Currency, language and region data are bundled in the locales directory in ICU <= 4.2
if (!is_icu_version_42_or_earlier($version)) {
    check_dir($source.DIRECTORY_SEPARATOR.'curr');
    check_dir($source.DIRECTORY_SEPARATOR.'lang');
    check_dir($source.DIRECTORY_SEPARATOR.'region');
}

check_dir($source.DIRECTORY_SEPARATOR.'locales');

// Convert the *.txt resource bundles to *.res files
$target = $version;
$target = __DIR__.DIRECTORY_SEPARATOR.$target;
$currDir = $target.DIRECTORY_SEPARATOR.'curr';
$langDir = $target.DIRECTORY_SEPARATOR.'lang';
$localesDir = $target.DIRECTORY_SEPARATOR.'locales';
$namesDir = $target.DIRECTORY_SEPARATOR.'names';
$namesGeneratedDir = $namesDir.DIRECTORY_SEPARATOR.'generated';
$regionDir = $target.DIRECTORY_SEPARATOR.'region';

make_directory($target);
clear_directory($target);
create_svn_info_file($source, $target);

make_directory($currDir);
clear_directory($currDir);

// Currency data is available at the locales data files in ICU <= 4.2 and the supplementalData file is available at the
// misc directory
if (is_icu_version_42_or_earlier($version)) {
    genrb_file($currDir, $source.DIRECTORY_SEPARATOR.'locales', 'en', $icuBinPath);
    genrb_file($currDir, $source.DIRECTORY_SEPARATOR.'misc', 'supplementalData', $icuBinPath);
} else {
    genrb_file($currDir, $source.DIRECTORY_SEPARATOR.'curr', 'en', $icuBinPath);
    genrb_file($currDir, $source.DIRECTORY_SEPARATOR.'curr', 'supplementalData', $icuBinPath);
}

// It seems \ResourceBundle does not like locale names with uppercase chars then we rename the binary file
// See: http://bugs.php.net/bug.php?id=54025
$filename_from = $currDir.DIRECTORY_SEPARATOR.'supplementalData.res';
$filename_to   = $currDir.DIRECTORY_SEPARATOR.'supplementaldata.res';

if (!rename($filename_from, $filename_to)) {
    bailout('The file '.$filename_from.' could not be renamed');
}

make_directory($langDir);
clear_directory($langDir);

// Language data is available at the locales data files in ICU <= 4.2
if (is_icu_version_42_or_earlier($version)) {
    genrb($source.DIRECTORY_SEPARATOR.'locales', $langDir, $icuBinPath);
} else {
    genrb($source.DIRECTORY_SEPARATOR.'lang', $langDir, $icuBinPath);
}

make_directory($localesDir);
clear_directory($localesDir);
genrb($source.DIRECTORY_SEPARATOR.'locales', $localesDir, $icuBinPath);

make_directory($regionDir);
clear_directory($regionDir);

// Region data is available at the locales data files in ICU <= 4.2
if (is_icu_version_42_or_earlier($version)) {
    genrb($source.DIRECTORY_SEPARATOR.'locales', $regionDir, $icuBinPath);
} else {
    genrb($source.DIRECTORY_SEPARATOR.'region', $regionDir, $icuBinPath);
}

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
// Even if the source and generated file are UTF-8 encoded, for some reason the data seems not correctly encoded, leading to
// a parse error in genrb (Stopped parsing resource with U_ILLEGAL_CHAR_FOUND). So we call the genrb passing that the source
// files are UTF-8 encoded.
if (is_icu_version_42_or_earlier($version)) {
    genrb($namesGeneratedDir, $namesDir, $icuBinPath, '-e UTF-8');
} else {
    genrb($namesGeneratedDir, $namesDir, $icuBinPath);
}

// Clean up
clear_directory($namesGeneratedDir);
rmdir($namesGeneratedDir);

// Generate the data to the stubbed intl classes We only extract data for the 'en' locale
// The extracted data is used only by the stub classes
$defaultLocale = 'en';

$currencies = array();
$currenciesMeta = array();
$defaultMeta = array();

$bundle = load_resource_bundle('supplementaldata', $currDir);

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

$bundle = load_resource_bundle('en', $currDir);

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

$countries = get_data('Countries', $regionDir, $defaultLocale, $countriesConstraint);

// Languages
$languagesConstraint = function($code) {
    // "mul" is the code for multiple languages
    if ('mul' !== $code) {
        return true;
    }

    return false;
};

$languages = get_data('Languages', $langDir, $defaultLocale, $languagesConstraint);

// Display locales
$displayLocales = get_data('Locales', $namesDir, $defaultLocale);

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
