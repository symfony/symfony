<?php

namespace Symfony\Component\PropertyInfo;

/**
 * Nullable Extractor Interface.
 *
 */
interface PropertyNullableExtractorInterface
{
    /**
     * Gets nullable info of a property.
     *
     * @return int
     */
    public function getNullableInfo(string $class, string $property): int;
}
