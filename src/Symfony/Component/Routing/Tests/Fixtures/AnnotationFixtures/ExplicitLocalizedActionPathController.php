<?php

namespace Symfony\Component\Routing\Tests\Fixtures\AnnotationFixtures;

use Symfony\Component\Routing\Annotation\Route;

class ExplicitLocalizedActionPathController
{
    /**
     * @Route(path={"en": "/path", "nl": "/pad"}, name="action")
     */
    public function action()
    {
    }
}
