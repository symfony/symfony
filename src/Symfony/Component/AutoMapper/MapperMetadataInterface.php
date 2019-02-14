<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AutoMapper;

use Symfony\Component\AutoMapper\Extractor\PropertyMapping;

/**
 * Stores metadata needed for mapping data.
 *
 * @internal
 *
 * @author Joel Wurtz <jwurtz@jolicode.com>
 */
interface MapperMetadataInterface
{
    /**
     * Get the source type mapped.
     */
    public function getSource(): string;

    /**
     * Get the target type mapped.
     */
    public function getTarget(): string;

    /**
     * Get properties to map between source and target.
     *
     * @return PropertyMapping[]
     */
    public function getPropertiesMapping(): array;

    /**
     * Get property to map by name, or null if not mapped.
     */
    public function getPropertyMapping(string $property): ?PropertyMapping;

    /**
     * Get date time format to use when mapping date time to string.
     */
    public function getDateTimeFormat(): string;
}
