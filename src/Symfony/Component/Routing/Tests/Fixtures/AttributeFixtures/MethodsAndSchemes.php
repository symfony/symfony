<?php

namespace Symfony\Component\Routing\Tests\Fixtures\AttributeFixtures;

use Symfony\Component\Routing\Attribute\Route;

final class MethodsAndSchemes
{
    #[Route(path: '/array-many', name: 'array_many', methods: ['GET', 'POST'], schemes: ['http', 'https'])]
    public function arrayMany(): void
    {
    }

    #[Route(path: '/array-one', name: 'array_one', methods: ['GET'], schemes: ['http'])]
    public function arrayOne(): void
    {
    }

    #[Route(path: '/string', name: 'string', methods: 'POST', schemes: 'https')]
    public function string(): void
    {
    }
}
