<?php

namespace Symfony\Component\Routing\Tests\Fixtures\AnnotationFixtures;

use Symfony\Component\Routing\Attribute\Route;

/**
 * @Route("/test", utf8=true)
 */
class Utf8ActionControllers
{
    /**
     * @Route(name="one")
     */
    public function one()
    {
    }

    /**
     * @Route(name="two", utf8=false)
     */
    public function two()
    {
    }
}
