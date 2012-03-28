<?php

namespace Symfony\Component\Serializer\Tests\Fixtures;

use Symfony\Component\Serializer\Normalizer\NormalizableInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizableInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class ScalarDummy implements NormalizableInterface, DenormalizableInterface
{
    public $foo;
    public $xmlFoo;

    public function normalize(NormalizerInterface $normalizer, $format = null)
    {
        return $format === 'xml' ? $this->xmlFoo : $this->foo;
    }

    public function denormalize(DenormalizerInterface $denormalizer, $data, $format = null)
    {
        if ($format === 'xml') {
            $this->xmlFoo = $data;
        } else {
            $this->foo = $data;
        }
    }
}
