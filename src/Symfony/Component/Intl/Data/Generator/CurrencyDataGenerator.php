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
use Symfony\Component\Intl\Data\Util\ArrayAccessibleResourceBundle;
use Symfony\Component\Intl\Data\Util\LocaleScanner;

/**
 * The rule for compiling the currency bundle.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @internal
 */
class CurrencyDataGenerator extends AbstractDataGenerator
{
    private const DENYLIST = [
        'XBA' => true, // European Composite Unit
        'XBB' => true, // European Monetary Unit
        'XBC' => true, // European Unit of Account (XBC)
        'XBD' => true, // European Unit of Account (XBD)
        'XUA' => true, // ADB Unit of Account
        'XAU' => true, // Gold
        'XAG' => true, // Silver
        'XPT' => true, // Platinum
        'XPD' => true, // Palladium
        'XSU' => true, // Sucre
        'XDR' => true, // Special Drawing Rights
        'XTS' => true, // Testing Currency Code
        'XXX' => true, // Unknown Currency
    ];

    /**
     * Collects all available currency codes.
     *
     * @var string[]
     */
    private array $currencyCodes = [];

    protected function scanLocales(LocaleScanner $scanner, string $sourceDir): array
    {
        return $scanner->scanLocales($sourceDir.'/curr');
    }

    protected function compileTemporaryBundles(BundleCompilerInterface $compiler, string $sourceDir, string $tempDir): void
    {
        $compiler->compile($sourceDir.'/curr', $tempDir);
        $compiler->compile($sourceDir.'/misc/currencyNumericCodes.txt', $tempDir);
    }

    protected function preGenerate(): void
    {
        $this->currencyCodes = [];
    }

    protected function generateDataForLocale(BundleEntryReaderInterface $reader, string $tempDir, string $displayLocale): ?array
    {
        $localeBundle = $reader->read($tempDir, $displayLocale);

        if (isset($localeBundle['Currencies']) && null !== $localeBundle['Currencies']) {
            $data = [
                'Names' => $this->generateSymbolNamePairs($localeBundle),
            ];

            $this->currencyCodes = array_merge($this->currencyCodes, array_keys($data['Names']));

            return $data;
        }

        return null;
    }

    protected function generateDataForRoot(BundleEntryReaderInterface $reader, string $tempDir): ?array
    {
        $rootBundle = $reader->read($tempDir, 'root');

        return [
            'Names' => $this->generateSymbolNamePairs($rootBundle),
        ];
    }

    protected function generateDataForMeta(BundleEntryReaderInterface $reader, string $tempDir): ?array
    {
        $supplementalDataBundle = $reader->read($tempDir, 'supplementalData');
        $numericCodesBundle = $reader->read($tempDir, 'currencyNumericCodes');

        $this->currencyCodes = array_unique($this->currencyCodes);

        sort($this->currencyCodes);

        $data = [
            'Currencies' => $this->currencyCodes,
            'Meta' => $this->generateCurrencyMeta($supplementalDataBundle),
            'Map' => $this->generateCurrencyMap($supplementalDataBundle),
            'Alpha3ToNumeric' => $this->generateAlpha3ToNumericMapping($numericCodesBundle, $this->currencyCodes),
        ];

        $data['NumericToAlpha3'] = $this->generateNumericToAlpha3Mapping($data['Alpha3ToNumeric']);

        return $data;
    }

    private function generateSymbolNamePairs(ArrayAccessibleResourceBundle $rootBundle): array
    {
        $symbolNamePairs = array_map(fn ($pair) => \array_slice(iterator_to_array($pair), 0, 2), iterator_to_array($rootBundle['Currencies']));

        // Remove unwanted currencies
        return array_diff_key($symbolNamePairs, self::DENYLIST);
    }

    private function generateCurrencyMeta(ArrayAccessibleResourceBundle $supplementalDataBundle): array
    {
        // The metadata is already de-duplicated. It contains one key "DEFAULT"
        // which is used for currencies that don't have dedicated entries.
        return iterator_to_array($supplementalDataBundle['CurrencyMeta']);
    }

    /**
     * @return array<string, array>
     */
    private function generateCurrencyMap(mixed $supplementalDataBundle): array
    {
        /**
         * @var list<string, list<string, array{from?: string, to?: string, tender?: false}>> $regionsData
         */
        $regionsData = [];

        foreach ($supplementalDataBundle['CurrencyMap'] as $regionId => $region) {
            foreach ($region as $metadata) {
                /**
                 * Note 1: The "to" property (if present) is always greater than "from".
                 * Note 2: The "to" property may be missing if the currency is still in use.
                 * Note 3: The "tender" property indicates whether the country legally recognizes the currency within
                 *         its borders. This property is explicitly set to `false` only if that is not the case;
                 *         otherwise, it is `true` by default.
                 * Note 4: The "from" and "to" dates are not stored as strings; they are stored as a pair of integers.
                 * Note 5: The "to" property may be missing if "tender" is set to `false`.
                 *
                 * @var array{
                 *        from?: array{0: int, 1: int},
                 *        to?: array{0: int, 2: int},
                 *        tender?: bool,
                 *        id: string
                 *      } $metadata
                 */
                $metadata = iterator_to_array($metadata);

                $id = $metadata['id'];

                unset($metadata['id']);

                if (\array_key_exists($id, self::DENYLIST)) {
                    continue;
                }

                if (\array_key_exists('from', $metadata)) {
                    $metadata['from'] = self::icuPairToDatetimeString($metadata['from']);
                }

                if (\array_key_exists('to', $metadata)) {
                    $metadata['to'] = self::icuPairToDatetimeString($metadata['to']);
                }

                if (\array_key_exists('tender', $metadata)) {
                    $metadata['tender'] = filter_var($metadata['tender'], \FILTER_VALIDATE_BOOLEAN, \FILTER_NULL_ON_FAILURE);

                    if (null === $metadata['tender']) {
                        throw new \RuntimeException('Unexpected boolean value for tender attribute.');
                    }
                }

                $regionsData[$regionId][$id] = $metadata;
            }

            // Do not exclude countries with no currencies or excluded currencies (e.g. Antartica)
            $regionsData[$regionId] ??= [];
        }

        return $regionsData;
    }

    private function generateAlpha3ToNumericMapping(ArrayAccessibleResourceBundle $numericCodesBundle, array $currencyCodes): array
    {
        $alpha3ToNumericMapping = iterator_to_array($numericCodesBundle['codeMap']);

        asort($alpha3ToNumericMapping);

        // Filter unknown currencies (e.g. "AYM")
        return array_intersect_key($alpha3ToNumericMapping, array_flip($currencyCodes));
    }

    private function generateNumericToAlpha3Mapping(array $alpha3ToNumericMapping): array
    {
        $numericToAlpha3Mapping = [];

        foreach ($alpha3ToNumericMapping as $alpha3 => $numeric) {
            // Make sure that the mapping is stored as table and not as array
            $numeric = (string) $numeric;

            if (!isset($numericToAlpha3Mapping[$numeric])) {
                $numericToAlpha3Mapping[$numeric] = [];
            }

            $numericToAlpha3Mapping[$numeric][] = $alpha3;
        }

        return $numericToAlpha3Mapping;
    }

    /**
     * Decodes ICU "date pair" into a DateTimeImmutable (UTC).
     *
     * ICU stores UDate = milliseconds since 1970-01-01T00:00:00Z in a signed 64-bit.
     *
     * @param array{0: int, 1: int} $pair
     */
    private static function icuPairToDatetimeString(array $pair): string
    {
        [$highBits32, $lowBits32] = $pair;

        // Recompose a 64-bit unsigned integer from two 32-bit chunks.
        $unsigned64 = ((($highBits32 & 0xFFFFFFFF) << 32) | ($lowBits32 & 0xFFFFFFFF));

        // Split into seconds and milliseconds.
        $seconds = intdiv($unsigned64, 1000);
        $millisecondsRemainder = $unsigned64 - $seconds * 1000;

        // Normalize negative millisecond remainders (e.g., for pre-1970 values)
        if (0 > $millisecondsRemainder) {
            --$seconds;
        }

        // Note: Unlike the XML files, the date pair is already in UTC.
        $datetime = \DateTimeImmutable::createFromFormat('U', (string) $seconds, new \DateTimeZone('Etc/UTC'));

        if (false === $datetime) {
            throw new \RuntimeException('Unable to parse ICU milliseconds pair.');
        }

        return $datetime->format('Y-m-d\TH:i:s');
    }
}
