<?php

namespace Symfony\Component\Routing\Tests\Fixtures\AttributeFixtures;

use Symfony\Component\Routing\Attribute\Route;

class NothingButNameController
{
    #[Route(name: 'action')]
    public function action()
    {
    }
}
