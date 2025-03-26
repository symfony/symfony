<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Metadata;

/**
 * Factory to create Mapper metadata.
 *
 * @experimental
 *
 * @author Antoine Bluchet <soyuka@gmail.com>
 */
interface ObjectMapperMetadataFactoryInterface
{
    /**
     * @param array<string, mixed> $context
     *
     * @return list<Mapping>
     */
    public function create(object $object, ?string $property = null, array $context = []): array;
}
