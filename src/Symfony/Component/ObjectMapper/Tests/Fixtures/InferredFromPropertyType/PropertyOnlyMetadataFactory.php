<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\InferredFromPropertyType;

use Symfony\Component\ObjectMapper\Metadata\ObjectMapperMetadataFactoryInterface;
use Symfony\Component\ObjectMapper\Metadata\ReflectionObjectMapperMetadataFactory;

/**
 * Reports the mappings declared on properties, and states that no class maps to another one.
 */
final class PropertyOnlyMetadataFactory implements ObjectMapperMetadataFactoryInterface
{
    private ObjectMapperMetadataFactoryInterface $inner;

    public function __construct()
    {
        $this->inner = new ReflectionObjectMapperMetadataFactory();
    }

    public function create(object $object, ?string $property = null, array $context = []): array
    {
        return $property ? $this->inner->create($object, $property, $context) : [];
    }
}
