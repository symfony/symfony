<?php

use Psr\Container\ContainerInterface;
use Symfony\Component\ObjectMapper\Exception\MappingTransformException;
use Symfony\Component\ObjectMapper\Exception\MappingException;
use Symfony\Component\ObjectMapper\MappingHelper;
use Symfony\Component\ObjectMapper\ObjectMapperInterface;
use Symfony\Component\ObjectMapper\Metadata\ObjectMapperMetadataFactoryInterface;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

return function(
    Symfony\Component\ObjectMapper\Tests\Fixtures\PromotedConstructor\Source $source,
    Symfony\Component\ObjectMapper\Tests\Fixtures\PromotedConstructor\Target $target,
    ObjectMapperInterface $objectMapper,
    ObjectMapperMetadataFactoryInterface $metadataFactory,
    \SplObjectStorage $objectMap,
    ?PropertyAccessorInterface $propertyAccessor = null,
    ?ContainerInterface $transformCallableLocator = null,
    ?ContainerInterface $conditionCallableLocator = null,
    bool $mappingToObject = false
): Symfony\Component\ObjectMapper\Tests\Fixtures\PromotedConstructor\Target {
    $ctorArguments = [];

    $mapToProperties = [];
    $objectMap[$source] = $target;

    if (!property_exists($source, 'id') || (new \ReflectionProperty($source, 'id'))->isInitialized($source)) {
        $value = MappingHelper::getValue($source, 'id', $propertyAccessor);
        if (is_object($value) && MappingHelper::hasMappingTarget($value, $metadataFactory)) {
            $value = match (true) {
                $value === $source => $target,
                $objectMap->offsetExists($value) => $objectMap[$value],
                default => $objectMapper->map($value),
            };
        }
        $ctorArguments['id'] = $value;
    }

    if (!property_exists($source, 'name') || (new \ReflectionProperty($source, 'name'))->isInitialized($source)) {
        $value = MappingHelper::getValue($source, 'name', $propertyAccessor);
        if (is_object($value) && MappingHelper::hasMappingTarget($value, $metadataFactory)) {
            $value = match (true) {
                $value === $source => $target,
                $objectMap->offsetExists($value) => $objectMap[$value],
                default => $objectMapper->map($value),
            };
        }
        $ctorArguments['name'] = $value;
    }

    if (!$mappingToObject) {
        $target->__construct(...$ctorArguments);
    }
    if ($mappingToObject && $ctorArguments) {
        if (isset($ctorArguments['id'])) {
            $mapToProperties['id'] = $ctorArguments['id'];
        }
        if (isset($ctorArguments['name'])) {
            $mapToProperties['name'] = $ctorArguments['name'];
        }
    }
    foreach ($mapToProperties as $prop => $v) {
        MappingHelper::setValue($target, $prop, $v, $propertyAccessor);
    }
    return $target;
};

