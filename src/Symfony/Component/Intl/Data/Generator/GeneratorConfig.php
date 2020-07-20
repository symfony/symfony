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

use Symfony\Component\Intl\Data\Bundle\Writer\BundleWriterInterface;

/**
 * Stores contextual information for resource bundle generation.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @internal
 */
class GeneratorConfig
{
    private $sourceDir;
    private $icuVersion;

    /**
     * @var BundleWriterInterface[]
     */
    private $bundleWriters = [];

    public function __construct(string $sourceDir, string $icuVersion)
    {
        $this->sourceDir = $sourceDir;
        $this->icuVersion = $icuVersion;
    }

    /**
     * Adds a writer to be used during the data conversion.
     */
    public function addBundleWriter(string $targetDir, BundleWriterInterface $writer)
    {
        $this->bundleWriters[$targetDir] = $writer;
    }

    /**
     * Returns the writers indexed by their output directories.
     *
     * @return BundleWriterInterface[]
     */
    public function getBundleWriters(): array
    {
        return $this->bundleWriters;
    }

    /**
     * Returns the directory where the source versions of the resource bundles
     * are stored.
     *
     * @return string An absolute path to a directory
     */
    public function getSourceDir(): string
    {
        return $this->sourceDir;
    }

    /**
     * Returns the ICU version of the bundles being converted.
     *
     * @return string The ICU version string
     */
    public function getIcuVersion(): string
    {
        return $this->icuVersion;
    }
}
