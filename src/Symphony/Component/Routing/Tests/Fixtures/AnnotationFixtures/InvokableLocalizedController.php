<?php

namespace Symphony\Component\Routing\Tests\Fixtures\AnnotationFixtures;

use Symphony\Component\Routing\Annotation\Route;

/**
 * @Route(path={"nl": "/hier", "en": "/here"}, name="action")
 */
class InvokableLocalizedController
{
    public function __invoke()
    {
    }
}
