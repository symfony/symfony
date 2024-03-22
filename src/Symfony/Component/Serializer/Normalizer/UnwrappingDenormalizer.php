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
use Symfony\Component\Serializer\Exception\LogicException;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerAwareTrait;

/**
 * @author Eduard Bulava <bulavaeduard@gmail.com>
 */
final class UnwrappingDenormalizer implements DenormalizerInterface, SerializerAwareInterface
{
    use SerializerAwareTrait;

    public const UNWRAP_PATH = 'unwrap_path';

    private readonly PropertyAccessorInterface $propertyAccessor;

    public function __construct(?PropertyAccessorInterface $propertyAccessor = null)
    {
        $this->propertyAccessor = $propertyAccessor ?? PropertyAccess::createPropertyAccessor();
    }

    public function getSupportedTypes(?string $format): array
    {
        return ['*' => false];
    }

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        $propertyPath = $context[self::UNWRAP_PATH];
        $context['unwrapped'] = true;

        if ($propertyPath) {
            if (!$this->propertyAccessor->isReadable($data, $propertyPath)) {
                return null;
            }

            $data = $this->propertyAccessor->getValue($data, $propertyPath);
        }

        if (!$this->serializer instanceof DenormalizerInterface) {
            throw new LogicException('Cannot unwrap path because the injected serializer is not a denormalizer.');
        }

        return $this->serializer->denormalize($data, $type, $format, $context);
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return \array_key_exists(self::UNWRAP_PATH, $context) && !isset($context['unwrapped']);
    }
}
