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

if ('cli' !== \PHP_SAPI) {
    throw new \Exception('This script must be run from the command line.');
}

/*
 * This script syncs IBAN formats from the upstream and updates them into IbanValidator.
 *
 * Usage:
 *   php Resources/bin/sync-iban-formats.php
 */

error_reporting(\E_ALL);

set_error_handler(static function (int $type, string $msg, string $file, int $line): void {
    throw new \ErrorException($msg, 0, $type, $file, $line);
});

echo "Collecting IBAN formats...\n";

$formats = array_merge(
    (new WikipediaIbanProvider())->getIbanFormats(),
    (new SwiftRegistryIbanProvider())->getIbanFormats()
);

printf("Collected %d IBAN formats\n", count($formats));

echo "Updating validator...\n";

updateValidatorFormats(__DIR__.'/../../Constraints/IbanValidator.php', $formats);

echo "Done.\n";

exit(0);

function updateValidatorFormats(string $validatorPath, array $formats): void
{
    ksort($formats);

    $formatsContent = "[\n";
    $formatsContent .= "        // auto-generated\n";

    foreach ($formats as $countryCode => [$format, $country]) {
        $formatsContent .= "        '{$countryCode}' => '{$format}', // {$country}\n";
    }

    $formatsContent .= '    ]';

    $validatorContent = file_get_contents($validatorPath);

    $validatorContent = preg_replace(
        '/FORMATS = \[.*?\];/s',
        "FORMATS = {$formatsContent};",
        $validatorContent
    );

    file_put_contents($validatorPath, $validatorContent);
}

final class SwiftRegistryIbanProvider
{
    /**
     * @return array<string, array{string, string}>
     */
    public function getIbanFormats(): array
    {
        $items = $this->readPropertiesFromRegistry([
            'Name of country' => 'country',
            'IBAN prefix country code (ISO 3166)' => 'country_code',
            'IBAN structure' => 'iban_structure',
            'Country code includes other countries/territories' => 'included_country_codes',
        ]);

        $formats = [];

        foreach ($items as $item) {
            $formats[$item['country_code']] = [$this->buildIbanRegexp($item['iban_structure']), $item['country']];

            foreach ($this->parseCountryCodesList($item['included_country_codes']) as $includedCountryCode) {
                $formats[$includedCountryCode] = $formats[$item['country_code']];
            }
        }

        return $formats;
    }

    /**
     * @return list<string>
     */
    private function parseCountryCodesList(string $countryCodesList): array
    {
        if ('N/A' === $countryCodesList) {
            return [];
        }

        $countryCodes = [];

        foreach (explode(',', $countryCodesList) as $countryCode) {
            $countryCodes[] = preg_replace('/^([A-Z]{2})(\s+\(.+?\))?$/', '$1', trim($countryCode));
        }

        return $countryCodes;
    }

    /**
     * @param array<string, string> $properties
     *
     * @return list<array<string, string>>
     */
    private function readPropertiesFromRegistry(array $properties): array
    {
        $items = [];

        $registryContent = file_get_contents('https://www.swift.com/swift-resource/11971/download');
        $lines = explode("\n", $registryContent);

        // skip header line
        array_shift($lines);

        foreach ($lines as $line) {
            $columns = str_getcsv($line, "\t");
            $propertyLabel = array_shift($columns);

            if (!isset($properties[$propertyLabel])) {
                continue;
            }

            $propertyField = $properties[$propertyLabel];

            foreach ($columns as $index => $value) {
                $items[$index][$propertyField] = $value;
            }
        }

        return array_values($items);
    }

    private function buildIbanRegexp(string $ibanStructure): string
    {
        $pattern = $ibanStructure;

        $pattern = preg_replace('/(\d+)!n/', '\\d{$1}', $pattern);
        $pattern = preg_replace('/(\d+)!a/', '[A-Z]{$1}', $pattern);
        $pattern = preg_replace('/(\d+)!c/', '[\\dA-Z]{$1}', $pattern);

        return $pattern;
    }
}

final class WikipediaIbanProvider
{
    /**
     * @return array<string, array{string, string}>
     */
    public function getIbanFormats(): array
    {
        $formats = [];

        foreach ($this->readIbanFormatsTable() as $item) {
            if (!preg_match('/^([A-Z]{2})/', $item['Example'], $matches)) {
                continue;
            }

            $countryCode = $matches[1];

            $formats[$countryCode] = [$this->buildIbanRegexp($countryCode, $item['BBAN Format']), $item['Country']];
        }

        return $formats;
    }

    /**
     * @return list<array<string, string|int>>
     */
    private function readIbanFormatsTable(): array
    {
        $tablesResponse = file_get_contents('https://www.wikitable2json.com/api/International_Bank_Account_Number?table=3&keyRows=1&clearRef=true');

        return json_decode($tablesResponse, true, 512, JSON_THROW_ON_ERROR)[0];
    }

    private function buildIbanRegexp(string $countryCode, string $bbanFormat): string
    {
        $pattern = $bbanFormat;

        $pattern = preg_replace('/\s*,\s*/', '', $pattern);
        $pattern = preg_replace('/(\d+)n/', '\\d{$1}', $pattern);
        $pattern = preg_replace('/(\d+)a/', '[A-Z]{$1}', $pattern);
        $pattern = preg_replace('/(\d+)c/', '[\\dA-Z]{$1}', $pattern);

        return $countryCode.'\\d{2}'.$pattern;
    }
}
