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

use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

final class FooInterfaceDummyDenormalizer implements DenormalizerInterface
{
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): array
    {
        $result = [];
        foreach ($data as $foo) {
            $fooDummy = new FooImplementationDummy();
            $fooDummy->name = $foo['name'];
            $result[] = $fooDummy;
        }

        return $result;
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        if (str_ends_with($type, '[]')) {
            $className = substr($type, 0, -2);
            $classImplements = class_implements($className);
            \assert(\is_array($classImplements));

            return class_exists($className) && \in_array(FooDummyInterface::class, $classImplements, true);
        }

        return false;
    }

    /**
     * @return array<string, bool>
     */
    public function getSupportedTypes(?string $format): array
    {
        return [FooDummyInterface::class.'[]' => false];
    }
}
