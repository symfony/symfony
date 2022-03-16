<?php

namespace Symfony\Component\Routing\Tests\Fixtures\AttributeFixtures;

use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/here', name: 'lol', methods: ["GET", "POST"], schemes: ['https'])]
class InvokableController
{
    public function __invoke()
    {
    }
}
