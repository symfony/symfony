<?php

namespace Symfony\Component\Routing\Tests\Fixtures\AttributeFixtures;

use Symfony\Component\Routing\Attribute\Route;

class LocalizedPassthroughLocaleActionController
{
    #[Route(path: ['{_locale}' => '/{_locale}', 'nl' => '/nl'], name: 'action')]
    public function action()
    {
    }

    #[Route(path: ['/{_locale}', 'nl' => '/nl'], name: 'action2')]
    public function action2()
    {
    }

}
