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

use Symfony\Component\Intl\Data\Bundle\Compiler\GenrbCompiler;
use Symfony\Component\Intl\Data\Bundle\Reader\BundleReaderInterface;
use Symfony\Component\Intl\Data\Util\ArrayAccessibleResourceBundle;
use Symfony\Component\Intl\Data\Util\LocaleScanner;

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
     * Collects all available zone codes.
     *
     * @var string[]
     */
    private $zoneCodes = [];

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
    protected function compileTemporaryBundles(GenrbCompiler $compiler, $sourceDir, $tempDir)
    {
        $compiler->compile($sourceDir.'/zone', $tempDir);
        $compiler->compile($sourceDir.'/misc/timezoneTypes.txt', $tempDir);
        $compiler->compile($sourceDir.'/misc/metaZones.txt', $tempDir);
    }

    /**
     * {@inheritdoc}
     */
    protected function preGenerate()
    {
        $this->zoneCodes = [];
    }

    /**
     * {@inheritdoc}
     */
    protected function generateDataForLocale(BundleReaderInterface $reader, $tempDir, $displayLocale)
    {
        $localeBundle = $reader->read($tempDir, $displayLocale);

        if (isset($localeBundle['zoneStrings']) && null !== $localeBundle['zoneStrings']) {
            $data = [
                'Version' => $localeBundle['Version'],
                'Names' => self::generateZones(
                    $reader->read($tempDir, 'timezoneTypes'),
                    $reader->read($tempDir, 'metaZones'),
                    $reader->read($tempDir, 'root'),
                    $localeBundle
                ),
            ];

            $this->zoneCodes = array_merge($this->zoneCodes, array_keys($data['Names']));

            return $data;
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function generateDataForRoot(BundleReaderInterface $reader, $tempDir)
    {
    }

    /**
     * {@inheritdoc}
     */
    protected function generateDataForMeta(BundleReaderInterface $reader, $tempDir)
    {
        $rootBundle = $reader->read($tempDir, 'root');

        $this->zoneCodes = array_unique($this->zoneCodes);

        sort($this->zoneCodes);

        $data = [
            'Version' => $rootBundle['Version'],
            'Zones' => $this->zoneCodes,
        ];

        return $data;
    }

    private static function generateZones(ArrayAccessibleResourceBundle $typeBundle, ArrayAccessibleResourceBundle $metaBundle, ArrayAccessibleResourceBundle $rootBundle, ArrayAccessibleResourceBundle $localeBundle): array
    {
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

        $zones = [];
        foreach (array_keys($available) as $zone) {
            // lg: long generic, e.g. "Central European Time"
            // ls: long specific (not DST), e.g. "Central European Standard Time"
            // ld: long DST, e.g. "Central European Summer Time"
            // ec: example city, e.g. "Amsterdam"
            $name = $localeBundle['zoneStrings'][$zone]['lg'] ?? $rootBundle['zoneStrings'][$zone]['lg'] ?? $localeBundle['zoneStrings'][$zone]['ls'] ?? $rootBundle['zoneStrings'][$zone]['ls'] ?? null;
            $city = $localeBundle['zoneStrings'][$zone]['ec'] ?? $rootBundle['zoneStrings'][$zone]['ec'] ?? null;

            if (null === $name && isset($metazones[$zone])) {
                $meta = 'meta:'.$metazones[$zone];
                $name = $localeBundle['zoneStrings'][$meta]['lg'] ?? $rootBundle['zoneStrings'][$meta]['lg'] ?? $localeBundle['zoneStrings'][$meta]['ls'] ?? $rootBundle['zoneStrings'][$meta]['ls'] ?? null;
            }
            if (null === $city && 0 !== strrpos($zone, 'Etc:') && false !== $i = strrpos($zone, ':')) {
                $city = str_replace('_', ' ', substr($zone, $i + 1));
            }
            if (null === $name) {
                continue;
            }
            if (null !== $city) {
                $name .= ' ('.$city.')';
            }

            $id = str_replace(':', '/', $zone);
            $zones[$id] = $name;
        }

        return $zones;
    }
}
