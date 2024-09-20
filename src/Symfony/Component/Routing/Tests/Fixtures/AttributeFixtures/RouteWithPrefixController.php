<?php

namespace Symfony\Component\Routing\Tests\Fixtures\AttributeFixtures;

use Symfony\Component\Routing\Attribute\Route;

#[Route('/prefix')]
class RouteWithPrefixController
{
    #[Route(path: '/path', name: 'action')]
    public function action()
    {
    }
}
