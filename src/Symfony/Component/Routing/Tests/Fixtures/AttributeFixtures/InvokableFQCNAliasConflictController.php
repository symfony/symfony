<?php

namespace Symfony\Component\Routing\Tests\Fixtures\AttributeFixtures;

use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/foobarccc', name: self::class)]
class InvokableFQCNAliasConflictController
{
    public function __invoke()
    {
    }
}
