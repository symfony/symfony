<?php

namespace Symfony\Component\Routing\Tests\Fixtures\AnnotationFixtures;

use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route(env="some-env")
 */
class RouteWithEnv
{
    /**
     * @Route("/path", name="action")
     */
    public function action()
    {
    }

    /**
     * @Route("/path2", name="action2", env="some-other-env")
     */
    public function action2()
    {
    }
}
