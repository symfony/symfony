<?php

namespace Symfony\Tests\Component\Serializer\Fixtures;

use Symfony\Component\Serializer\Normalizer\NormalizableInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class ScalarDummy implements NormalizableInterface
{
    public $foo;
    public $xmlFoo;

    public function normalize(NormalizerInterface $normalizer, $format, $properties = null)
    {
        return $format === 'xml' ? $this->xmlFoo : $this->foo;
    }

    public function denormalize(NormalizerInterface $normalizer, $data, $format = null)
    {
        if ($format === 'xml') {
            $this->xmlFoo = $data;
        } else {
            $this->foo = $data;
        }
    }
}