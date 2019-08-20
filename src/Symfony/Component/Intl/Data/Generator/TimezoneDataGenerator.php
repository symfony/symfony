<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\Data\Generator;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Intl\Data\Bundle\Compiler\BundleCompilerInterface;
use Symfony\Component\Intl\Data\Bundle\Reader\BundleEntryReaderInterface;
use Symfony\Component\Intl\Data\Util\ArrayAccessibleResourceBundle;
use Symfony\Component\Intl\Data\Util\LocaleScanner;
use Symfony\Component\Intl\Exception\MissingResourceException;
use Symfony\Component\Intl\Locale;

/**
 * The rule for compiling the zone bundle.
 *
 * @author Roland Franssen <franssen.roland@gmail.com>
 *
 * @internal
 */
class TimezoneDataGenerator extends AbstractDataGenerator
{
    use FallbackTrait;

    /**
     * Collects all available zone IDs.
     *
     * @var string[]
     */
    private $zoneIds = [];
    private $zoneToCountryMapping = [];
    private $localeAliases = [];

    /**
     * {@inheritdoc}
     */
    protected function scanLocales(LocaleScanner $scanner, string $sourceDir): array
    {
        $this->localeAliases = $scanner->scanAliases($sourceDir.'/locales');

        return $scanner->scanLocales($sourceDir.'/zone');
    }

    /**
     * {@inheritdoc}
     */
    protected function compileTemporaryBundles(BundleCompilerInterface $compiler, string $sourceDir, string $tempDir)
    {
        $filesystem = new Filesystem();
        $filesystem->mkdir($tempDir.'/region');
        $compiler->compile($sourceDir.'/region', $tempDir.'/region');
        $compiler->compile($sourceDir.'/zone', $tempDir);
        $compiler->compile($sourceDir.'/misc/timezoneTypes.txt', $tempDir);
        $compiler->compile($sourceDir.'/misc/metaZones.txt', $tempDir);
        $compiler->compile($sourceDir.'/misc/windowsZones.txt', $tempDir);
    }

    /**
     * {@inheritdoc}
     */
    protected function preGenerate()
    {
        $this->zoneIds = [];
        $this->zoneToCountryMapping = [];
    }

    /**
     * {@inheritdoc}
     */
    protected function generateDataForLocale(BundleEntryReaderInterface $reader, string $tempDir, string $displayLocale): ?array
    {
        if (!$this->zoneToCountryMapping) {
            $this->zoneToCountryMapping = self::generateZoneToCountryMapping($reader->read($tempDir, 'windowsZones'));
        }

        // Don't generate aliases, as they are resolved during runtime
        // Unless an alias is needed as fallback for de-duplication purposes
        if (isset($this->localeAliases[$displayLocale]) && !$this->generatingFallback) {
            return null;
        }

        $localeBundle = $reader->read($tempDir, $displayLocale);

        if (!isset($localeBundle['zoneStrings']) || null === $localeBundle['zoneStrings']) {
            return null;
        }

        $data = [
            'Version' => $localeBundle['Version'],
            'Names' => $this->generateZones($reader, $tempDir, $displayLocale),
            'Meta' => self::generateZoneMetadata($localeBundle),
        ];

        // Don't de-duplicate a fallback locale
        // Ensures the display locale can be de-duplicated on itself
        if ($this->generatingFallback) {
            return $data;
        }

        // Process again to de-duplicate locales and their fallback locales
        // Only keep the differences
        $fallback = $this->generateFallbackData($reader, $tempDir, $displayLocale);
        if (isset($fallback['Names'])) {
            $data['Names'] = array_diff($data['Names'], $fallback['Names']);
        }
        if (isset($fallback['Meta'])) {
            $data['Meta'] = array_diff($data['Meta'], $fallback['Meta']);
        }
        if (!$data['Names'] && !$data['Meta']) {
            return null;
        }

        $this->zoneIds = array_merge($this->zoneIds, array_keys($data['Names']));

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    protected function generateDataForRoot(BundleEntryReaderInterface $reader, string $tempDir): ?array
    {
        $rootBundle = $reader->read($tempDir, 'root');

        return [
            'Version' => $rootBundle['Version'],
            'Meta' => self::generateZoneMetadata($rootBundle),
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function generateDataForMeta(BundleEntryReaderInterface $reader, string $tempDir): ?array
    {
        $rootBundle = $reader->read($tempDir, 'root');

        $this->zoneIds = array_unique($this->zoneIds);

        sort($this->zoneIds);
        ksort($this->zoneToCountryMapping);

        $data = [
            'Version' => $rootBundle['Version'],
            'Zones' => $this->zoneIds,
            'ZoneToCountry' => $this->zoneToCountryMapping,
            'CountryToZone' => self::generateCountryToZoneMapping($this->zoneToCountryMapping),
        ];

        return $data;
    }

    private function generateZones(BundleEntryReaderInterface $reader, string $tempDir, string $locale): array
    {
        $typeBundle = $reader->read($tempDir, 'timezoneTypes');
        $available = [];
        foreach ($typeBundle['typeMap']['timezone'] as $zone => $_) {
            if ('Etc:Unknown' === $zone || preg_match('~^Etc:GMT[-+]\d+$~', $zone)) {
                continue;
            }

            $available[$zone] = true;
        }

        $metaBundle = $reader->read($tempDir, 'metaZones');
        $metazones = [];
        foreach ($metaBundle['metazoneInfo'] as $zone => $info) {
            foreach ($info as $metazone) {
                $metazones[$zone] = $metazone->get(0);
            }
        }

        $regionFormat = $reader->readEntry($tempDir, $locale, ['zoneStrings', 'regionFormat']);
        $fallbackFormat = $reader->readEntry($tempDir, $locale, ['zoneStrings', 'fallbackFormat']);
        $resolveName = function (string $id, string $city = null) use ($reader, $tempDir, $locale, $regionFormat, $fallbackFormat): ?string {
            // Resolve default name as described per http://cldr.unicode.org/translation/timezones
            if (isset($this->zoneToCountryMapping[$id])) {
                try {
                    $country = $reader->readEntry($tempDir.'/region', $locale, ['Countries', $this->zoneToCountryMapping[$id]]);
                } catch (MissingResourceException $e) {
                    return null;
                }

                $name = str_replace('{0}', $country, $regionFormat);

                return null === $city ? $name : str_replace(['{0}', '{1}'], [$city, $name], $fallbackFormat);
            }
            if (null !== $city) {
                return str_replace('{0}', $city, $regionFormat);
            }

            return null;
        };
        $accessor = static function (array $indices, array ...$fallbackIndices) use ($locale, $reader, $tempDir) {
            foreach (\func_get_args() as $indices) {
                try {
                    return $reader->readEntry($tempDir, $locale, $indices);
                } catch (MissingResourceException $e) {
                }
            }

            return null;
        };
        $zones = [];
        foreach (array_keys($available) as $zone) {
            // lg: long generic, e.g. "Central European Time"
            // ls: long specific (not DST), e.g. "Central European Standard Time"
            // ld: long DST, e.g. "Central European Summer Time"
            // ec: example city, e.g. "Amsterdam"
            $name = $accessor(['zoneStrings', $zone, 'lg'], ['zoneStrings', $zone, 'ls']);
            $city = $accessor(['zoneStrings', $zone, 'ec']);
            $id = str_replace(':', '/', $zone);

            if (null === $name && isset($metazones[$zone])) {
                $meta = 'meta:'.$metazones[$zone];
                $name = $accessor(['zoneStrings', $meta, 'lg'], ['zoneStrings', $meta, 'ls']);
            }

            // Infer a default English named city for all locales
            // Ensures each timezone ID has a distinctive name
            if (null === $city && 0 !== strrpos($zone, 'Etc:') && false !== $i = strrpos($zone, ':')) {
                $city = str_replace('_', ' ', substr($zone, $i + 1));
            }
            if (null === $name) {
                $name = $resolveName($id, $city);
                $city = null;
            }
            if (null === $name) {
                continue;
            }

            // Ensure no duplicated content is generated
            if (null !== $city && false === mb_stripos(str_replace('-', ' ', $name), str_replace('-', ' ', $city))) {
                $name = str_replace(['{0}', '{1}'], [$city, $name], $fallbackFormat);
            }

            $zones[$id] = $name;
        }

        return $zones;
    }

    private static function generateZoneMetadata(ArrayAccessibleResourceBundle $localeBundle): array
    {
        $metadata = [];
        if (isset($localeBundle['zoneStrings']['gmtFormat'])) {
            $metadata['GmtFormat'] = str_replace('{0}', '%s', $localeBundle['zoneStrings']['gmtFormat']);
        }
        if (isset($localeBundle['zoneStrings']['hourFormat'])) {
            $hourFormat = explode(';', str_replace(['HH', 'mm', 'H', 'm'], ['%02d', '%02d', '%d', '%d'], $localeBundle['zoneStrings']['hourFormat']), 2);
            $metadata['HourFormatPos'] = $hourFormat[0];
            $metadata['HourFormatNeg'] = $hourFormat[1];
        }

        return $metadata;
    }

    private static function generateZoneToCountryMapping(ArrayAccessibleResourceBundle $windowsZoneBundle): array
    {
        $mapping = [];

        foreach ($windowsZoneBundle['mapTimezones'] as $zoneInfo) {
            foreach ($zoneInfo as $region => $zones) {
                if (RegionDataGenerator::isValidCountryCode($region)) {
                    $mapping += array_fill_keys(explode(' ', $zones), $region);
                }
            }
        }

        ksort($mapping);

        return $mapping;
    }

    private static function generateCountryToZoneMapping(array $zoneToCountryMapping): array
    {
        $mapping = [];

        foreach ($zoneToCountryMapping as $zone => $country) {
            $mapping[$country][] = $zone;
        }

        ksort($mapping);

        return $mapping;
    }
}
