<?php

namespace Symfony\Component\Routing\Tests\Fixtures\AnnotationFixtures;

use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/the/path")
 */
class MethodActionControllers
{
    /**
     * @Route(name="post", methods={"POST"})
     */
    public function post()
    {
    }

    /**
     * @Route(name="put", methods={"PUT"})
     */
    public function put()
    {
    }
}
