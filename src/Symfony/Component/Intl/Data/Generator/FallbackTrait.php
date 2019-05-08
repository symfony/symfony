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

use Symfony\Component\Intl\Data\Bundle\Reader\BundleEntryReaderInterface;
use Symfony\Component\Intl\Locale;

/**
 * @author Roland Franssen <franssen.roland@gmail.com>
 *
 * @internal
 */
trait FallbackTrait
{
    private $fallbackCache = [];
    private $generatingFallback = false;

    /**
     * @param string $tempDir
     * @param string $displayLocale
     *
     * @return array|null
     *
     * @see AbstractDataGenerator::generateDataForLocale()
     */
    abstract protected function generateDataForLocale(BundleEntryReaderInterface $reader, $tempDir, $displayLocale);

    /**
     * @param string $tempDir
     *
     * @return array|null
     *
     * @see AbstractDataGenerator::generateDataForRoot()
     */
    abstract protected function generateDataForRoot(BundleEntryReaderInterface $reader, $tempDir);

    /**
     * @param string $tempDir
     * @param string $displayLocale
     *
     * @return array
     */
    private function generateFallbackData(BundleEntryReaderInterface $reader, $tempDir, $displayLocale)
    {
        if (null === $fallback = Locale::getFallback($displayLocale)) {
            return [];
        }

        if (isset($this->fallbackCache[$fallback])) {
            return $this->fallbackCache[$fallback];
        }

        $prevGeneratingFallback = $this->generatingFallback;
        $this->generatingFallback = true;

        try {
            $data = 'root' === $fallback ? $this->generateDataForRoot($reader, $tempDir) : $this->generateDataForLocale($reader, $tempDir, $fallback);
        } finally {
            $this->generatingFallback = $prevGeneratingFallback;
        }

        return $this->fallbackCache[$fallback] = $data ?: [];
    }
}
