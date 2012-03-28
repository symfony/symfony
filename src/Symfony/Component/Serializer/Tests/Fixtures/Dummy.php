<?php

namespace Symfony\Component\Serializer\Tests\Fixtures;

use Symfony\Component\Serializer\Normalizer\NormalizableInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class Dummy implements NormalizableInterface
{
    public $foo;
    public $bar;
    public $baz;
    public $qux;

    public function normalize(NormalizerInterface $normalizer, $format = null)
    {
        return array(
            'foo' => $this->foo,
            'bar' => $this->bar,
            'baz' => $this->baz,
            'qux' => $this->qux,
        );
    }

    public function denormalize(DenormalizerInterface $denormalizer, $data, $format = null)
    {
        $this->foo = $data['foo'];
        $this->bar = $data['bar'];
        $this->baz = $data['baz'];
        $this->qux = $data['qux'];
    }
}
