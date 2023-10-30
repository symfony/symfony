<?php

namespace Symfony\Component\Routing\Tests\Fixtures\AnnotationFixtures;

use Symfony\Component\Routing\Attribute\Route;

class InvokableMethodController
{
    /**
     * @Route("/here", name="lol", methods={"GET", "POST"}, schemes={"https"})
     */
    public function __invoke()
    {
    }
}
