#!/usr/bin/env php
<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Generates translation files for use in TimezoneType based on the Unicode
 * Common Locale Data Repository.
 *
 * The generates files are written to Resources/translations and should be
 * committed to Git.
 */
$componentDir = realpath(__DIR__.'/../..');
$autoloadFile = $componentDir.'/vendor/autoload.php';

if (!is_file($autoloadFile)) {
    die("$autoloadFile not found; run `composer update` in $componentDir.\n");
}

require_once $autoloadFile;

use Symfony\Component\Form\Extension\Core\Type\TimezoneType;
use Symfony\Component\Translation\Dumper\XliffFileDumper;
use Symfony\Component\Translation\MessageCatalogue;

// We only support a small subset of the many locales included in CLDR.
$locales = array(
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
    'nn' => 'nn',
    'no' => 'no',
    'pl' => 'pl',
    'pt' => 'pt',
    'pt_BR' => 'pt_BR',
    'ro' => 'ro',
    'ru' => 'ru',
    'sk' => 'sk',
    'sl' => 'sl',
    'sr_Cyrl' => 'sr',
    'sr_Latn' => 'sr_Latn',
    'sv' => 'sv',
    'tl' => 'tl',
    'uk' => 'uk',
    'zh_CN' => 'zh_Hans_CN',
);

// Timezone identifiers containing 2 slashes need a qualifier in addition to
// just the city name. E.g. America/Argentina/San_Juan needs to be distinguished
// from other cities named San Juan. On the other hand, for US states the CLDR
// string already contains the qualifier, e.g. the English string fo
// America/Indiana/Knox is "Knox, Indiana".
$countries = array(
    'America/Argentina' => 'AR',
    'America/Indiana' => false,
    'America/Kentucky' => false,
    'America/North_Dakota' => false,
);

// Not all tzdata areas (the string before the first slash) correspond to
// territories definded in CLDR.
$areas = array(
    'America' => '019',
    'Africa' => '002',
    'Antarctica' => 'AQ',
    'Arctic' => false,
    'Asia' => '142',
    'Atlantic' => false,
    'Australia' => '053',
    'Europe' => '150',
    'Indian' => false,
    'Pacific' => false,
    'Other' => false,
);

$options = array(
    'path' => $componentDir.'/Resources/translations/generated',
    'default_locale' => 'en',
    'tool_info' => array(
        'tool-id' => basename(__FILE__),
        'tool-name' => 'CLDR time zone translation generator',
    ),
);

$timezones = TimezoneType::getTimezones(\DateTimeZone::ALL);

$domain = 'timezones';

$identifiers = \DateTimeZone::listIdentifiers(\DateTimeZone::ALL_WITH_BC);

foreach ($locales as $locale => $cldrLocale) {
    $names = array();

    foreach ($timezones as $area => $identifiers) {
        if (!isset($areas[$area])) {
            die("Area $area not \$areas.\n");
        }
        if ($areas[$area]) {
            $areaName = Punic\Territory::getName($areas[$area], $cldrLocale);
            if ($areaName) {
                $names[$area] = $areaName;
            }
        }

        foreach ($identifiers as $key => $identifier) {
            $timezoneName = Punic\Calendar::getTimezoneExemplarCity($identifier, false, $cldrLocale);

            $parts = explode('/', $identifier);
            if (count($parts) >= 3) {
                $prefix = $parts[0].'/'.$parts[1];
                if (!isset($countries[$prefix])) {
                    die("Prefix $prefix not defined in \$countries.\n");
                }
                if ($countries[$prefix]) {
                    $countryName = Punic\Territory::getName($countries[$prefix], $cldrLocale);
                    $timezoneName .= ', '.$countryName;
                }
            }

            if ($timezoneName) {
                $names[$key] = $timezoneName;
            }
        }
    }

    $catalogue = new MessageCatalogue($locale);
    $catalogue->add($names, $domain);

    $dumper = new XliffFileDumper();
    $dumper->dump($catalogue, $options);
}
