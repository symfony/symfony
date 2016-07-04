<?php

namespace Symfony\Component\Serializer\Tests\Fixtures;

use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Théo FIDRY <theo.fidry@gmail.com>
 */
class DenormalizerDecoratorSerializer implements SerializerInterface
{
    private $normalizer;

    /**
     * @param NormalizerInterface|DenormalizerInterface $normalizer
     */
    public function __construct($normalizer)
    {
        if (false === $normalizer instanceof NormalizerInterface && false === $normalizer instanceof DenormalizerInterface) {
            throw new \InvalidArgumentException();
        }

        $this->normalizer = $normalizer;
    }

    /**
     * {@inheritdoc}
     */
    public function serialize($data, $format, array $context = array())
    {
        return $this->normalizer->normalize($data, $format, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function deserialize($data, $type, $format, array $context = array())
    {
        return $this->normalizer->denormalize($data, $type, $format, $context);
    }
}
