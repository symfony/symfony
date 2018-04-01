<?php

namespace Symphony\Component\Routing\Tests\Fixtures\AnnotationFixtures;

use Symphony\Component\Routing\Annotation\Route;

class MissingRouteNameController
{
    /**
     * @Route("/path")
     */
    public function action()
    {
    }
}
