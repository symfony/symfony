<?php

namespace Symfony\Component\Routing\Tests\Fixtures\AttributeFixtures;

use Symfony\Component\Routing\Annotation\Route;

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
}
