<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Fixtures;

use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;

/**
 * @author Guilhem N. <egetick@gmail.com>
 */
class StaticConstructorNormalizer extends AbstractObjectNormalizer
{
    public function getSupportedTypes(?string $format): array
    {
        return [StaticConstructorDummy::class];
    }

    protected function extractAttributes(object $object, string $format = null, array $context = []): array
    {
        return get_object_vars($object);
    }

    protected function getAttributeValue(object $object, string $attribute, string $format = null, array $context = []): mixed
    {
        return $object->$attribute;
    }

    protected function setAttributeValue(object $object, string $attribute, mixed $value, string $format = null, array $context = []): void
    {
        $object->$attribute = $value;
    }

    protected function getConstructor(array &$data, string $class, array &$context, \ReflectionClass $reflectionClass, array|bool $allowedAttributes): ?\ReflectionMethod
    {
        if (is_a($class, StaticConstructorDummy::class, true)) {
            return new \ReflectionMethod($class, 'create');
        }

        return parent::getConstructor($data, $class, $context, $reflectionClass, $allowedAttributes);
    }
}
