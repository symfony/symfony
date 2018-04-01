<?php

namespace Symphony\Component\Routing\Tests\Fixtures\AnnotationFixtures;

use Symphony\Component\Routing\Annotation\Route;

/**
 * @Route("/here", name="lol")
 */
class InvokableController
{
    public function __invoke()
    {
    }
}
