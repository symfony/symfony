<?php

namespace Symfony\Component\Routing\Tests\Fixtures\AttributeFixtures;

use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/prefix', host: 'frankdejonge.nl', condition: 'lol=fun')]
class PrefixedActionPathController
{
    #[Route(path: '/path', name: 'action')]
    public function action()
    {
    }
}
