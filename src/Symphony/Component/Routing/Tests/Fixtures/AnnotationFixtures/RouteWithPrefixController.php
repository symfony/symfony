<?php

namespace Symphony\Component\Routing\Tests\Fixtures\AnnotationFixtures;

use Symphony\Component\Routing\Annotation\Route;

/**
 * @Route("/prefix")
 */
class RouteWithPrefixController
{
    /**
     * @Route("/path", name="action")
     */
    public function action()
    {
    }
}
