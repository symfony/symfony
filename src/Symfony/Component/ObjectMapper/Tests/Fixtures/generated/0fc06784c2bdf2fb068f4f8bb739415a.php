<?php

use Psr\Container\ContainerInterface;
use Symfony\Component\ObjectMapper\Exception\MappingTransformException;
use Symfony\Component\ObjectMapper\Exception\MappingException;
use Symfony\Component\ObjectMapper\MappingHelper;
use Symfony\Component\ObjectMapper\ObjectMapperInterface;
use Symfony\Component\ObjectMapper\Metadata\ObjectMapperMetadataFactoryInterface;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

return function(
    Symfony\Component\ObjectMapper\Tests\Fixtures\SimpleSource $source,
    Symfony\Component\ObjectMapper\Tests\Fixtures\SimpleTarget $target,
    ObjectMapperInterface $objectMapper,
    ObjectMapperMetadataFactoryInterface $metadataFactory,
    \SplObjectStorage $objectMap,
    ?PropertyAccessorInterface $propertyAccessor = null,
    ?ContainerInterface $transformCallableLocator = null,
    ?ContainerInterface $conditionCallableLocator = null,
    bool $mappingToObject = false
): Symfony\Component\ObjectMapper\Tests\Fixtures\SimpleTarget {
    $ctorArguments = [];
    $mapToProperties = [];
    $objectMap[$source] = $target;

    if (!property_exists($source, 'foo') || (new \ReflectionProperty($source, 'foo'))->isInitialized($source)) {
        $value = MappingHelper::getValue($source, 'foo', $propertyAccessor);
        if (is_object($value) && MappingHelper::hasMappingTarget($value, $metadataFactory)) {
            if ($value === $source) {
                $value = $target;
            } elseif ($objectMap->offsetExists($value)) {
                $value = $objectMap[$value];
            } else {
                $value = $objectMapper->map($value);
            }
        }
        $mapToProperties['bar'] = $value;
    }

    if ($mappingToObject && $ctorArguments) {
    }
    foreach ($mapToProperties as $prop => $v) {
        MappingHelper::setValue($target, $prop, $v, $propertyAccessor);
    }
    return $target;
};