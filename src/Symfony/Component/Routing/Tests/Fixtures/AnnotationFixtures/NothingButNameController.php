<?php

namespace Symfony\Component\Routing\Tests\Fixtures\AnnotationFixtures;

use Symfony\Component\Routing\Attribute\Route;

class NothingButNameController
{
    /**
     * @Route(name="action")
     */
    public function action()
    {
    }
}
