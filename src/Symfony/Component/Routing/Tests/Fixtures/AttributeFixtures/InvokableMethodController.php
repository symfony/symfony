<?php

namespace Symfony\Component\Routing\Tests\Fixtures\AttributeFixtures;

use Symfony\Component\Routing\Attribute\Route;

class InvokableMethodController
{
    #[Route(path: '/here', name: 'lol', methods: ["GET", "POST"], schemes: ['https'])]
    public function __invoke()
    {
    }
}
