<?php

namespace Symphony\Component\Routing\Tests\Fixtures\AnnotationFixtures;

use Symphony\Component\Routing\Annotation\Route;

class LocalizedActionPathController
{
    /**
     * @Route(path={"en": "/path", "nl": "/pad"}, name="action")
     */
    public function action()
    {
    }
}
