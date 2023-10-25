<?php

namespace Symfony\Component\Routing\Tests\Fixtures\AnnotationFixtures;

use Symfony\Component\Routing\Attribute\Route;

class MissingRouteNameController
{
    /**
     * @Route("/path")
     */
    public function action()
    {
    }
}
