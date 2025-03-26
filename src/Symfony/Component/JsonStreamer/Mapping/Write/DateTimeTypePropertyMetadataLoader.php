<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonStreamer\Mapping\Write;

use Symfony\Component\JsonStreamer\Mapping\PropertyMetadataLoaderInterface;
use Symfony\Component\JsonStreamer\ValueTransformer\DateTimeToStringValueTransformer;
use Symfony\Component\TypeInfo\Type\ObjectType;

/**
 * Transforms DateTimeInterface to string for properties with DateTimeInterface type.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @internal
 */
final class DateTimeTypePropertyMetadataLoader implements PropertyMetadataLoaderInterface
{
    public function __construct(
        private PropertyMetadataLoaderInterface $decorated,
    ) {
    }

    public function load(string $className, array $options = [], array $context = []): array
    {
        $result = $this->decorated->load($className, $options, $context);

        foreach ($result as &$metadata) {
            $type = $metadata->getType();

            if ($type instanceof ObjectType && is_a($type->getClassName(), \DateTimeInterface::class, true)) {
                $metadata = $metadata
                    ->withType(DateTimeToStringValueTransformer::getStreamValueType())
                    ->withAdditionalNativeToStreamValueTransformer('json_streamer.value_transformer.date_time_to_string');
            }
        }

        return $result;
    }
}
