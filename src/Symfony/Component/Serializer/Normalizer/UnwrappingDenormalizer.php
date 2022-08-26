<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Normalizer;

use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerAwareTrait;

/**
 * @author Eduard Bulava <bulavaeduard@gmail.com>
 */
final class UnwrappingDenormalizer implements DenormalizerInterface, SerializerAwareInterface, CacheableSupportsMethodInterface
{
    use SerializerAwareTrait;

    public const UNWRAP_PATH = 'unwrap_path';

    private $propertyAccessor;

    public function __construct(PropertyAccessorInterface $propertyAccessor = null)
    {
        $this->propertyAccessor = $propertyAccessor ?? PropertyAccess::createPropertyAccessor();
    }

    public function denormalize(mixed $data, string $class, string $format = null, array $context = []): mixed
    {
        $propertyPath = $context[self::UNWRAP_PATH];
        $context['unwrapped'] = true;

        if ($propertyPath) {
            if (!$this->propertyAccessor->isReadable($data, $propertyPath)) {
                return null;
            }

            $data = $this->propertyAccessor->getValue($data, $propertyPath);
        }

        return $this->serializer->denormalize($data, $class, $format, $context);
    }

    public function supportsDenormalization(mixed $data, string $type, string $format = null, array $context = []): bool
    {
        return \array_key_exists(self::UNWRAP_PATH, $context) && !isset($context['unwrapped']);
    }

    public function hasCacheableSupportsMethod(): bool
    {
        return $this->serializer instanceof CacheableSupportsMethodInterface && $this->serializer->hasCacheableSupportsMethod();
    }
}
