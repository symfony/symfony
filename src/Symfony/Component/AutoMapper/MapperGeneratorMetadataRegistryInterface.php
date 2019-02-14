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

/**
 * Registry of metadata.
 *
 * @internal
 *
 * @author Joel Wurtz <jwurtz@jolicode.com>
 */
interface MapperGeneratorMetadataRegistryInterface
{
    /**
     * Register metadata.
     */
    public function register(MapperGeneratorMetadataInterface $configuration): void;

    /**
     * Get metadata for a source and a target.
     */
    public function getMetadata(string $source, string $target): ?MapperGeneratorMetadataInterface;
}
