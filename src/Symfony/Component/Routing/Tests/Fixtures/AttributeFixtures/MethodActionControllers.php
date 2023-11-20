<?php

namespace Symfony\Component\Routing\Tests\Fixtures\AttributeFixtures;

use Symfony\Component\Routing\Attribute\Route;

#[Route('/the/path')]
class MethodActionControllers
{
    #[Route(name: 'post', methods: ['POST'])]
    public function post()
    {
    }

    #[Route(name: 'put', methods: ['PUT'], priority: 10)]
    public function put()
    {
    }
}
