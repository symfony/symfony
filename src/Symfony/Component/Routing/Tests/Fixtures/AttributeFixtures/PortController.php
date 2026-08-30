<?php

namespace Symfony\Component\Routing\Tests\Fixtures\AttributeFixtures;

use Symfony\Component\Routing\Attribute\Route;

#[Route(port: 8000)]
final class PortController
{
    #[Route(path: '/inherited', name: 'inherited_port')]
    public function inherited(): void
    {
    }

    #[Route(path: '/overridden', name: 'overridden_port', port: 8001)]
    public function overridden(): void
    {
    }

    #[Route(path: '/string', name: 'string_port', port: '8002')]
    public function string(): void
    {
    }
}
