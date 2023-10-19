<?php

namespace Symfony\Component\Routing\Tests\Fixtures\AnnotationFixtures;

use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Tests\Fixtures\Enum\TestIntBackedEnum;
use Symfony\Component\Routing\Tests\Fixtures\Enum\TestStringBackedEnum;

class DefaultValueController
{
    /**
     * @Route("/{default}/path", name="action")
     */
    public function action($default = 'value')
    {
    }

    /**
     * @Route("/hello/{name<\w+>}", name="hello_without_default")
     * @Route("/hello/{name<\w+>?Symfony}", name="hello_with_default")
     */
    public function hello(string $name = 'World')
    {
    }

    /**
     * @Route("/enum/{default}", name="string_enum_action")
     */
    public function stringEnumAction(TestStringBackedEnum $default = TestStringBackedEnum::Diamonds)
    {
    }

    /**
     * @Route("/enum/{default<\d+>}", name="int_enum_action")
     */
    public function intEnumAction(TestIntBackedEnum $default = TestIntBackedEnum::Diamonds)
    {
    }
}
