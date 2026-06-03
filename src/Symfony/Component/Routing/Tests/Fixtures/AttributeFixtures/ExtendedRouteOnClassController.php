<?php

namespace Symfony\Component\Routing\Tests\Fixtures\AttributeFixtures;

use Symfony\Component\Routing\Attribute\Route;

#[ExtendedRoute('/class-level')]
class ExtendedRouteOnClassController
{
    #[Route(path: '/method-level', name: 'action')]
    public function action()
    {
    }
}
