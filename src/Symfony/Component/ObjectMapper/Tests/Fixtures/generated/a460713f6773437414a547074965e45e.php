<?php

use Psr\Container\ContainerInterface;
use Symfony\Component\ObjectMapper\Exception\MappingTransformException;
use Symfony\Component\ObjectMapper\Exception\MappingException;
use Symfony\Component\ObjectMapper\MappingHelper;
use Symfony\Component\ObjectMapper\ObjectMapperInterface;
use Symfony\Component\ObjectMapper\Metadata\ObjectMapperMetadataFactoryInterface;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

return function(
    Symfony\Component\ObjectMapper\Tests\Fixtures\ServiceLocator\A $source,
    Symfony\Component\ObjectMapper\Tests\Fixtures\ServiceLocator\B $target,
    ObjectMapperInterface $objectMapper,
    ObjectMapperMetadataFactoryInterface $metadataFactory,
    \SplObjectStorage $objectMap,
    ?PropertyAccessorInterface $propertyAccessor = null,
    ?ContainerInterface $transformCallableLocator = null,
    ?ContainerInterface $conditionCallableLocator = null,
    bool $mappingToObject = false
): Symfony\Component\ObjectMapper\Tests\Fixtures\ServiceLocator\B {
    $ctorArguments = [];
    $mapToProperties = [];
    $objectMap[$source] = $target;

    if (!property_exists($source, 'foo') || (new \ReflectionProperty($source, 'foo'))->isInitialized($source)) {
        $value = MappingHelper::getValue($source, 'foo', $propertyAccessor);
        $condition = MappingHelper::getCallable('Symfony\\Component\\ObjectMapper\\Tests\\Fixtures\\ServiceLocator\\ConditionCallable', $conditionCallableLocator);
        if ($condition && MappingHelper::call($condition, $value, $source, $target)) {
            $transform = MappingHelper::getCallable('Symfony\\Component\\ObjectMapper\\Tests\\Fixtures\\ServiceLocator\\TransformCallable', $transformCallableLocator);
            if ($transform) {
                $value = MappingHelper::call($transform, $value, $source, $target);
            }
            if (is_object($value) && MappingHelper::hasMappingTarget($value, $metadataFactory)) {
                $value = match (true) {
                    $value === $source => $target,
                    $objectMap->offsetExists($value) => $objectMap[$value],
                    default => $objectMapper->map($value),
                };
            }
            $mapToProperties['bar'] = $value;
        }
    }

    foreach ($mapToProperties as $prop => $v) {
        MappingHelper::setValue($target, $prop, $v, $propertyAccessor);
    }
    return $target;
};

