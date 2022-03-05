<?php

namespace Symfony\Component\Serializer\Annotation;

/**
 * Indicates that this argument should be deserialized and (optionally) validated.
 *
 * @author Gary PEGEOT <garypegeot@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
class Input
{
    public function __construct(private ?string $format = null, private array $serializationContext = [], private array $validationGroups = ['Default'])
    {
    }

    public function getFormat(): ?string
    {
        return $this->format;
    }

    public function getSerializationContext(): array
    {
        return $this->serializationContext;
    }

    public function getValidationGroups(): array
    {
        return $this->validationGroups;
    }
}
