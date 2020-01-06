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

use Symfony\Component\Serializer\Exception\LogicException;

/**
 * Denormalizes scalar value to specific scalar type.
 *
 * @author Alexander Menshchikov <amenshchikov@gmail.com>
 */
final class ScalarDenormalizer implements DenormalizerInterface, CacheableSupportsMethodInterface
{
    /**
     * {@inheritdoc}
     */
    public function supportsDenormalization($data, $type, $format = null)
    {
        return (is_scalar($data) || null === $data)
            && \in_array($type, ['int', 'integer', 'bool', 'boolean', 'float', 'string'], true);
    }

    /**
     * {@inheritdoc}
     */
    public function denormalize($data, $type, $format = null, array $context = [])
    {
        if (false === @settype($data, $type)) {
            throw new LogicException(sprintf('"%s" cannot be denormalized to %s', (string) $data, $type));
        }

        return $data;
    }

    public function hasCacheableSupportsMethod(): bool
    {
        return __CLASS__ === \get_class($this);
    }
}
