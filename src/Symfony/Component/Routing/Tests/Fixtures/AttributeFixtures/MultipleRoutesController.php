<?php

namespace Symfony\Component\Routing\Tests\Fixtures\AttributeFixtures;

use Symfony\Component\Routing\Annotation\Route;

#[Route('/MainRoute2')]
#[Route('/RouteAlias2')]
class MultipleRoutesController
{
    #[Route('/SubPath')]
    #[Route('/SubAlias')]
    public function withAlias()
    {
    }
}
