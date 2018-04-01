<?php

namespace Symphony\Component\Routing\Tests\Fixtures\AnnotationFixtures;

use Symphony\Component\Routing\Annotation\Route;

class ActionPathController
{
    /**
     * @Route("/path", name="action")
     */
    public function action()
    {
    }
}
