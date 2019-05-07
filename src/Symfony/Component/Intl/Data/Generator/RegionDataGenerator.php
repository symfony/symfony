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
 * The rule for compiling the region bundle.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @see http://source.icu-project.org/repos/icu/icu4j/trunk/main/classes/core/src/com/ibm/icu/util/Region.java
 *
 * @internal
 */
class RegionDataGenerator extends AbstractDataGenerator
{
    private static $blacklist = [
        // Look like countries, but are sub-continents
        'QO' => true, // Outlying Oceania
        'EU' => true, // European Union
        'EZ' => true, // Eurozone
        'UN' => true, // United Nations
        // Uninhabited islands
        'BV' => true, // Bouvet Island
        'HM' => true, // Heard & McDonald Islands
        'CP' => true, // Clipperton Island
        // Misc
        'ZZ' => true, // Unknown Region
    ];

    /**
     * Collects all available language codes.
     *
     * @var string[]
     */
    private $regionCodes = [];

    /**
     * {@inheritdoc}
     */
    protected function scanLocales(LocaleScanner $scanner, $sourceDir)
    {
        return $scanner->scanLocales($sourceDir.'/region');
    }

    /**
     * {@inheritdoc}
     */
    protected function compileTemporaryBundles(GenrbCompiler $compiler, $sourceDir, $tempDir)
    {
        $compiler->compile($sourceDir.'/region', $tempDir);
    }

    /**
     * {@inheritdoc}
     */
    protected function preGenerate()
    {
        $this->regionCodes = [];
    }

    /**
     * {@inheritdoc}
     */
    protected function generateDataForLocale(BundleReaderInterface $reader, $tempDir, $displayLocale)
    {
        $localeBundle = $reader->read($tempDir, $displayLocale);

        // isset() on \ResourceBundle returns true even if the value is null
        if (isset($localeBundle['Countries']) && null !== $localeBundle['Countries']) {
            $data = [
                'Version' => $localeBundle['Version'],
                'Names' => $this->generateRegionNames($localeBundle),
            ];

            $this->regionCodes = array_merge($this->regionCodes, array_keys($data['Names']));

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

        $this->regionCodes = array_unique($this->regionCodes);

        sort($this->regionCodes);

        return [
            'Version' => $rootBundle['Version'],
            'Regions' => $this->regionCodes,
        ];
    }

    /**
     * @return array
     */
    protected function generateRegionNames(ArrayAccessibleResourceBundle $localeBundle)
    {
        $unfilteredRegionNames = iterator_to_array($localeBundle['Countries']);
        $regionNames = [];

        foreach ($unfilteredRegionNames as $region => $regionName) {
            if (isset(self::$blacklist[$region])) {
                continue;
            }

            // WORLD/CONTINENT/SUBCONTINENT/GROUPING
            if (ctype_digit($region) || \is_int($region)) {
                continue;
            }

            $regionNames[$region] = $regionName;
        }

        return $regionNames;
    }
}
