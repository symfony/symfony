<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

function bailout($message)
{
    die($message."\n");
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

function load_resource_bundle($locale, $directory)
{
    $bundle = new \ResourceBundle($locale, $directory);

    if (null === $bundle) {
        bailout('The resource bundle for locale '.$locale.' could not be loaded from directory '.$directory);
    }

    return $bundle;
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

check_dir($source.DIRECTORY_SEPARATOR.'lang');
check_dir($source.DIRECTORY_SEPARATOR.'locales');
check_dir($source.DIRECTORY_SEPARATOR.'region');

check_command('genrb');

// Convert the *.txt resource bundles to *.res files
$target = __DIR__;
$langDir = $target.DIRECTORY_SEPARATOR.'lang';
$localesDir = $target.DIRECTORY_SEPARATOR.'locales';
$namesDir = $target.DIRECTORY_SEPARATOR.'names';
$namesGeneratedDir = $namesDir.DIRECTORY_SEPARATOR.'generated';
$regionDir = $target.DIRECTORY_SEPARATOR.'region';

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
            $langName .= ' (' . implode(', ', $extras) . ')';
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
