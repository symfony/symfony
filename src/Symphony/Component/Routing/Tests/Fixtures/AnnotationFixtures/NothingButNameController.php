<?php

namespace Symphony\Component\Routing\Tests\Fixtures\AnnotationFixtures;

use Symphony\Component\Routing\Annotation\Route;

class NothingButNameController
{
    /**
     * @Route(name="action")
     */
    public function action()
    {
    }
}
