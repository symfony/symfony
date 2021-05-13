<?php

namespace Symfony\Component\Routing\Tests\Fixtures\ServiceProvider;

use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Service\ServiceProviderInterface;

class ExpressionLanguageServiceProvider implements ServiceProviderInterface
{
    public function get(string $id) {}

    public function has(string $id) {}

    public function getProvidedServices(): array
    {
        return ['router' => '?'.RouterInterface::class];
    }
}
