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
    private array $fallbackCache = [];
    private bool $generatingFallback = false;

    /**
     * @see AbstractDataGenerator::generateDataForLocale()
     */
    abstract protected function generateDataForLocale(BundleEntryReaderInterface $reader, string $tempDir, string $displayLocale): ?array;

    /**
     * @see AbstractDataGenerator::generateDataForRoot()
     */
    abstract protected function generateDataForRoot(BundleEntryReaderInterface $reader, string $tempDir): ?array;

    private function generateFallbackData(BundleEntryReaderInterface $reader, string $tempDir, string $displayLocale): array
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
