<?php

namespace Symphony\Component\Routing\Tests\Fixtures\AnnotationFixtures;

use Symphony\Component\Routing\Annotation\Route;

/**
 * @Route("/prefix", host="frankdejonge.nl", condition="lol=fun")
 */
class PrefixedActionPathController
{
    /**
     * @Route("/path", name="action")
     */
    public function action()
    {
    }
}
