<?php

namespace Symfony\Component\Routing\Tests\Fixtures\AttributeFixtures;

use Symfony\Component\Routing\Annotation\Route;

class FooController
{
    #[Route('/Blog')]
    public function simplePath()
    {
    }

    #[Route(['nl' => '/hier', 'en' => '/here'])]
    public function localized()
    {
    }

    #[Route(requirements: ['locale' => 'en'])]
    public function requirements()
    {
    }

    #[Route(options: ['compiler_class' => 'RouteCompiler'])]
    public function options()
    {
    }

    #[Route(name: 'blog_index')]
    public function name()
    {
    }

    #[Route(defaults: ['_controller' => 'MyBlogBundle:Blog:index'])]
    public function defaults()
    {
    }

    #[Route(schemes: ['https'])]
    public function schemes()
    {
    }

    #[Route(methods: ['GET', 'POST'])]
    public function methods()
    {
    }

    #[Route(host: '{locale}.example.com')]
    public function host()
    {
    }

    #[Route(condition: 'context.getMethod() == \'GET\'')]
    public function condition()
    {
    }
}
