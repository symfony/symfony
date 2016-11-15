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
    const UNKNOWN_REGION_ID = 'ZZ';

    const OUTLYING_OCEANIA_REGION_ID = 'QO';

    const EUROPEAN_UNION_ID = 'EU';

    const NETHERLANDS_ANTILLES_ID = 'AN';

    const BOUVET_ISLAND_ID = 'BV';

    const HEARD_MCDONALD_ISLANDS_ID = 'HM';

    const CLIPPERTON_ISLAND_ID = 'CP';

    /**
     * Regions excluded from generation.
     *
     * @var array
     */
    private static $blacklist = array(
        self::UNKNOWN_REGION_ID => true,
        // Look like countries, but are sub-continents
        self::OUTLYING_OCEANIA_REGION_ID => true,
        self::EUROPEAN_UNION_ID => true,
        // No longer exists
        self::NETHERLANDS_ANTILLES_ID => true,
        // Uninhabited islands
        self::BOUVET_ISLAND_ID => true,
        self::HEARD_MCDONALD_ISLANDS_ID => true,
        self::CLIPPERTON_ISLAND_ID => true,
    );

    /**
     * Collects all available language codes.
     *
     * @var string[]
     */
    private $regionCodes = array();

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
        $this->regionCodes = array();
    }

    /**
     * {@inheritdoc}
     */
    protected function generateDataForLocale(BundleReaderInterface $reader, $tempDir, $displayLocale)
    {
        $localeBundle = $reader->read($tempDir, $displayLocale);

        // isset() on \ResourceBundle returns true even if the value is null
        if (isset($localeBundle['Countries']) && null !== $localeBundle['Countries']) {
            $data = array(
                'Version' => $localeBundle['Version'],
                'Names' => $this->generateRegionNames($localeBundle),
            );

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

        return array(
            'Version' => $rootBundle['Version'],
            'Regions' => $this->regionCodes,
        );
    }

    /**
     * @param ArrayAccessibleResourceBundle $localeBundle
     *
     * @return array
     */
    protected function generateRegionNames(ArrayAccessibleResourceBundle $localeBundle)
    {
        $unfilteredRegionNames = iterator_to_array($localeBundle['Countries']);
        $regionNames = array();

        foreach ($unfilteredRegionNames as $region => $regionName) {
            if (isset(self::$blacklist[$region])) {
                continue;
            }

            // WORLD/CONTINENT/SUBCONTINENT/GROUPING
            if (ctype_digit($region) || is_int($region)) {
                continue;
            }

            $regionNames[$region] = $regionName;
        }

        return $regionNames;
    }
}
