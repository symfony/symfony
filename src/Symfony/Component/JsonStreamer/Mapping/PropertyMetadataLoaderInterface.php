<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonStreamer\Mapping;

/**
 * Loads properties stream reading/writing metadata for a given $className.
 *
 * These metadata can be used by the DataModelBuilder to create
 * an appropriate ObjectNode.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @experimental
 */
interface PropertyMetadataLoaderInterface
{
    /**
     * @param class-string         $className
     * @param array<string, mixed> $options   Implementation-specific options
     * @param array<string, mixed> $context
     *
     * @return array<string, PropertyMetadata>
     */
    public function load(string $className, array $options = [], array $context = []): array;
}
