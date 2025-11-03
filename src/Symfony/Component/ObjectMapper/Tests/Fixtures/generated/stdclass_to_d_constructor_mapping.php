<?php

use Psr\Container\ContainerInterface;
use Symfony\Component\ObjectMapper\Exception\MappingTransformException;
use Symfony\Component\ObjectMapper\Exception\MappingException;
use Symfony\Component\ObjectMapper\MappingHelper;
use Symfony\Component\ObjectMapper\ObjectMapperInterface;
use Symfony\Component\ObjectMapper\Metadata\ObjectMapperMetadataFactoryInterface;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

return function(
    stdClass $source,
    Symfony\Component\ObjectMapper\Tests\Fixtures\InitializedConstructor\D $target,
    ObjectMapperInterface $objectMapper,
    ObjectMapperMetadataFactoryInterface $metadataFactory,
    \SplObjectStorage $objectMap,
    ?PropertyAccessorInterface $propertyAccessor = null,
    ?ContainerInterface $transformCallableLocator = null,
    ?ContainerInterface $conditionCallableLocator = null,
    bool $mappingToObject = false
): Symfony\Component\ObjectMapper\Tests\Fixtures\InitializedConstructor\D {
    $ctorArguments = [];

    $mapToProperties = [];
    $objectMap[$source] = $target;

    if (!property_exists($source, 'bar') || (new \ReflectionProperty($source, 'bar'))->isInitialized($source)) {
        $value = MappingHelper::getValue($source, 'bar', $propertyAccessor);
        if (is_object($value) && MappingHelper::hasMappingTarget($value, $metadataFactory)) {
            $value = match (true) {
                $value === $source => $target,
                $objectMap->offsetExists($value) => $objectMap[$value],
                default => $objectMapper->map($value),
            };
        }
        $ctorArguments['bar'] = $value;
    }

    if (!$mappingToObject) {
        $target->__construct(...$ctorArguments);
    }
    foreach ($mapToProperties as $prop => $v) {
        MappingHelper::setValue($target, $prop, $v, $propertyAccessor);
    }
    return $target;
};

