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

use Symfony\Component\Serializer\Exception\BadMethodCallException;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Denormalizes arrays of objects.
 *
 * @author Alexander M. Turek <me@derrabus.de>
 *
 * @final since version 3.3.
 */
class ArrayDenormalizer implements DenormalizerInterface, SerializerAwareInterface
{
    /**
     * @var SerializerInterface|DenormalizerInterface
     */
    private $serializer;

    /**
     * {@inheritdoc}
     *
     * @throws NotNormalizableValueException
     */
    public function denormalize($data, $class, $format = null, array $context = [])
    {
        if (null === $this->serializer) {
            throw new BadMethodCallException('Please set a serializer before calling denormalize()!');
        }
        if (!\is_array($data)) {
            throw new InvalidArgumentException('Data expected to be an array, '.\gettype($data).' given.');
        }
        if ('[]' !== substr($class, -2)) {
            throw new InvalidArgumentException('Unsupported class: '.$class);
        }

        $serializer = $this->serializer;
        $class = substr($class, 0, -2);

        $builtinType = isset($context['key_type']) ? $context['key_type']->getBuiltinType() : null;
        foreach ($data as $key => $value) {
            if (null !== $builtinType && !\call_user_func('is_'.$builtinType, $key)) {
                throw new NotNormalizableValueException(sprintf('The type of the key "%s" must be "%s" ("%s" given).', $key, $builtinType, \gettype($key)));
            }

            $data[$key] = $serializer->denormalize($value, $class, $format, $context);
        }

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsDenormalization($data, $type, $format = null/*, array $context = []*/)
    {
        $context = \func_num_args() > 3 ? func_get_arg(3) : [];

        return '[]' === substr($type, -2)
            && $this->serializer->supportsDenormalization($data, substr($type, 0, -2), $format, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function setSerializer(SerializerInterface $serializer)
    {
        if (!$serializer instanceof DenormalizerInterface) {
            throw new InvalidArgumentException('Expected a serializer that also implements DenormalizerInterface.');
        }

        $this->serializer = $serializer;
    }
}
