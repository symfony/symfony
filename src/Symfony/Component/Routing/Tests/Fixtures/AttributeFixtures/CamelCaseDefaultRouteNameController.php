<?php

namespace Symfony\Component\Routing\Tests\Fixtures\AttributeFixtures;

use Symfony\Component\Routing\Attribute\Route;

class CamelCaseDefaultRouteNameController
{
    #[Route('/change-password')]
    public function changePassword()
    {
    }
}
