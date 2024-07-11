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

use Symfony\Component\Serializer\Exception\MissingConstructorArgumentsException;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * @author Ian Bentley <ian@idbentley.com>
 */
final class UnionDenormalizer implements DenormalizerAwareInterface, DenormalizerInterface
{
    use DenormalizerAwareTrait;

    public function setDenormalizer(DenormalizerInterface $denormalizer): void
    {
        $this->denormalizer = $denormalizer;
    }

    public function getSupportedTypes(?string $format): array
    {
        return ['*' => true];
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        if ($this->denormalizer === null) {
            throw new \BadMethodCallException(sprintf('The nested denormalizer needs to be set to allow "%s()" to be used.', __METHOD__));
        }
        if (str_contains($type, '|')) {
            $possibleTypes = explode('|', $type);
            $support = true;

            // all possible types must be supported
            foreach ($possibleTypes as $possibleType) {
                $typeSupport = $this->denormalizer->supportsDenormalization($data, $possibleType, $format, $context);
                $support = $support && $typeSupport;
            }
            return $support;
        }

        return false;
    }

    /** @phpstan-ignore-next-line */
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        $typeResolver = new \phpDocumentor\Reflection\TypeResolver();
        $result = $typeResolver->resolve($type);
        $possibleTypes = explode('|', $type);

        $extraAttributesException = null;
        $missingConstructorArgumentsException = null;

        if (count($possibleTypes) == 1) {
            return $this->denormalizer->denormalize($data, $type, $format, $context);
        }

        foreach ($possibleTypes as $possibleType) {
            if (null === $data && $possibleType->isNullable()) {
                return null;
            }

            try {
                return $this->denormalizer->denormalize($data, $possibleType, $format, $context);
            } catch (MissingConstructorArgumentsException $e) {
                echo "Couldn't denormalize $possibleType: $e\n";
            }
        }
        throw new NotNormalizableValueException("Couldn't denormalize any of the possible types");
    }
}
