<?php

namespace Symfony\Component\Routing\Tests\Fixtures\AttributeFixtures;

use Symfony\Component\Routing\Attribute\Route;

#[Route(env: 'some-env')]
class RouteWithEnv
{
    #[Route(path: '/path', name: 'action')]
    public function action()
    {
    }

    #[Route(path: '/path2', name: 'action2', env: 'some-other-env')]
    public function action2()
    {
    }

    #[Route(path: '/path3', name: 'action3', env: ['some-other-env', 'some-other-env-two'])]
    public function action3()
    {
    }

    #[Route(path: '/path4', name: 'action4', env: null)]
    public function action4()
    {
    }

    #[Route(path: '/path5', name: 'action5', env: ['some-other-env', 'some-env'])]
    public function action5()
    {
    }
}
