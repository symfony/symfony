<?php

namespace Symfony\Component\Routing\Tests\Fixtures\AttributeFixtures;

use Symfony\Component\Routing\Attribute\Route;

#[Route(path: ["nl" => "/hier", "en" => "/here"], name: 'action')]
class InvokableLocalizedController
{
    public function __invoke()
    {
    }
}
