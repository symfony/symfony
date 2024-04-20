<?php

namespace Symfony\Component\Routing\Tests\Fixtures\AttributeFixtures;

use Symfony\Component\Routing\Attribute\Route;

#[Route(path: ['en' => '/the/path', 'nl' => '/het/pad'])]
class LocalizedMethodActionControllers
{
    #[Route(name: 'post', methods: ['POST'])]
    public function post()
    {
    }

    #[Route(name: 'put', methods: ['PUT'])]
    public function put()
    {
    }
}
