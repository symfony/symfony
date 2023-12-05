<?php

namespace Symfony\Component\Serializer\Tests\Fixtures\CustomNormalizer\FullTypeHints;

class PrivateConstructor
{
    public int $foo;

    private function __construct()
    {
    }

    public static function create(int $foo)
    {
        $model = new self();
        $model->foo = $foo;
        return $model;
    }

}
