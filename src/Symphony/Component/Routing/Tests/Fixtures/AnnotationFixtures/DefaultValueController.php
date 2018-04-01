<?php

namespace Symphony\Component\Routing\Tests\Fixtures\AnnotationFixtures;

use Symphony\Component\Routing\Annotation\Route;

class DefaultValueController
{
    /**
     * @Route("/{default}/path", name="action")
     */
    public function action($default = 'value')
    {
    }
}
