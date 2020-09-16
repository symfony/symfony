<?php

namespace Symfony\Component\Routing\Tests\Fixtures\AttributeFixtures;

use Symfony\Component\Routing\Annotation\Route;

class ActionPathController
{
    #[Route('/path', name: 'action')]
    public function action()
    {
    }
}
