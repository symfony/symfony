<?php

namespace Symfony\Component\Routing\Tests\Fixtures\AnnotationFixtures;

use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/here", name="lol")
 */
class InvokableController
{
    public function __invoke()
    {
    }
}
