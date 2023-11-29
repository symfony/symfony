<?php

namespace Symfony\Component\Routing\Tests\Fixtures\AnnotationFixtures;

use Symfony\Component\Routing\Attribute\Route;

/**
 * @Route("/foobarccc", name="Symfony\Component\Routing\Tests\Fixtures\AnnotationFixtures\InvokableFQCNAliasConflictController")
 */
class InvokableFQCNAliasConflictController
{
    public function __invoke()
    {
    }
}
