<?php

namespace Symfony\Tests\Component\Serializer\Fixtures;

use Symfony\Component\Serializer\Normalizer\NormalizableInterface;
use Symfony\Component\Serializer\SerializerInterface;

class ScalarDummy implements NormalizableInterface
{
    public $foo;
    public $xmlFoo;

    public function normalize(SerializerInterface $serializer, $format = null)
    {
        return $format === 'xml' ? $this->xmlFoo : $this->foo;
    }

    public function denormalize(SerializerInterface $serializer, $data, $format = null)
    {
        if ($format === 'xml') {
            $this->xmlFoo = $data;
        } else {
            $this->foo = $data;
        }
    }
}
