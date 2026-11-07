<?php

namespace Symfony\Component\Routing\Tests\Fixtures\AttributeFixtures;

use Symfony\Component\Routing\Attribute\Route;

#[Route(path: ['en' => '/en', 'fr' => '/fr'])]
class LocalizedPrefixWithUnlocalizedActionController
{
    #[Route('/translated', name: 'translated')]
    public function translated(): void
    {
    }

    #[Route('/.well-known/jwks.json', name: 'public_jwks', locale: false)]
    public function jwks(): void
    {
    }
}
