<?php

namespace Symfony\Component\Routing\Tests\Fixtures\AttributeFixtures;

use Symfony\Component\Routing\Attribute\Route;

class PrivateProtectedMethodsController
{
    #[Route(name: 'foo')]
    private function privateFoo()
    {
    }

    #[Route(name: 'foo')]
    protected function protectedFoo()
    {
    }

    #[Route(name: 'foo')]
    public function publicFoo()
    {
    }
}
