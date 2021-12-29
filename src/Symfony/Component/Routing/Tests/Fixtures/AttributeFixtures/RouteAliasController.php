<?php

namespace Symfony\Component\Routing\Tests\Fixtures\AttributeFixtures;

use Symfony\Component\Routing\Annotation\Route;

#[Route(['/MainRoute1', '/RouteAlias1'])]
class RouteAliasController
{
    #[Route(['/SubPath', '/SubAlias'])]
    public function withAlias()
    {
    }
}
