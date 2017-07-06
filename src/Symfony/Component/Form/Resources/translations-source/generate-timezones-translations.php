#!/usr/bin/env php
<?php

/**
 * Generates translation files for use in TimezoneType based on the Unicode 
 * Common Locale Data Repository.
 *
 * The generates files are written to ../translations and should be committed to
 * Git.
 *
 * Before running the script, download core.zip from the latest CLDR release
 * from http://cldr.unicode.org/index/downloads. Unzip the file into a
 * subdirectory of the directory containing this PHP script.
 *
 * Run the following commands inside the translations-source directory:
 * $ mkdir cldr
 * $ wget http://unicode.org/Public/cldr/XXX/core.zip  <-- replace XXX with the latest version
 * $ unzip core.zip
 */
require_once '../../../../../../vendor/autoload.php';

use Symfony\Component\Translation\Dumper\XliffFileDumper;
use Symfony\Component\Translation\Loader\XliffFileLoader;
use Symfony\Component\Translation\MessageCatalogue;

/**
 * Translates a time zone identifier into an English string.
 *
 * Uses the algorithm used in TimezoneType::getTimezones().
 *
 * @param string $identifier
 *
 * @return string
 */
function getSourceName($identifier)
{
    $parts = explode('/', $identifier);

    if (count($parts) > 2) {
        $name = $parts[1].' - '.$parts[2];
    } elseif (count($parts) > 1) {
        $name = $parts[1];
    } else {
        $name = $parts[0];
    }

    return str_replace('_', ' ', $name);
}

$locales = [
    // English must be processed first.
    'en' => 'en',

    // We only support a small subset of the many locales included in CLDR.
    'ar' => 'ar',
    'az' => 'az',
    'bg' => 'bg',
    'ca' => 'ca',
    'cs' => 'cs',
    'da' => 'da',
    'de' => 'de',
    'el' => 'el',
    'en' => 'en',
    'es' => 'es',
    'et' => 'et',
    'eu' => 'eu',
    'fa' => 'fa',
    'fi' => 'fi',
    'fr' => 'fr',
    'gl' => 'gl',
    'he' => 'he',
    'hr' => 'hr',
    'hu' => 'hu',
    'hy' => 'hy',
    'id' => 'id',
    'it' => 'it',
    'ja' => 'ja',
    'lb' => 'lb',
    'lt' => 'lt',
    'lv' => 'lv',
    'mn' => 'mn',
    'nb' => 'nb',
    'nl' => 'nl',
    'pl' => 'pl',
    'pt_BR' => 'pt_BR',
    'pt' => 'pt',
    'ro' => 'ro',
    'ru' => 'ru',
    'sk' => 'sk',
    'sl' => 'sl',
    'sr_Cyrl' => 'sr',
    'sr_Latn' => 'sr_Latn',
    'sv' => 'sv',
    'uk' => 'uk',
    'zh_CN' => 'zh_Hans_CN',
];

$options = array(
    'path' => __DIR__.'/../translations',
    'default_locale' => 'en',
    'tool_info' => array(
        'tool-id' => basename(__FILE__),
        'tool-name' => 'Time zone translation generator',
    ),
);

// The source names are generated from the list identifiers. They are English
// names, except they miss some punctuation and diacritics. E.g. the identifier
// America/St_Barthelemy has the source name "St Barthelemy", but the proper
// English name is "St. Barthélemy".
$sourceNames = array();
foreach (\DateTimeZone::listIdentifiers(\DateTimeZone::ALL_WITH_BC) as $identifier) {
    $sourceNames[$identifier] = getSourceName($identifier);
}

$domain = 'timezones';
$dumper = new XliffFileDumper();
$loader = new XliffFileLoader();

foreach ($locales as $locale => $cldrLocale) {
    $localeNames = array();

    $cldrFile = __DIR__.'/cldr/common/main/'.$cldrLocale.'.xml';
    if (!file_exists($cldrFile)) {
        throw new \RuntimeException('Source file not found: '.$cldrFile);
    }

    $xml = simplexml_load_file($cldrFile);

    if (!isset($xml->dates->timeZoneNames)) {
        $cldrLocale = (string) $xml->identity->language->attributes()->type;
        $cldrFile = __DIR__.'/cldr/common/main/'.$cldrLocale.'.xml';
        if (!file_exists($cldrFile)) {
            throw new \RuntimeException('Fallback source file not found: '.$cldrFile);
        }

        $xml = simplexml_load_file($cldrFile);

        if (!isset($xml->dates->timeZoneNames)) {
            throw new \RuntimeException('No time zone info defined in fallback locale: '.$cldrFile);
        }
    }

    foreach ($xml->dates->timeZoneNames->zone as $zone) {
        $identifier = (string) $zone->attributes()->type;
        $localeName = (string) $zone->exemplarCity;

        // Add time zones that exist in the CLDR data but is not (yet) known by
        // PHP on this computer.
        if ($locale == 'en' && !isset($sourceNames[$identifier])) {
            $sourceNames[$identifier] = getSourceName($identifier);
        }

        if ($localeName && isset($sourceNames[$identifier])) {
            $enName = $sourceNames[$identifier];
            $localeNames[$enName] = $localeName;
        }
    }

    $catalogue = new MessageCatalogue($locale);
    $catalogue->add($localeNames, $domain);

    // Load additional translations (including continent names) and overrides.
    $extraFile = __DIR__.'/timezones.'.$locale.'.xlf';
    if (file_exists($extraFile)) {
        $extraCatalogue = $loader->load($extraFile, $locale, $domain);
        $catalogue->addCatalogue($extraCatalogue);
    }

    $dumper->dump($catalogue, $options);
}
