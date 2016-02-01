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

use Symfony\Component\Intl\Data\Bundle\Reader\BundleReaderInterface;
use Symfony\Component\Intl\Data\Util\ArrayAccessibleResourceBundle;
use Symfony\Component\Intl\Data\Bundle\Compiler\GenrbCompiler;
use Symfony\Component\Intl\Data\Util\LocaleScanner;

/**
 * The rule for compiling the currency bundle.
 *
 * @internal
 */
class TimezoneDataGenerator extends AbstractDataGenerator
{
    /**
     * Collects all available timezones.
     *
     * @var string[]
     */
    private $timezones = array();

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
    }

    /**
     * {@inheritdoc}
     */
    protected function preGenerate()
    {
        $this->timezones = array();
    }

    /**
     * {@inheritdoc}
     */
    protected function generateDataForLocale(BundleReaderInterface $reader, $tempDir, $displayLocale)
    {
        $localeBundle = $reader->read($tempDir, $displayLocale);

        if (isset($localeBundle['zoneStrings']) && null !== $localeBundle['zoneStrings']) {
            $data = array(
                'Version' => $localeBundle['Version'],
                'Names' => $this->generateTimezoneNames($localeBundle),
            );

            $this->timezones = array_merge($this->timezones, array_keys($data['Names']));

            return $data;
        }

        return;
    }

    /**
     * {@inheritdoc}
     */
    protected function generateDataForRoot(BundleReaderInterface $reader, $tempDir)
    {
        $rootBundle = $reader->read($tempDir, 'root');

        $names = $this->generateTimezoneNames($rootBundle);

        foreach ($this->timezones as $timezone) {
            if (!isset($names[$timezone])) {
                $names[$timezone] = $this->getFallbackName($timezone);
            }
        }

        ksort($names);

        return array(
            'Version' => $rootBundle['Version'],
            'Names' => $names,
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function generateDataForMeta(BundleReaderInterface $reader, $tempDir)
    {
        $rootBundle = $reader->read($tempDir, 'root');

        $this->timezones = array_unique($this->timezones);

        sort($this->timezones);

        $data = array(
            'Version' => $rootBundle['Version'],
            'Timezones' => $this->timezones,
        );

        return $data;
    }

    /**
     * @param ArrayAccessibleResourceBundle $rootBundle
     *
     * @return array
     */
    private function generateTimezoneNames(ArrayAccessibleResourceBundle $rootBundle)
    {
        $timezoneNames = array();
        foreach ($rootBundle['zoneStrings'] as $key => $zone) {
            if (strpos($key, ':') !== false && substr($key, 5) != 'meta:') {
                $identifier = str_replace(':', '/', $key);

                $zone = iterator_to_array($zone);

                if (isset($zone['ec'])) {
                    $timezoneNames[$identifier] = $zone['ec'];
                }
            }
        }

        return $timezoneNames;
    }

    /**
     * Converts a timezone identifier to an English string.
     *
     * @param string $timezone
     *
     * @return string
     */
    private function getFallbackName($timezone)
    {
        $parts = explode('/', $timezone);
        if (count($parts) > 2) {
            $name = $parts[2].', '.$parts[1];
        } elseif (count($parts) > 1) {
            $name = $parts[1];
        } else {
            $name = $parts[0];
        }

        return str_replace('_', ' ', $name);
    }
}
