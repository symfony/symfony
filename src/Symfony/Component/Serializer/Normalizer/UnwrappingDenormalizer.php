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
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Eduard Bulava <bulavaeduard@gmail.com>
 */
final class UnwrappingDenormalizer implements DenormalizerInterface, DenormalizerAwareInterface
{
    use DenormalizerAwareTrait;

    public const UNWRAP_PATH = 'unwrap_path';

    private readonly PropertyAccessorInterface $propertyAccessor;

    public function __construct(PropertyAccessorInterface $propertyAccessor = null)
    {
        $this->propertyAccessor = $propertyAccessor ?? PropertyAccess::createPropertyAccessor();
    }

    public function getSupportedTypes(?string $format): array
    {
        return ['*' => false];
    }

    public function denormalize(mixed $data, string $type, string $format = null, array $context = []): mixed
    {
        $propertyPath = $context[self::UNWRAP_PATH];
        $context['unwrapped'] = true;

        if ($propertyPath) {
            if (!$this->propertyAccessor->isReadable($data, $propertyPath)) {
                return null;
            }

            $data = $this->propertyAccessor->getValue($data, $propertyPath);
        }

        return $this->denormalizer->denormalize($data, $type, $format, $context);
    }

    public function supportsDenormalization(mixed $data, string $type, string $format = null, array $context = []): bool
    {
        return \array_key_exists(self::UNWRAP_PATH, $context) && !isset($context['unwrapped']);
    }

    public function setSerializer(SerializerInterface $serializer): void
    {
        trigger_deprecation('symfony/serializer', '7.1', 'The "%s()" method is deprecated, use "setDenormalizer()" instead.', __METHOD__);

        if (!$serializer instanceof DenormalizerInterface) {
            throw new LogicException(sprintf('Cannot set denormalizer because the injected serializer does not implement the "%s".', DenormalizerInterface::class));
        }

        $this->setDenormalizer($serializer);
    }
}
