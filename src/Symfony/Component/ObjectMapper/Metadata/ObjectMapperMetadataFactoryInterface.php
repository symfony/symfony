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
 * @author Antoine Bluchet <soyuka@gmail.com>
 */
interface ObjectMapperMetadataFactoryInterface
{
    /**
     * The context describes the mapping being computed. The object mapper fills these keys:
     *
     *  * "source" and "target": the classes being mapped, when $property is given
     *  * "target" and "target_property": the class and the property a nested $object is written
     *    into, set only when the mapper can write that property
     *
     * @param array<string, mixed> $context
     *
     * @return list<Mapping>
     */
    public function create(object $object, ?string $property = null, array $context = []): array;
}
