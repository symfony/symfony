<?php

namespace Symfony\Component\Routing\Tests\Fixtures\AttributeFixtures;

use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Tests\Fixtures\Enum\TestIntBackedEnum;
use Symfony\Component\Routing\Tests\Fixtures\Enum\TestStringBackedEnum;

class DefaultValueController
{
    #[Route(path: '/{default}/path', name: 'action')]
    public function action($default = 'value')
    {
    }

    #[
        Route(path: '/hello/{name<\w+>}', name: 'hello_without_default'),
        Route(path: 'hello/{name<\w+>?Symfony}', name: 'hello_with_default'),
    ]
    public function hello(string $name = 'World')
    {
    }

    #[Route(path: '/enum/{default}', name: 'string_enum_action')]
    public function stringEnumAction(TestStringBackedEnum $default = TestStringBackedEnum::Diamonds)
    {
    }

    #[Route(path: '/enum/{default<\d+>}', name: 'int_enum_action')]
    public function intEnumAction(TestIntBackedEnum $default = TestIntBackedEnum::Diamonds)
    {
    }
}
