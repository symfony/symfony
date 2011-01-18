<?php

namespace Symfony\Tests\Component\Serializer\Fixtures;

use Symfony\Component\Serializer\Normalizer\NormalizableInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class Dummy implements NormalizableInterface
{
    public $foo;
    public $bar;
    public $baz;
    public $qux;

    public function normalize(NormalizerInterface $normalizer, $format, $properties = null)
    {
        return array(
            'foo' => $this->foo,
            'bar' => $this->bar,
            'baz' => $this->baz,
            'qux' => $this->qux,
        );
    }

    public function denormalize(NormalizerInterface $normalizer, $data, $format = null)
    {
        $this->foo = $data['foo'];
        $this->bar = $data['bar'];
        $this->baz = $data['baz'];
        $this->qux = $data['qux'];
    }
}