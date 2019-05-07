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

use Symfony\Component\Intl\Data\Bundle\Compiler\BundleCompilerInterface;
use Symfony\Component\Intl\Data\Bundle\Reader\BundleEntryReaderInterface;
use Symfony\Component\Intl\Data\Provider\RegionDataProvider;
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
    /**
     * Collects all available zone IDs.
     *
     * @var string[]
     */
    private $zoneIds = [];
    private $regionDataProvider;

    public function __construct(BundleCompilerInterface $compiler, string $dirName, RegionDataProvider $regionDataProvider)
    {
        parent::__construct($compiler, $dirName);

        $this->regionDataProvider = $regionDataProvider;
    }

    /**
     * {@inheritdoc}
     */
    protected function scanLocales(LocaleScanner $scanner, $sourceDir)
    {
        return $scanner->scanLocales($sourceDir.'/zone');
    }

    /**
     * {@inheritdoc}
     */
    protected function compileTemporaryBundles(BundleCompilerInterface $compiler, $sourceDir, $tempDir)
    {
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
    }

    /**
     * {@inheritdoc}
     */
    protected function generateDataForLocale(BundleEntryReaderInterface $reader, $tempDir, $displayLocale)
    {
        $localeBundle = $reader->read($tempDir, $displayLocale);

        if (isset($localeBundle['zoneStrings']) && null !== $localeBundle['zoneStrings']) {
            $localeBundles = [$localeBundle];
            $fallback = $displayLocale;
            while (null !== ($fallback = Locale::getFallback($fallback))) {
                $localeBundles[] = $reader->read($tempDir, $fallback);
            }
            if ('root' !== $displayLocale) {
                $localeBundles[] = $reader->read($tempDir, 'root');
            }
            $metadata = [];
            $data = [
                'Version' => $localeBundle['Version'],
                'Names' => $this->generateZones(
                    $displayLocale,
                    $reader->read($tempDir, 'timezoneTypes'),
                    $reader->read($tempDir, 'metaZones'),
                    $reader->read($tempDir, 'windowsZones'),
                    $localeBundles,
                    $metadata
                ),
            ];

            if (!$data['Names'] && !$metadata) {
                return;
            }

            $data['Meta'] = $metadata;

            $this->zoneIds = array_merge($this->zoneIds, array_keys($data['Names']));

            return $data;
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function generateDataForRoot(BundleEntryReaderInterface $reader, $tempDir)
    {
    }

    /**
     * {@inheritdoc}
     */
    protected function generateDataForMeta(BundleEntryReaderInterface $reader, $tempDir)
    {
        $rootBundle = $reader->read($tempDir, 'root');

        $this->zoneIds = array_unique($this->zoneIds);

        sort($this->zoneIds);

        $data = [
            'Version' => $rootBundle['Version'],
            'Zones' => $this->zoneIds,
            'ZoneToCountry' => self::generateZoneToCountryMapping($reader->read($tempDir, 'windowsZones')),
        ];

        $data['CountryToZone'] = self::generateCountryToZoneMapping($data['ZoneToCountry']);

        return $data;
    }

    /**
     * @param ArrayAccessibleResourceBundle[] $localeBundles
     */
    private function generateZones(string $locale, ArrayAccessibleResourceBundle $typeBundle, ArrayAccessibleResourceBundle $metaBundle, ArrayAccessibleResourceBundle $windowsZonesBundle, array $localeBundles, array &$metadata = []): array
    {
        $accessor = static function (ArrayAccessibleResourceBundle $resourceBundle, array $indices) {
            $result = $resourceBundle;
            foreach ($indices as $indice) {
                $result = $result[$indice] ?? null;
            }

            return $result;
        };
        $accessor = static function (array $indices, &$inherited = false) use ($localeBundles, $accessor) {
            $inherited = false;
            foreach ($localeBundles as $i => $localeBundle) {
                $nextLocaleBundle = $localeBundles[$i + 1] ?? null;
                $result = $accessor($localeBundle, $indices);
                if (null !== $result && (null === $nextLocaleBundle || $result !== $accessor($nextLocaleBundle, $indices))) {
                    $inherited = 0 !== $i;

                    return $result;
                }
            }

            return null;
        };
        $regionFormat = $accessor(['zoneStrings', 'regionFormat']) ?? '{0}';
        $fallbackFormat = $accessor(['zoneStrings', 'fallbackFormat']) ?? '{1} ({0})';
        $zoneToCountry = self::generateZoneToCountryMapping($windowsZonesBundle);
        $resolveName = function (string $id, string $city = null) use ($locale, $regionFormat, $fallbackFormat, $zoneToCountry): string {
            if (isset($zoneToCountry[$id])) {
                try {
                    $country = $this->regionDataProvider->getName($zoneToCountry[$id], $locale);
                } catch (MissingResourceException $e) {
                    $country = $this->regionDataProvider->getName($zoneToCountry[$id], 'en');
                }

                return null === $city ? str_replace('{0}', $country, $regionFormat) : str_replace(['{0}', '{1}'], [$city, $country], $fallbackFormat);
            } elseif (null !== $city) {
                return str_replace('{0}', $city, $regionFormat);
            } else {
                return str_replace(['/', '_'], ' ', 0 === strrpos($id, 'Etc/') ? substr($id, 4) : $id);
            }
        };
        $available = [];
        foreach ($typeBundle['typeMap']['timezone'] as $zone => $_) {
            if ('Etc:Unknown' === $zone || preg_match('~^Etc:GMT[-+]\d+$~', $zone)) {
                continue;
            }

            $available[$zone] = true;
        }

        $metazones = [];
        foreach ($metaBundle['metazoneInfo'] as $zone => $info) {
            foreach ($info as $metazone) {
                $metazones[$zone] = $metazone->get(0);
            }
        }

        $isBase = false === strpos($locale, '_');
        $zones = [];
        foreach (array_keys($available) as $zone) {
            // lg: long generic, e.g. "Central European Time"
            // ls: long specific (not DST), e.g. "Central European Standard Time"
            // ld: long DST, e.g. "Central European Summer Time"
            // ec: example city, e.g. "Amsterdam"
            $name = $accessor(['zoneStrings', $zone, 'lg'], $nameInherited) ?? $accessor(['zoneStrings', $zone, 'ls'], $nameInherited);
            $city = $accessor(['zoneStrings', $zone, 'ec'], $cityInherited);
            $id = str_replace(':', '/', $zone);

            if (null === $name && isset($metazones[$zone])) {
                $meta = 'meta:'.$metazones[$zone];
                $name = $accessor(['zoneStrings', $meta, 'lg'], $nameInherited) ?? $accessor(['zoneStrings', $meta, 'ls'], $nameInherited);
            }
            if (null === $city && 0 !== strrpos($zone, 'Etc:') && false !== $i = strrpos($zone, ':')) {
                $city = str_replace('_', ' ', substr($zone, $i + 1));
                $cityInherited = !$isBase;
            }
            if ($isBase && null === $name) {
                $name = $resolveName($id, $city);
                $city = null;
            }
            if (
                ($nameInherited && $cityInherited)
                || (null === $name && null === $city)
                || ($nameInherited && null === $city)
                || ($cityInherited && null === $name)
            ) {
                continue;
            }
            if (null === $name) {
                $name = $resolveName($id, $city);
            } elseif (null !== $city && false === mb_stripos(str_replace('-', ' ', $name), str_replace('-', ' ', $city))) {
                $name = str_replace(['{0}', '{1}'], [$city, $name], $fallbackFormat);
            }

            $zones[$id] = $name;
        }

        $gmtFormat = $accessor(['zoneStrings', 'gmtFormat'], $gmtFormatInherited) ?? 'GMT{0}';
        if (!$gmtFormatInherited || $isBase) {
            $metadata['GmtFormat'] = str_replace('{0}', '%s', $gmtFormat);
        }

        $hourFormat = $accessor(['zoneStrings', 'hourFormat'], $hourFormatInherited) ?? '+HH:mm;-HH:mm';
        if (!$hourFormatInherited || $isBase) {
            $metadata['HourFormat'] = explode(';', str_replace(['HH', 'mm', 'H', 'm'], ['%02d', '%02d', '%d', '%d'], $hourFormat), 2);
        }

        return $zones;
    }

    private static function generateZoneToCountryMapping(ArrayAccessibleResourceBundle $windowsZoneBundle): array
    {
        $mapping = [];

        foreach ($windowsZoneBundle['mapTimezones'] as $zoneInfo) {
            foreach ($zoneInfo as $region => $zones) {
                if (\in_array($region, ['001', 'ZZ'], true)) {
                    continue;
                }
                $mapping += array_fill_keys(explode(' ', $zones), $region);
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
