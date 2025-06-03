<?php

namespace Symfony\Component\Routing\Tests\Fixtures\AttributeFixtures;

class ExtendedRouteOnMethodController
{
    #[ExtendedRoute(path: '/method-level', name: 'action')]
    public function action()
    {
    }
}
