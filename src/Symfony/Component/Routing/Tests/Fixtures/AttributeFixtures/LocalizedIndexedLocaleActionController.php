<?php

namespace Symfony\Component\Routing\Tests\Fixtures\AttributeFixtures;

use Symfony\Component\Routing\Attribute\Route;

class LocalizedIndexedLocaleActionController
{
    #[Route(path: [1 => '/en', 2 => '/nl'], name: 'error')]
    public function error()
    {
    }

}
