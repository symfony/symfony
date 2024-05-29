<?php

namespace Symfony\Component\Routing\Tests\Fixtures\AttributeFixtures;

use Symfony\Component\Routing\Annotation\Route;

class RouteWithPriorityController
{
    #[Route('/important', name: 'important', priority: 2)]
    public function important()
    {
    }

    #[Route('/also-important', name: 'also_important', priority: 1, defaults: ['_locale' => 'cs'])]
    public function alsoImportant()
    {
    }
}
