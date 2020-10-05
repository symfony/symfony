<?php

namespace Symfony\Component\Routing\Tests\Fixtures\AttributeFixtures;

use Symfony\Component\Routing\Annotation\Route;

class MissingRouteNameController
{
    #[Route('/path')]
    public function action()
    {
    }
}
