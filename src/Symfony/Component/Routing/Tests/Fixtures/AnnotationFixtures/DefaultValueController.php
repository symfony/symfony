<?php

namespace Symfony\Component\Routing\Tests\Fixtures\AnnotationFixtures;

use Symfony\Component\Routing\Annotation\Route;

class DefaultValueController
{
    /**
     * @Route("/{default}/path", name="action")
     */
    public function action($default = 'value')
    {
    }
}
