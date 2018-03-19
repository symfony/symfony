<?php

namespace Symfony\Component\Routing\Tests\Fixtures\AnnotationFixtures;

use Symfony\Component\Routing\Annotation\Route;

class ActionPathController
{
    /**
     * @Route("/path", name="action")
     */
    public function action()
    {
    }
}
